<?php
require_once '../config/database.php';
require_once '../config/openai.php';

function debug_log($message, $data = null) {
    $debugInfo = date('Y-m-d H:i:s') . " - " . $message . "\n";
    if ($data !== null) {
        $debugInfo .= print_r($data, true) . "\n";
    }
    file_put_contents('debug.txt', $debugInfo, FILE_APPEND);
}

// Gestione CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Max-Age: 1728000');

try {
    // Gestione della richiesta OPTIONS (preflight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('HTTP/1.1 204 No Content');
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    // Leggi il corpo della richiesta JSON
    $jsonInput = file_get_contents('php://input');
    debug_log("Received request:", $jsonInput);
    
    $data = json_decode($jsonInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg(), 400);
    }

    if (!isset($data['chatbotId']) || !isset($data['message'])) {
        throw new Exception('Missing required fields', 400);
    }

    $chatbotId = $data['chatbotId'];
    $userMessage = $data['message'];
    $tone = $data['tone'] ?? 'professionale'; // Usa il tono dalla richiesta o default a 'professionale'

    debug_log("Processing request for chatbot:", $chatbotId);
    debug_log("Using tone:", $tone);

    // Inizializza il database
    $db = Database::getInstance();

    // Verifica se il chatbot esiste
    $chatbot = $db->fetch(
        "SELECT id, name, website_url, tone_of_voice FROM chatbots WHERE id = ?",
        [$chatbotId]
    );
    
    debug_log("Chatbot check response:", $chatbot);

    if (!$chatbot) {
        throw new Exception('Chatbot not found', 404);
    }

    // Recupera la cronologia delle conversazioni
    $conversations = $db->fetchAll(
        "SELECT user_message, bot_response 
         FROM conversations 
         WHERE chatbot_id = ? 
         ORDER BY timestamp DESC 
         LIMIT 5",
        [$chatbotId]
    );
    
    debug_log("Conversation history:", $conversations);

    // Recupera il contenuto delle pagine
    $pages = $db->fetchAll(
        "SELECT url, content 
         FROM scraped_pages 
         WHERE chatbot_id = ? 
         ORDER BY scraped_at DESC",
        [$chatbotId]
    );
    
    debug_log("Pages response:", $pages);

    // Prepara il contesto
    $context = [];
    foreach ($pages as $page) {
        try {
            // Gestisci il contenuto
            if (is_string($page['content'])) {
                $content = json_decode($page['content'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($content)) {
                    foreach ($content as $text) {
                        if (!empty($text) && is_string($text)) {
                            $context[] = $text;
                        }
                    }
                } else {
                    $context[] = $page['content'];
                }
            }
        } catch (Exception $e) {
            debug_log("Error processing page:", $e->getMessage());
            continue;
        }
    }

    debug_log("Prepared context count:", count($context));

    // Genera la risposta
    $openai = OpenAI::getInstance();
    $botResponse = $openai->generateResponse($userMessage, $context, $conversations, $tone);
    debug_log("Generated response:", $botResponse);

    // Salva la conversazione
    $conversationId = $db->insert('conversations', [
        'chatbot_id' => $chatbotId,
        'user_message' => $userMessage,
        'bot_response' => $botResponse,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    debug_log("Conversation saved with ID:", $conversationId);

    echo json_encode([
        'response' => $botResponse,
        'status' => 'success'
    ]);

} catch (Exception $e) {
    $statusCode = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
    http_response_code($statusCode);
    
    debug_log("Error occurred:", [
        'message' => $e->getMessage(),
        'code' => $statusCode,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    echo json_encode([
        'error' => $e->getMessage(),
        'status' => 'error'
    ]);
}
?> 