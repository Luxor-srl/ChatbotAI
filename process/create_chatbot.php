<?php
require_once '../config/database.php';

// Inizializza il file di debug
file_put_contents('debug.txt', "\n=== NEW CHATBOT CREATION REQUEST ===\n" . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    file_put_contents('debug.txt', "Error: Method not allowed\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$websiteUrl = filter_input(INPUT_POST, 'website_url', FILTER_SANITIZE_URL);
$chatbotName = filter_input(INPUT_POST, 'chatbot_name', FILTER_SANITIZE_STRING);
$scrapingDepth = filter_input(INPUT_POST, 'scraping_depth', FILTER_VALIDATE_INT) ?: 2;
$maxPages = filter_input(INPUT_POST, 'max_pages', FILTER_VALIDATE_INT) ?: 50;

file_put_contents('debug.txt', "Received data:\nURL: {$websiteUrl}\nName: {$chatbotName}\nDepth: {$scrapingDepth}\nMax Pages: {$maxPages}\n", FILE_APPEND);

if (!$websiteUrl || !$chatbotName) {
    file_put_contents('debug.txt', "Error: Missing required fields\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'Tutti i campi sono obbligatori']);
    exit;
}

// Validate URL format
if (!filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
    file_put_contents('debug.txt', "Error: Invalid URL format\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'URL non valido. Assicurati di includere http:// o https://']);
    exit;
}

try {
    // Inizializza il database
    $db = Database::getInstance();
    file_put_contents('debug.txt', "Database connection established\n", FILE_APPEND);

    // Crea un nuovo chatbot nel database
    $chatbotId = $db->insert('chatbots', [
        'name' => $chatbotName,
        'website_url' => $websiteUrl,
        'status' => 'crawling',
        'scraping_depth' => $scrapingDepth,
        'max_pages' => $maxPages
    ]);

    file_put_contents('debug.txt', "Chatbot created with ID: {$chatbotId}\n", FILE_APPEND);

    // Avvia il processo di crawling in background
    $scriptPath = realpath(__DIR__ . '/crawl_site.php');
    $logFile = realpath(__DIR__) . '/crawling_' . $chatbotId . '.log';
    $cmd = sprintf(
        'nohup php %s > %s 2>&1 & echo $!',
        escapeshellarg($scriptPath),
        escapeshellarg($logFile)
    );
    
    file_put_contents('debug.txt', "Executing command: {$cmd}\n", FILE_APPEND);
    
    $pid = shell_exec($cmd);
    file_put_contents('debug.txt', "Crawling process started with PID: {$pid}\n", FILE_APPEND);

    echo json_encode([
        'success' => true,
        'chatbotId' => $chatbotId,
        'message' => 'Chatbot creato con successo. Il crawling è in corso...'
    ]);

} catch (Exception $e) {
    file_put_contents('debug.txt', "Error in chatbot creation: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
exit;

function startScraping($url, $chatbotId) {
    file_put_contents('debug.txt', "\n=== STARTING SCRAPING ===\n", FILE_APPEND);
    file_put_contents('debug.txt', "URL: " . $url . "\n", FILE_APPEND);
    file_put_contents('debug.txt', "Chatbot ID: " . $chatbotId . "\n", FILE_APPEND);

    try {
        // Inizializza cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        $html = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception("Curl Error: " . curl_error($ch));
        }
        
        // Crea un DOM parser
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        
        // Rimuovi script, style e commenti
        removeUnwantedTags($dom);
        
        // Estrai il contenuto significativo
        $xpath = new DOMXPath($dom);
        
        // Array per memorizzare i contenuti delle diverse sezioni
        $pageContent = [];
        
        // Cerca contenuti in div e section con class o id significativi
        $contentNodes = $xpath->query('//div[contains(@class, "content") or contains(@class, "main") or contains(@id, "content") or contains(@id, "main")]|//section|//article');
        foreach ($contentNodes as $node) {
            $content = extractCleanText($node);
            if (strlen($content) > 100) { // Prendi solo contenuti significativi
                $pageContent[] = $content;
            }
        }
        
        // Cerca anche contenuti in elementi specifici
        $elements = [
            '//h1',
            '//h2',
            '//h3',
            '//p[string-length(normalize-space()) > 50]',
            '//div[contains(@class, "description")]',
            '//div[contains(@class, "about")]'
        ];
        
        foreach ($elements as $query) {
            $nodes = $xpath->query($query);
            foreach ($nodes as $node) {
                $content = extractCleanText($node);
                if (!empty($content) && strlen($content) > 50) {
                    $pageContent[] = $content;
                }
            }
        }
        
        // Rimuovi duplicati
        $pageContent = array_unique($pageContent);
        
        // Debug del contenuto estratto
        file_put_contents('debug.txt', "\n=== EXTRACTED CONTENT ===\n", FILE_APPEND);
        file_put_contents('debug.txt', "Number of content pieces: " . count($pageContent) . "\n", FILE_APPEND);
        file_put_contents('debug.txt', "Content: " . print_r($pageContent, true) . "\n", FILE_APPEND);
        
        // Salva il contenuto nel database
        $db = Database::getInstance();
        
        // Converti l'array in JSON
        $jsonContent = json_encode(array_values($pageContent));
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON encode error: " . json_last_error_msg());
        }
        
        // Salva la pagina nel database
        $db->insert('scraped_pages', [
            'chatbot_id' => $chatbotId,
            'url' => $url,
            'content' => $jsonContent,
            'scraped_at' => date('Y-m-d H:i:s')
        ]);
        
        // Aggiorna lo stato del chatbot a completato
        updateChatbotStatus($chatbotId, 'completed');
        file_put_contents('debug.txt', "\n=== SCRAPING COMPLETED ===\n", FILE_APPEND);
        
    } catch (Exception $e) {
        file_put_contents('debug.txt', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
        updateChatbotStatus($chatbotId, 'error');
    }
}

function removeUnwantedTags($dom) {
    $unwantedTags = ['script', 'style', 'iframe', 'link', 'meta'];
    foreach ($unwantedTags as $tag) {
        $elements = $dom->getElementsByTagName($tag);
        while ($elements->length > 0) {
            $element = $elements->item(0);
            $element->parentNode->removeChild($element);
        }
    }
}

function extractCleanText($node) {
    // Ottieni il testo e rimuovi spazi extra
    $text = $node->textContent;
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    // Rimuovi caratteri di controllo e formattazione
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    
    return $text;
}

function updateChatbotStatus($chatbotId, $status) {
    try {
        $db = Database::getInstance();
        $db->query(
            "UPDATE chatbots SET status = ?, updated_at = NOW() WHERE id = ?",
            [$status, $chatbotId]
        );
        
        // Debug log
        file_put_contents('debug.txt', "=== UPDATE CHATBOT STATUS ===\n", FILE_APPEND);
        file_put_contents('debug.txt', "Chatbot ID: " . $chatbotId . "\n", FILE_APPEND);
        file_put_contents('debug.txt', "New Status: " . $status . "\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('debug.txt', "Error updating status: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}
?> 