<?php
require_once 'config/database.php';

// Verifica che l'ID del chatbot sia stato fornito
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$chatbotId = $_GET['id'];
$db = Database::getInstance();

// Gestione delle azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_chatbot':
                // Elimina il chatbot e tutti i dati correlati
                $db->query("DELETE FROM chatbots WHERE id = ?", [$chatbotId]);
                header('Location: index.php');
                exit;
                
            case 'clear_queue':
                // Cancella la coda di scraping
                $db->query(
                    "DELETE FROM scraping_queue WHERE chatbot_id = ? AND status = 'pending'",
                    [$chatbotId]
                );
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $chatbotId);
                exit;
                
            case 'delete_page':
                if (isset($_POST['page_id'])) {
                    $db->query(
                        "DELETE FROM scraped_pages WHERE id = ? AND chatbot_id = ?",
                        [$_POST['page_id'], $chatbotId]
                    );
                }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $chatbotId);
                exit;
            
            case 'add_question':
                if (isset($_POST['question']) && !empty($_POST['question'])) {
                    // Verifica se ci sono già 3 domande
                    $questionCount = $db->fetch(
                        "SELECT COUNT(*) as count FROM custom_questions WHERE chatbot_id = ?",
                        [$chatbotId]
                    );
                    
                    if ($questionCount['count'] < 3) {
                        $db->insert('custom_questions', [
                            'chatbot_id' => $chatbotId,
                            'question' => $_POST['question']
                        ]);
                    }
                }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $chatbotId);
                exit;
                
            case 'delete_question':
                if (isset($_POST['question_id'])) {
                    $db->query(
                        "DELETE FROM custom_questions WHERE id = ? AND chatbot_id = ?",
                        [$_POST['question_id'], $chatbotId]
                    );
                }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $chatbotId);
                exit;
            
            case 'update_tone':
                if (isset($_POST['tone_of_voice'])) {
                    $db->query(
                        "UPDATE chatbots SET tone_of_voice = ? WHERE id = ?",
                        [$_POST['tone_of_voice'], $chatbotId]
                    );
                }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $chatbotId);
                exit;
            
            case 'update_palette':
                if (isset($_POST['color_palette'])) {
                    $db->query(
                        "UPDATE chatbots SET color_palette = ? WHERE id = ?",
                        [$_POST['color_palette'], $chatbotId]
                    );
                }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $chatbotId);
                exit;
            
            case 'update_logo':
                if (isset($_FILES['logo'])) {
                    $logo = $_FILES['logo'];
                    if ($logo['error'] === UPLOAD_ERR_OK) {
                        // Verifica dimensione massima (2MB)
                        if ($logo['size'] > 2 * 1024 * 1024) {
                            $_SESSION['error'] = 'Il file è troppo grande. Dimensione massima: 2MB';
                            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $chatbotId);
                            exit;
                        }

                        // Verifica tipo di file
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!in_array($logo['type'], $allowedTypes)) {
                            $_SESSION['error'] = 'Tipo di file non supportato. Usa PNG, JPG o GIF';
                            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $chatbotId);
                            exit;
                        }

                        // Crea directory per i logo se non esiste
                        $uploadDir = 'uploads/logos/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        // Genera nome file unico
                        $extension = pathinfo($logo['name'], PATHINFO_EXTENSION);
                        $filename = uniqid('logo_') . '.' . $extension;
                        $uploadPath = $uploadDir . $filename;

                        // Sposta il file caricato
                        if (move_uploaded_file($logo['tmp_name'], $uploadPath)) {
                            // Salva il percorso nel database
                            $logoUrl = $uploadPath;
                            $db->query(
                                "UPDATE chatbots SET logo_url = ? WHERE id = ?",
                                [$logoUrl, $chatbotId]
                            );
                        }
                    }
                }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $chatbotId);
                exit;
            
            case 'remove_logo':
                $db->query(
                    "UPDATE chatbots SET logo_url = NULL WHERE id = ?",
                    [$chatbotId]
                );
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $chatbotId);
                exit;
            
            case 'update_booking':
                if (isset($_POST['booking_url'])) {
                    $db->query(
                        "UPDATE chatbots SET booking_online_url = ? WHERE id = ?",
                        [$_POST['booking_url'], $chatbotId]
                    );
                }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $chatbotId);
                exit;
        }
    }
}

