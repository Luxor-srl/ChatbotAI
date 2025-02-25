<?php
require_once '../config/database.php';

function debug_log($message, $data = null) {
    $debugInfo = date('Y-m-d H:i:s') . " - " . $message . "\n";
    if ($data !== null) {
        $debugInfo .= print_r($data, true) . "\n";
    }
    file_put_contents('debug.txt', $debugInfo, FILE_APPEND);
}

function extractLinks($html, $baseUrl) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR);
    $xpath = new DOMXPath($dom);
    $links = [];
    
    // Trova tutti i link
    $anchors = $xpath->query('//a[@href]');
    foreach ($anchors as $anchor) {
        $href = $anchor->getAttribute('href');
        $absoluteUrl = resolveUrl($baseUrl, $href);
        if ($absoluteUrl && isValidInternalUrl($baseUrl, $absoluteUrl)) {
            // Rimuovi l'ancora e i parametri dall'URL finale
            $absoluteUrl = preg_replace('/[?#].*$/', '', $absoluteUrl);
            $links[] = [
                'url' => $absoluteUrl,
                'title' => $anchor->textContent
            ];
        }
    }
    
    return array_unique($links, SORT_REGULAR);
}

function isValidInternalUrl($baseUrl, $url) {
    // Rimuovi gli spazi e i caratteri non validi dall'URL
    $url = trim($url);
    
    // Lista di schemi da escludere
    $invalidSchemes = ['tel:', 'mailto:', 'javascript:', 'file:', 'ftp:', 'data:', 'sms:'];
    foreach ($invalidSchemes as $scheme) {
        if (stripos($url, $scheme) === 0) {
            return false;
        }
    }

    // Se l'URL è vuoto o inizia con un cancelletto, non è valido
    if (empty($url) || $url === '#' || strpos($url, '#') === 0) {
        return false;
    }

    // Rimuovi i parametri dell'URL e l'ancora se presenti
    $url = preg_replace('/[?#].*$/', '', $url);
    
    try {
        $baseUrlParts = parse_url($baseUrl);
        $urlParts = parse_url($url);

        // Se non riusciamo a fare il parsing dell'URL, non è valido
        if (!$baseUrlParts || !isset($baseUrlParts['host'])) {
            return false;
        }

        // Se l'URL è relativo (non ha host), è valido
        if (!isset($urlParts['host'])) {
            return true;
        }

        // Normalizza gli host per il confronto
        $baseHost = strtolower(preg_replace('/^www\./', '', $baseUrlParts['host']));
        $urlHost = strtolower(preg_replace('/^www\./', '', $urlParts['host']));

        // Verifica che il dominio sia lo stesso
        return $urlHost === $baseHost;
    } catch (Exception $e) {
        return false;
    }
}

function resolveUrl($baseUrl, $href) {
    // Rimuovi gli spazi e i caratteri non validi
    $href = trim($href);
    
    // Se è già un URL completo, verifica che sia dello stesso dominio
    if (filter_var($href, FILTER_VALIDATE_URL)) {
        if (!isValidInternalUrl($baseUrl, $href)) {
            return false;
        }
        return $href;
    }
    
    // Rimuovi l'ancora e i parametri se presenti
    $href = preg_replace('/[?#].*$/', '', $href);
    
    try {
        $baseUrlParts = parse_url($baseUrl);
        
        // Se l'URL base non è valido, ritorna false
        if (!$baseUrlParts || !isset($baseUrlParts['host'])) {
            return false;
        }
        
        // Se inizia con //, aggiungi solo lo schema
        if (strpos($href, '//') === 0) {
            $href = $baseUrlParts['scheme'] . ':' . $href;
            return isValidInternalUrl($baseUrl, $href) ? $href : false;
        }
        
        // Se inizia con /, uniscilo con il dominio base
        if (strpos($href, '/') === 0) {
            return $baseUrlParts['scheme'] . '://' . $baseUrlParts['host'] . $href;
        }
        
        // Gestisci i percorsi relativi
        $basePath = isset($baseUrlParts['path']) ? dirname($baseUrlParts['path']) : '/';
        if ($basePath === '/') {
            $basePath = '';
        }
        
        return $baseUrlParts['scheme'] . '://' . $baseUrlParts['host'] . $basePath . '/' . ltrim($href, '/');
    } catch (Exception $e) {
        return false;
    }
}

