<?php
require_once '../config/database.php';
require_once '../config/openai.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['messages']) || !isset($data['baseUrl']) || !isset($data['formData'])) {
        throw new Exception('Invalid request data', 400);
    }

    $openai = OpenAI::getInstance();
    
    // Aggiungi un prompt di esempio per aiutare il modello
    $examplePrompt = [
        "role" => "system",
        "content" => "Esempio di trasformazione URL:
        Input URL: https://www.simplebooking.it/ibe/hotelbooking/search?hid=4944&lang=it
        Date: check-in 2024-03-20, check-out 2024-03-25
        Ospiti: 2 adulti, 1 bambino
        Output URL: https://www.simplebooking.it/ibe/hotelbooking/search?hid=4944&lang=it&in=2024-03-20&out=2024-03-25&guests=A,A,C"
    ];

    // Prepara i messaggi per OpenAI
    $messages = array_merge([$examplePrompt], $data['messages']);

    // Chiama OpenAI per generare l'URL
    $response = $openai->generateBookingUrl($messages, $data['baseUrl'], $data['formData']);

    echo json_encode([
        'status' => 'success',
        'bookingUrl' => $response
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 