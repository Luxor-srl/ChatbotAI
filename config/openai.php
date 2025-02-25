<?php
define('OPENAI_API_KEY', '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');

class OpenAI {
    private static $instance = null;
    private $api_key;
    private $model = "gpt-4o-mini";
    private $maxContextTokens = 4000;
    private $system_prompt;
    private $tone_prompts = [
        'professionale' => "Sei un assistente virtuale professionale ed esperto. Fornisci risposte chiare, precise e basate sui fatti, mantenendo sempre un tono competente e affidabile. Usa un linguaggio tecnico quando appropriato, ma assicurati che sia comprensibile.",
        'amichevole' => "Sei un assistente virtuale cordiale e accogliente. Usa un tono caldo e amichevole, come se stessi parlando con un amico. Sii empatico e comprensivo, rendendo la conversazione piacevole e naturale.",
        'informale' => "Sei un assistente virtuale rilassato e alla mano. Usa un linguaggio quotidiano e informale, come in una chiacchierata tra conoscenti. Sii spontaneo ma sempre rispettoso, rendendo la conversazione leggera e accessibile.",
        'formale' => "Sei un assistente virtuale formale e professionale. Usa un linguaggio rispettoso e ricercato, mantenendo sempre la massima cortesia. Sii preciso e dettagliato nelle tue risposte, dimostrando competenza e autorevolezza.",
        'entusiasta' => "Sei un assistente virtuale energico e positivo. Mostra entusiasmo nelle tue risposte, usando un tono vivace e incoraggiante. Sii ottimista e motivante, trasmettendo energia positiva nella conversazione."
    ];

    private function __construct() {
        $this->api_key = OPENAI_API_KEY;
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new OpenAI();
        }
        return self::$instance;
    }

    private function getSystemPrompt($tone = 'professionale') {
        $basePrompt = $this->tone_prompts[$tone] ?? $this->tone_prompts['professionale'];
        return $basePrompt . "\n\nLinee guida aggiuntive:\n" .
            "1. Analizza attentamente il contesto fornito\n" .
            "2. Trova le informazioni più pertinenti per la domanda dell'utente\n" .
            "3. Fornisci risposte concise e ben organizzate\n" .
            "4. Se non conosci qualcosa, ammettilo onestamente\n" .
            "5. Se l'informazione è lunga, sintetizzala in modo chiaro";
    }

    private function prepareContext($context) {
        if (empty($context)) return '';

        $formattedContext = "CONTENUTO RILEVANTE:\n\n";
        foreach ($context as $text) {
            if (!empty($text)) {
                $formattedContext .= $text . "\n\n---\n\n";
            }
        }
        return $formattedContext;
    }

    public function generateResponse($userMessage, $context = [], $conversations = [], $tone = 'professionale') {
        $messages = [
            ["role" => "system", "content" => $this->getSystemPrompt($tone)]
        ];

        if (!empty($context)) {
            $contextMessage = $this->prepareContext($context);
            $messages[] = ["role" => "system", "content" => $contextMessage];
        }

        foreach ($conversations as $conv) {
            $messages[] = ["role" => "user", "content" => $conv['user_message']];
            $messages[] = ["role" => "assistant", "content" => $conv['bot_response']];
        }

        $messages[] = ["role" => "user", "content" => $userMessage];

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->api_key
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                    'presence_penalty' => 0.6,
                    'frequency_penalty' => 0.5
                ])
            ]);
            
            $response = curl_exec($ch);
            if (curl_errno($ch)) throw new Exception(curl_error($ch));
            curl_close($ch);
            
            $responseData = json_decode($response, true);
            if (isset($responseData['choices'][0]['message']['content'])) {
                return $responseData['choices'][0]['message']['content'];
            }
            
            throw new Exception('Invalid response format');
            
        } catch (Exception $e) {
            return 'Mi dispiace, al momento non riesco a rispondere. Potresti riprovare tra qualche istante?';
        }
    }

    public function generateBookingUrl($messages, $baseUrl, $formData) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->api_key
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.3, // Temperatura più bassa per risposte più precise
                    'max_tokens' => 150,
                    'presence_penalty' => 0,
                    'frequency_penalty' => 0
                ])
            ]);
            
            $response = curl_exec($ch);
            if (curl_errno($ch)) throw new Exception(curl_error($ch));
            curl_close($ch);
            
            $responseData = json_decode($response, true);
            if (isset($responseData['choices'][0]['message']['content'])) {
                $generatedUrl = trim($responseData['choices'][0]['message']['content']);
                
                // Verifica che l'URL generato sia valido
                if (filter_var($generatedUrl, FILTER_VALIDATE_URL)) {
                    return $generatedUrl;
                }
                
                // Se l'URL non è valido, ritorna l'URL base
                return $baseUrl;
            }
            
            throw new Exception('Invalid response format');
            
        } catch (Exception $e) {
            error_log("Error generating booking URL: " . $e->getMessage());
            return $baseUrl;
        }
    }
}
?> 