function crawlUrl($url, $chatbotId, $depth = 0) {
    $db = Database::getInstance();
    
    try {
        debug_log("Starting to crawl URL: " . $url . " at depth: " . $depth);
        
        // Verifica se l'URL è già stato crawlato
        $existing = $db->fetch(
            "SELECT id FROM discovered_urls WHERE chatbot_id = ? AND url = ?",
            [$chatbotId, $url]
        );
        
        if ($existing) {
            debug_log("URL already crawled: " . $url);
            return;
        }
        
        // Inizializza cURL
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
        
        if (curl_errno($ch)) {
            throw new Exception("Curl Error for URL {$url}: " . curl_error($ch));
        }
        
        // Estrai il titolo della pagina
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        $titleNode = $dom->getElementsByTagName('title')->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : '';
        
        debug_log("Found page title: " . $title . " for URL: " . $url);
        
        // Salva l'URL scoperto
        try {
            $db->insert('discovered_urls', [
                'chatbot_id' => $chatbotId,
                'url' => $url,
                'title' => $title,
                'depth' => $depth
            ]);
            debug_log("Saved URL to database: " . $url);
        } catch (Exception $e) {
            // Ignora errori di duplicati
            if (!strpos($e->getMessage(), 'Duplicate entry')) {
                throw $e;
            }
            debug_log("URL already exists in database: " . $url);
        }
        
        // Recupera i limiti del chatbot
        $chatbot = $db->fetch(
            "SELECT scraping_depth FROM chatbots WHERE id = ?",
            [$chatbotId]
        );
        
        // Se non abbiamo raggiunto la profondità massima, continua il crawling
        if ($depth < $chatbot['scraping_depth']) {
            $links = extractLinks($html, $url);
            debug_log("Found " . count($links) . " links on page: " . $url);
            
            foreach ($links as $link) {
                debug_log("Processing link: " . $link['url'] . " with title: " . $link['title']);
                crawlUrl($link['url'], $chatbotId, $depth + 1);
            }
        } else {
            debug_log("Reached maximum depth for URL: " . $url);
        }
        
    } catch (Exception $e) {
        debug_log("Error crawling URL: " . $url, $e->getMessage());
        // Non interrompere il processo per un singolo errore
    }
}

// Processa la coda di crawling
try {
    $db = Database::getInstance();
    debug_log("Starting crawling process");
    
    // Recupera i chatbot in stato 'crawling'
    $chatbots = $db->fetchAll(
        "SELECT * FROM chatbots WHERE status = 'crawling'"
    );
    
    debug_log("Found " . count($chatbots) . " chatbots to crawl");
    
    foreach ($chatbots as $chatbot) {
        debug_log("Starting crawl for chatbot: " . $chatbot['id'] . " - " . $chatbot['name']);
        debug_log("Website URL: " . $chatbot['website_url']);
        
        // Inizia il crawling dall'URL del sito
        crawlUrl($chatbot['website_url'], $chatbot['id'], 0);
        
        // Verifica quante URL sono state trovate
        $urlCount = $db->fetch(
            "SELECT COUNT(*) as count FROM discovered_urls WHERE chatbot_id = ?",
            [$chatbot['id']]
        );
        
        debug_log("Found " . $urlCount['count'] . " URLs for chatbot: " . $chatbot['id']);
        
        if ($urlCount['count'] > 0) {
            // Aggiorna lo stato del chatbot
            $db->query(
                "UPDATE chatbots SET status = 'url_selection', updated_at = NOW() WHERE id = ?",
                [$chatbot['id']]
            );
            debug_log("Updated chatbot status to url_selection");
        } else {
            // Se non sono state trovate URL, imposta lo stato su error
            $db->query(
                "UPDATE chatbots SET status = 'error', updated_at = NOW() WHERE id = ?",
                [$chatbot['id']]
            );
            debug_log("No URLs found, updated chatbot status to error");
        }
        
        debug_log("Crawling completed for chatbot: " . $chatbot['id']);
    }
    
} catch (Exception $e) {
    debug_log("Error in crawling process:", $e->getMessage());
    debug_log("Stack trace:", $e->getTraceAsString());
    
    // In caso di errore, aggiorna lo stato del chatbot
    if (isset($chatbot['id'])) {
        try {
            $db->query(
                "UPDATE chatbots SET status = 'error', updated_at = NOW() WHERE id = ?",
                [$chatbot['id']]
            );
            debug_log("Updated chatbot status to error due to exception");
        } catch (Exception $updateError) {
            debug_log("Error updating chatbot status:", $updateError->getMessage());
        }
    }
} 