// Recupera i dettagli del chatbot
$chatbot = $db->fetch(
    "SELECT * FROM chatbots WHERE id = ?",
    [$chatbotId]
);

if (!$chatbot) {
    header('Location: index.php');
    exit;
}

// Recupera le pagine scrapate
$pages = $db->fetchAll(
    "SELECT * FROM scraped_pages WHERE chatbot_id = ? ORDER BY scraped_at DESC",
    [$chatbotId]
);

// Recupera le ultime conversazioni
$conversations = $db->fetchAll(
    "SELECT * FROM conversations WHERE chatbot_id = ? ORDER BY timestamp DESC LIMIT 10",
    [$chatbotId]
);

// Recupera le domande personalizzate
$customQuestions = $db->fetchAll(
    "SELECT * FROM custom_questions WHERE chatbot_id = ? ORDER BY created_at DESC",
    [$chatbotId]
);
?>
<!DOCTYPE html>
<html lang="it" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettagli Chatbot - <?php echo htmlspecialchars($chatbot['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="h-full">
    <div class="min-h-full">
        <!-- Navigation -->
        <nav class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex">
                        <div class="flex flex-shrink-0 items-center">
                            <img class="h-8 w-auto" src="https://www.spottywifi.it/wp-content/uploads/2025/02/hotspot-wifi-hotel-spotty-2.png" alt="Logo">
                        </div>
                        <div class="ml-6 flex items-center space-x-8">
                            <a href="index.php" class="text-gray-900 inline-flex items-center px-1 pt-1 text-sm font-medium">Dashboard</a>
                            <span class="text-gray-900 inline-flex items-center px-1 pt-1 text-sm font-medium">
                                <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                <?php echo htmlspecialchars($chatbot['name']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page header -->
        <div class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-2xl font-semibold text-gray-900 flex items-center gap-x-3">
                            <?php echo htmlspecialchars($chatbot['name']); ?>
                            <?php
                            $statusClass = match($chatbot['status']) {
                                'created' => 'text-yellow-700 bg-yellow-50 ring-yellow-600/20',
                                'crawling' => 'text-blue-700 bg-blue-50 ring-blue-600/20',
                                'url_selection' => 'text-purple-700 bg-purple-50 ring-purple-600/20',
                                'scraping' => 'text-orange-700 bg-orange-50 ring-orange-600/20',
                                'completed' => 'text-green-700 bg-green-50 ring-green-600/20',
                                'error' => 'text-red-700 bg-red-50 ring-red-600/20',
                                default => 'text-gray-700 bg-gray-50 ring-gray-600/20'
                            };
                            $statusText = match($chatbot['status']) {
                                'created' => 'Nuovo',
                                'crawling' => 'Analisi',
                                'url_selection' => 'Selezione',
                                'scraping' => 'Scraping',
                                'completed' => 'Completato',
                                'error' => 'Errore',
                                default => 'Sconosciuto'
                            };
                            ?>
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset <?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            <?php echo htmlspecialchars($chatbot['website_url']); ?>
                        </p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <form method="POST" class="inline" onsubmit="return confirm('Sei sicuro di voler eliminare questo chatbot? Questa azione non può essere annullata.');">
                            <input type="hidden" name="action" value="delete_chatbot">
                            <button type="submit" class="inline-flex items-center gap-x-2 rounded-md bg-red-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                                <i class="fas fa-trash -ml-0.5 h-5 w-5"></i>
                                Elimina Chatbot
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div class="space-y-6">
                    <!-- Codice di integrazione -->
                    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg">
                        <div class="px-4 py-6 sm:px-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-semibold leading-7 text-gray-900">Codice di integrazione</h2>
                                <button onclick="copyIntegrationCode()" class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    <i class="fas fa-copy -ml-0.5 h-5 w-5"></i>
                                    Copia codice
                                </button>
                            </div>
                            <div class="mt-4">
                                <div class="relative">
                                    <pre class="mt-2 p-4 bg-gray-900 text-gray-100 rounded-lg overflow-x-auto text-sm font-mono"><code>&lt;script src="<?php echo htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']); ?>/public/js/chatbot-widget.js"&gt;&lt;/script&gt;
&lt;script&gt;
    window.initChatbot('<?php echo htmlspecialchars($chatbot['id']); ?>', 
        <?php echo json_encode(array_column($customQuestions, 'question')); ?>, 
        '<?php echo htmlspecialchars($chatbot['tone_of_voice']); ?>', 
        '<?php echo htmlspecialchars($chatbot['color_palette']); ?>',
        <?php echo !empty($chatbot['logo_url']) ? "'" . htmlspecialchars($chatbot['logo_url']) . "'" : "null"; ?>,
        <?php echo !empty($chatbot['booking_online_url']) ? "'" . htmlspecialchars($chatbot['booking_online_url']) . "'" : "null"; ?>
    );
&lt;/script&gt;</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Logo del chatbot -->
                    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg">
                        <div class="px-4 py-6 sm:px-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-semibold leading-7 text-gray-900">Logo del chatbot</h2>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Carica un logo personalizzato che verrà mostrato nell'header del chatbot.
                            </p>
                            
                            <div class="mt-6">
                                <?php if (!empty($chatbot['logo_url'])): ?>
                                <div class="mb-6">
                                    <div class="flex items-center gap-x-4">
                                        <img src="<?php echo htmlspecialchars($chatbot['logo_url']); ?>" 
                                             alt="Logo corrente" 
                                             class="h-12 w-auto object-contain rounded-lg border border-gray-200">
                                        <form method="POST" class="inline-flex" onsubmit="return confirm('Sei sicuro di voler rimuovere il logo?');">
                                            <input type="hidden" name="action" value="remove_logo">
                                            <button type="submit" 
                                                class="inline-flex items-center gap-x-2 rounded-md bg-red-50 px-3 py-2 text-sm font-semibold text-red-600 shadow-sm hover:bg-red-100">
                                                <i class="fas fa-trash"></i>
                                                Rimuovi logo
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php else: ?>
                                <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-y-4">
                                    <input type="hidden" name="action" value="update_logo">
                                    
                                    <div class="flex items-center justify-center w-full">
                                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                <i class="fas fa-cloud-upload-alt mb-3 text-2xl text-gray-400"></i>
                                                <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Clicca per caricare</span> o trascina il file qui</p>
                                                <p class="text-xs text-gray-500">PNG, JPG o GIF (max. 2MB)</p>
                                            </div>
                                            <input type="file" name="logo" id="logoInput" class="hidden" accept="image/*" required onchange="previewLogo(this);" />
                                        </label>
                                    </div>

                                    <div id="logoPreview" class="hidden mb-4">
                                        <div class="flex items-center gap-x-4">
                                            <img id="previewImage" src="" alt="Anteprima logo" class="h-12 w-auto object-contain rounded-lg border border-gray-200">
                                            <button type="button" onclick="removeLogo();" 
                                                class="inline-flex items-center gap-x-2 rounded-md bg-red-50 px-3 py-2 text-sm font-semibold text-red-600 shadow-sm hover:bg-red-100">
                                                <i class="fas fa-times"></i>
                                                Rimuovi
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" id="uploadButton" 
                                            class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                            <i class="fas fa-save -ml-0.5 h-5 w-5"></i>
                                            Carica logo
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>

                                <script>
                                function previewLogo(input) {
                                    if (input.files && input.files[0]) {
                                        const reader = new FileReader();
                                        reader.onload = function(e) {
                                            document.getElementById('previewImage').src = e.target.result;
                                            document.getElementById('logoPreview').classList.remove('hidden');
                                            input.parentElement.classList.add('hidden');
                                        };
                                        reader.readAsDataURL(input.files[0]);
                                    }
                                }

                                function removeLogo() {
                                    document.getElementById('logoInput').value = '';
                                    document.getElementById('logoPreview').classList.add('hidden');
                                    document.getElementById('logoInput').parentElement.classList.remove('hidden');
                                }
                                </script>
                            </div>
                        </div>
                    </div>

                    <!-- Domande personalizzate -->
                    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg">
                        <div class="px-4 py-6 sm:px-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-semibold leading-7 text-gray-900">Domande personalizzate</h2>
                                <?php if (count($customQuestions) < 3): ?>
                                <button onclick="showAddQuestionForm()" class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    <i class="fas fa-plus -ml-0.5 h-5 w-5"></i>
                                    Aggiungi domanda
                                </button>
                                <?php endif; ?>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Aggiungi fino a 3 domande che verranno mostrate all'apertura del chatbot.
                            </p>
                            <div class="mt-4">
                                <?php if (empty($customQuestions)): ?>
                                    <p class="text-sm text-gray-500">Nessuna domanda personalizzata configurata.</p>
                                <?php else: ?>
                                    <ul class="divide-y divide-gray-100">
                                        <?php foreach ($customQuestions as $question): ?>
                                            <li class="flex items-center justify-between py-4">
                                                <div class="flex items-center gap-x-3">
                                                    <div class="flex-none rounded-full bg-indigo-50 p-2">
                                                        <i class="fas fa-question h-5 w-5 text-indigo-600"></i>
                                                    </div>
                                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($question['question']); ?></p>
                                                </div>
                                                <form method="POST" class="inline" onsubmit="return confirm('Sei sicuro di voler eliminare questa domanda?');">
                                                    <input type="hidden" name="action" value="delete_question">
                                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Modal per aggiungere domanda -->
                    <div id="addQuestionModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50">
                        <div class="fixed inset-0 z-10 overflow-y-auto">
                            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="add_question">
                                        <div>
                                            <div class="mt-3 text-center sm:mt-5">
                                                <h3 class="text-base font-semibold leading-6 text-gray-900">
                                                    Aggiungi domanda personalizzata
                                                </h3>
                                                <div class="mt-2">
                                                    <textarea name="question" rows="3" required
                                                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                                        placeholder="Inserisci la domanda..."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                                            <button type="submit"
                                                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2">
                                                Aggiungi
                                            </button>
                                            <button type="button" onclick="hideAddQuestionForm()"
                                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0">
                                                Annulla
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    function showAddQuestionForm() {
                        document.getElementById('addQuestionModal').classList.remove('hidden');
                    }

                    function hideAddQuestionForm() {
                        document.getElementById('addQuestionModal').classList.add('hidden');
                    }
                    </script>

                    <!-- Aggiorna il codice di integrazione per includere le domande personalizzate -->
                    <script>
                    function copyIntegrationCode() {
                        const questions = <?php echo json_encode(array_column($customQuestions, 'question')); ?>;
                        const code = `<script src="<?php echo htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']); ?>/public/js/chatbot-widget.js"><\/script>
<script>
    window.initChatbot('<?php echo htmlspecialchars($chatbot['id']); ?>', 
        ${JSON.stringify(questions)}, 
        '<?php echo htmlspecialchars($chatbot['tone_of_voice']); ?>', 
        '<?php echo htmlspecialchars($chatbot['color_palette']); ?>',
        <?php echo !empty($chatbot['logo_url']) ? "'" . htmlspecialchars($chatbot['logo_url']) . "'" : "null"; ?>,
        <?php echo !empty($chatbot['booking_online_url']) ? "'" . htmlspecialchars($chatbot['booking_online_url']) . "'" : "null"; ?>
    );
<\/script>`;
                        
                        navigator.clipboard.writeText(code).then(() => {
                            alert('Codice di integrazione copiato negli appunti!');
                        }).catch(err => {
                            console.error('Errore durante la copia del codice:', err);
                            alert('Errore durante la copia del codice. Per favore, prova di nuovo.');
                        });
                    }
                    </script>

                    <!-- Tono di voce -->
                    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg">
                        <div class="px-4 py-6 sm:px-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-semibold leading-7 text-gray-900">Tono di voce</h2>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Seleziona il tono di voce che il chatbot userà nelle sue risposte.
                            </p>
                            <form method="POST" class="mt-6">
                                <input type="hidden" name="action" value="update_tone">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                    <?php
                                    $tones = [
                                        'professionale' => [
                                            'icon' => 'fa-briefcase',
                                            'description' => 'Chiaro e competente'
                                        ],
                                        'amichevole' => [
                                            'icon' => 'fa-smile',
                                            'description' => 'Cordiale e accogliente'
                                        ],
                                        'informale' => [
                                            'icon' => 'fa-coffee',
                                            'description' => 'Rilassato e alla mano'
                                        ],
                                        'formale' => [
                                            'icon' => 'fa-user-tie',
                                            'description' => 'Serio e rispettoso'
                                        ],
                                        'entusiasta' => [
                                            'icon' => 'fa-star',
                                            'description' => 'Energico e positivo'
                                        ]
                                    ];
                                    
                                    foreach ($tones as $tone => $details): ?>
                                        <div class="relative">
                                            <input type="radio" name="tone_of_voice" id="tone_<?php echo $tone; ?>" 
                                                value="<?php echo $tone; ?>" class="peer hidden" 
                                                <?php echo $chatbot['tone_of_voice'] === $tone ? 'checked' : ''; ?>>
                                            <label for="tone_<?php echo $tone; ?>" 
                                                class="flex flex-col items-center gap-2 p-4 text-sm rounded-lg border cursor-pointer
                                                    peer-checked:border-indigo-600 peer-checked:ring-1 peer-checked:ring-indigo-600
                                                    hover:bg-gray-50 transition-all">
                                                <i class="fas <?php echo $details['icon']; ?> text-2xl text-gray-400 peer-checked:text-indigo-600"></i>
                                                <div class="font-medium text-gray-900 capitalize"><?php echo $tone; ?></div>
                                                <div class="text-xs text-gray-500"><?php echo $details['description']; ?></div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-6 flex justify-end">
                                    <button type="submit" 
                                        class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                        <i class="fas fa-save -ml-0.5 h-5 w-5"></i>
                                        Salva preferenze
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Palette colori -->
                    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg">
                        <div class="px-4 py-6 sm:px-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-semibold leading-7 text-gray-900">Palette colori</h2>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Seleziona la palette di colori per personalizzare l'aspetto del chatbot.
                            </p>
                            <form method="POST" class="mt-6">
                                <input type="hidden" name="action" value="update_palette">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                    <?php
                                    $palettes = [
                                        'indigo' => [
                                            'icon' => 'fa-palette',
                                            'preview' => '#4F46E5',
                                            'description' => 'Classico ed elegante'
                                        ],
                                        'emerald' => [
                                            'icon' => 'fa-palette',
                                            'preview' => '#059669',
                                            'description' => 'Naturale e rilassante'
                                        ],
                                        'rose' => [
                                            'icon' => 'fa-palette',
                                            'preview' => '#E11D48',
                                            'description' => 'Vivace e accogliente'
                                        ],
                                        'amber' => [
                                            'icon' => 'fa-palette',
                                            'preview' => '#D97706',
                                            'description' => 'Caldo e solare'
                                        ],
                                        'sky' => [
                                            'icon' => 'fa-palette',
                                            'preview' => '#0284C7',
                                            'description' => 'Fresco e professionale'
                                        ]
                                    ];
                                    
                                    foreach ($palettes as $palette => $details): 
                                        $paletteNames = [
                                            'indigo' => 'Indaco',
                                            'emerald' => 'Smeraldo', 
                                            'rose' => 'Rosa',
                                            'amber' => 'Ambra',
                                            'sky' => 'Celeste'
                                        ];
                                        ?>
                                        <div class="relative">
                                            <input type="radio" name="color_palette" id="palette_<?php echo $palette; ?>" 
                                                value="<?php echo $palette; ?>" class="peer hidden" 
                                                <?php echo $chatbot['color_palette'] === $palette ? 'checked' : ''; ?>>
                                            <label for="palette_<?php echo $palette; ?>" 
                                                class="flex flex-col items-center gap-2 p-4 text-sm rounded-lg border cursor-pointer
                                                    peer-checked:border-<?php echo $palette; ?>-600 peer-checked:ring-1 peer-checked:ring-<?php echo $palette; ?>-600
                                                    hover:bg-gray-50 transition-all">
                                                <div class="w-8 h-8 rounded-full" style="background-color: <?php echo $details['preview']; ?>"></div>
                                                <div class="font-medium text-gray-900"><?php echo $paletteNames[$palette]; ?></div>
                                                <div class="text-xs text-gray-500"><?php echo $details['description']; ?></div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-6 flex justify-end">
                                    <button type="submit" 
                                        class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                        <i class="fas fa-save -ml-0.5 h-5 w-5"></i>
                                        Salva preferenze
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Configurazione Booking Online -->
                    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg">
                        <div class="px-4 py-6 sm:px-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-semibold leading-7 text-gray-900">Configurazione Booking Online</h2>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Configura l'URL del sistema di prenotazione online per permettere agli utenti di prenotare direttamente dal chatbot.
                            </p>
                            
                            <form method="POST" class="mt-6">
                                <input type="hidden" name="action" value="update_booking">
                                
                                <div class="space-y-4">
                                    <div>
                                        <label for="booking_url" class="block text-sm font-medium leading-6 text-gray-900">
                                            URL Booking Online
                                        </label>
                                        <div class="mt-2">
                                            <input type="url" name="booking_url" id="booking_url"
                                                value="<?php echo htmlspecialchars($chatbot['booking_online_url'] ?? ''); ?>"
                                                class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                                placeholder="https://booking.example.com/...">
                                        </div>
                                        <p class="mt-2 text-sm text-gray-500">
                                            Inserisci l'URL completo del tuo sistema di prenotazione online. Il chatbot userà questo URL come base per generare i link di prenotazione.
                                        </p>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" 
                                            class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                            <i class="fas fa-save -ml-0.5 h-5 w-5"></i>
                                            Salva configurazione
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Statistiche -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg p-6">
                            <div class="flex items-center gap-x-3">
                                <div class="flex-none rounded-full bg-indigo-50 p-3">
                                    <i class="fas fa-file-alt h-5 w-5 text-indigo-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500">Pagine indicizzate</p>
                                    <p class="mt-1 text-2xl font-semibold text-gray-900"><?php echo count($pages); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg p-6">
                            <div class="flex items-center gap-x-3">
                                <div class="flex-none rounded-full bg-indigo-50 p-3">
                                    <i class="fas fa-comments h-5 w-5 text-indigo-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500">Conversazioni</p>
                                    <p class="mt-1 text-2xl font-semibold text-gray-900"><?php echo count($conversations); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg p-6">
                            <div class="flex items-center gap-x-3">
                                <div class="flex-none rounded-full bg-indigo-50 p-3">
                                    <i class="fas fa-sitemap h-5 w-5 text-indigo-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500">Profondità scraping</p>
                                    <p class="mt-1 text-2xl font-semibold text-gray-900"><?php echo $chatbot['scraping_depth']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg p-6">
                            <div class="flex items-center gap-x-3">
                                <div class="flex-none rounded-full bg-indigo-50 p-3">
                                    <i class="fas fa-clock h-5 w-5 text-indigo-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500">Creato il</p>
                                    <p class="mt-1 text-2xl font-semibold text-gray-900"><?php echo date('d/m/Y', strtotime($chatbot['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagine indicizzate -->
                    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
                        <div class="border-b border-gray-200 px-4 py-5 sm:px-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-semibold leading-7 text-gray-900">Pagine indicizzate</h2>
                                <form method="POST" class="inline" onsubmit="return confirm('Sei sicuro di voler cancellare la coda di scraping?');">
                                    <input type="hidden" name="action" value="clear_queue">
                                    <button type="submit" class="inline-flex items-center gap-x-2 rounded-md bg-yellow-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-yellow-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow-600">
                                        <i class="fas fa-broom -ml-0.5 h-5 w-5"></i>
                                        Cancella Coda
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="px-4 py-5 sm:px-6">
                            <div class="overflow-hidden">
                                <div class="max-h-[60vh] overflow-y-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50 sticky top-0">
                                            <tr>
                                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">URL</th>
                                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Contenuto</th>
                                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Data scraping</th>
                                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                                    <span class="sr-only">Azioni</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white">
                                            <?php foreach ($pages as $page): ?>
                                            <tr>
                                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm">
                                                    <a href="<?php echo htmlspecialchars($page['url']); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                                        <?php echo htmlspecialchars($page['url']); ?>
                                                    </a>
                                                </td>
                                                <td class="px-3 py-4 text-sm text-gray-500">
                                                    <?php 
                                                    $content = json_decode($page['content'], true);
                                                    if (is_array($content)) {
                                                        echo '<div class="max-h-32 overflow-y-auto">';
                                                        foreach ($content as $text) {
                                                            echo '<p class="mb-2">' . htmlspecialchars(substr($text, 0, 200)) . '...</p>';
                                                        }
                                                        echo '</div>';
                                                    } else {
                                                        echo htmlspecialchars(substr($page['content'], 0, 200)) . '...';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                    <?php echo date('d/m/Y H:i', strtotime($page['scraped_at'])); ?>
                                                </td>
                                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                                    <form method="POST" class="inline" onsubmit="return confirm('Sei sicuro di voler eliminare questa pagina?');">
                                                        <input type="hidden" name="action" value="delete_page">
                                                        <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['id']); ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                                            Elimina
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ultime conversazioni -->
                    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
                        <div class="border-b border-gray-200 px-4 py-5 sm:px-6">
                            <h2 class="text-base font-semibold leading-7 text-gray-900">Ultime conversazioni</h2>
                        </div>
                        <div class="px-4 py-5 sm:px-6">
                            <div class="space-y-8">
                                <?php foreach ($conversations as $conv): ?>
                                <div class="conversation-item">
                                    <!-- Messaggio utente -->
                                    <div class="flex items-start space-x-3 mb-6">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i class="fas fa-user text-gray-500 text-sm"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between mb-1">
                                                <p class="text-sm font-medium text-gray-900">Utente</p>
                                                <time datetime="<?php echo $conv['timestamp']; ?>" class="text-xs text-gray-500">
                                                    <?php echo date('d/m/Y H:i', strtotime($conv['timestamp'])); ?>
                                                </time>
                                            </div>
                                            <div class="bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-700">
                                                <?php echo htmlspecialchars($conv['user_message']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Messaggio chatbot -->
                                    <div class="flex items-start space-x-3 pl-11">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center">
                                                <i class="fas fa-robot text-indigo-600 text-sm"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center mb-1">
                                                <p class="text-sm font-medium text-gray-900">Chatbot</p>
                                            </div>
                                            <div class="bg-white border border-gray-100 shadow-sm rounded-lg px-4 py-3 text-sm text-gray-700">
                                                <?php echo nl2br(htmlspecialchars($conv['bot_response'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <?php if (empty($conversations)): ?>
                                <div class="text-center py-6">
                                    <div class="mx-auto h-12 w-12 text-gray-400">
                                        <i class="fas fa-comments text-2xl"></i>
                                    </div>
                                    <h3 class="mt-2 text-sm font-semibold text-gray-900">Nessuna conversazione</h3>
                                    <p class="mt-1 text-sm text-gray-500">Non ci sono ancora conversazioni con questo chatbot.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    function copyIntegrationCode() {
        const questions = <?php echo json_encode(array_column($customQuestions, 'question')); ?>;
        const code = `<script src="<?php echo htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']); ?>/public/js/chatbot-widget.js"><\/script>
<script>
    window.initChatbot('<?php echo htmlspecialchars($chatbot['id']); ?>', 
        ${JSON.stringify(questions)}, 
        '<?php echo htmlspecialchars($chatbot['tone_of_voice']); ?>', 
        '<?php echo htmlspecialchars($chatbot['color_palette']); ?>',
        <?php echo !empty($chatbot['logo_url']) ? "'" . htmlspecialchars($chatbot['logo_url']) . "'" : "null"; ?>,
        <?php echo !empty($chatbot['booking_online_url']) ? "'" . htmlspecialchars($chatbot['booking_online_url']) . "'" : "null"; ?>
    );
<\/script>`;
        
        navigator.clipboard.writeText(code).then(() => {
            alert('Codice di integrazione copiato negli appunti!');
        }).catch(err => {
            console.error('Errore durante la copia del codice:', err);
            alert('Errore durante la copia del codice. Per favore, prova di nuovo.');
        });
    }
    </script>
</body>
</html>