<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['chatbot_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing chatbot_id parameter']);
    exit;
}

try {
    $chatbotId = $_GET['chatbot_id'];
    $db = Database::getInstance();

    // Recupera lo stato del chatbot
    $chatbot = $db->fetch(
        "SELECT status FROM chatbots WHERE id = ?",
        [$chatbotId]
    );

    if (!$chatbot) {
        http_response_code(404);
        echo json_encode(['error' => 'Chatbot not found']);
        exit;
    }

    echo json_encode([
        'status' => $chatbot['status']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 