<?php
// Add debug logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
file_put_contents('scraping_debug.log', "Script started at: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Test PHP environment
file_put_contents('scraping_debug.log', "PHP version: " . PHP_VERSION . "\n", FILE_APPEND);
file_put_contents('scraping_debug.log', "Current working directory: " . getcwd() . "\n", FILE_APPEND);

// Usa il percorso assoluto per includere database.php
$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/database.php';

function scrapePage($url, $chatbotId, $depth = 0) {
    $db = Database::getInstance();
    
    try {
        // Verifica il limite di pagine
        $chatbot = $db->fetch("SELECT scraping_depth, max_pages FROM chatbots WHERE id = ?", [$chatbotId]);
        $scrapedCount = $db->fetch(
            "SELECT COUNT(*) as count FROM scraped_pages WHERE chatbot_id = ?", 
            [$chatbotId]
        );
        
        if ($scrapedCount['count'] >= $chatbot['max_pages']) {
            // Aggiorna gli elementi rimanenti in coda
            $db->query(
                "UPDATE scraping_queue 
                 SET status = 'completed', 
                     updated_at = NOW(), 
                     error_message = 'Skipped: page limit reached'
                 WHERE chatbot_id = ? AND status IN ('pending', 'processing')",
                [$chatbotId]
            );
            
            // Aggiorna lo stato del chatbot
            $db->query(
                "UPDATE chatbots SET status = 'completed', updated_at = NOW() WHERE id = ?",
                [$chatbotId]
            );
            
            return;
        }

        // Scraping della pagina
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ]);
        
        $html = curl_exec($ch);
        if (curl_errno($ch)) throw new Exception(curl_error($ch));
        
        // Parsing del contenuto
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        
        // Pulizia del contenuto
        foreach (['script', 'style', 'iframe', 'link', 'meta'] as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            while ($elements->length > 0) {
                $elements->item(0)->parentNode->removeChild($elements->item(0));
            }
        }
        
        // Estrazione del contenuto
        $xpath = new DOMXPath($dom);
        $contentNodes = $xpath->query('//div[contains(@class, "content") or contains(@class, "main") or contains(@id, "content") or contains(@id, "main")]|//section|//article|//p[string-length(normalize-space()) > 50]');
        
        $pageContent = [];
        foreach ($contentNodes as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if (strlen($text) > 50) $pageContent[] = $text;
        }
        
        // Salvataggio del contenuto
        if (!empty($pageContent)) {
            $db->insert('scraped_pages', [
                'chatbot_id' => $chatbotId,
                'url' => $url,
                'content' => json_encode(array_values(array_unique($pageContent)))
            ]);
        }
        
        // Aggiornamento stato
        $db->query(
            "UPDATE scraping_queue SET status = 'completed', updated_at = NOW() WHERE chatbot_id = ? AND url = ?",
            [$chatbotId, $url]
        );
        
    } catch (Exception $e) {
        $db->query(
            "UPDATE scraping_queue SET status = 'error', error_message = ?, updated_at = NOW() WHERE chatbot_id = ? AND url = ?",
            [$e->getMessage(), $chatbotId, $url]
        );
    }
}

// Processo principale
try {
    $db = Database::getInstance();
    set_time_limit(0);
    chdir(__DIR__);
    
    while (true) {
        $queueItem = $db->fetch(
            "SELECT sq.*, c.max_pages, c.website_url 
             FROM scraping_queue sq 
             JOIN chatbots c ON sq.chatbot_id = c.id 
             WHERE sq.status = 'pending' 
             ORDER BY sq.created_at ASC 
             LIMIT 1"
        );
        
        if (!$queueItem) break;
        
        // Aggiorna stato a processing
        $db->query(
            "UPDATE scraping_queue SET status = 'processing', updated_at = NOW() WHERE id = ?",
            [$queueItem['id']]
        );
        
        // Esegui scraping
        scrapePage($queueItem['url'], $queueItem['chatbot_id'], $queueItem['depth']);
        
        // Verifica se ci sono altre pagine da processare
        $pendingCount = $db->fetch(
            "SELECT COUNT(*) as count FROM scraping_queue WHERE chatbot_id = ? AND status = 'pending'",
            [$queueItem['chatbot_id']]
        );
        
        if ($pendingCount['count'] == 0) {
            $db->query(
                "UPDATE chatbots SET status = 'completed', updated_at = NOW() WHERE id = ?",
                [$queueItem['chatbot_id']]
            );
        }
        
        sleep(1);
    }
    
} catch (Exception $e) {
    if (isset($queueItem['chatbot_id'])) {
        $db->query(
            "UPDATE chatbots SET status = 'error', updated_at = NOW() WHERE id = ?",
            [$queueItem['chatbot_id']]
        );
    }
}