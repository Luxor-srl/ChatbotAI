<?php
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$chatbotId = $_GET['id'];
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_selections':
            // Deseleziona tutte le URL
            $db->query(
                "UPDATE discovered_urls SET is_selected = FALSE WHERE chatbot_id = ?",
                [$chatbotId]
            );
            
            // Seleziona le URL scelte
            if (isset($_POST['selected_urls']) && is_array($_POST['selected_urls'])) {
                foreach ($_POST['selected_urls'] as $urlId) {
                    $db->query(
                        "UPDATE discovered_urls SET is_selected = TRUE WHERE id = ? AND chatbot_id = ?",
                        [$urlId, $chatbotId]
                    );
                }
            }
            
            // Aggiorna lo stato del chatbot
            $db->query(
                "UPDATE chatbots SET status = 'scraping', updated_at = NOW() WHERE id = ?",
                [$chatbotId]
            );

            // Inserisci le URL selezionate nella coda
            $selectedUrls = $db->fetchAll(
                "SELECT url FROM discovered_urls WHERE chatbot_id = ? AND is_selected = TRUE",
                [$chatbotId]
            );

            foreach ($selectedUrls as $url) {
                try {
                    $db->insert('scraping_queue', [
                        'chatbot_id' => $chatbotId,
                        'url' => $url['url'],
                        'status' => 'pending',
                        'depth' => 0
                    ]);
                } catch (Exception $e) {
                    if (!strpos($e->getMessage(), 'Duplicate entry')) {
                        throw $e;
                    }
                }
            }
            
            // Avvia il processo di scraping
            $scriptPath = realpath(__DIR__ . '/process/scrape_pages.php');
            $logFile = __DIR__ . '/process/scraping_' . $chatbotId . '.log';
            
            if (!file_exists(dirname($logFile))) {
                mkdir(dirname($logFile), 0777, true);
            }
            
            $workingDir = realpath(__DIR__);
            $cmd = sprintf(
                'cd %s && /usr/bin/php %s > %s 2>&1 & echo $!',
                escapeshellarg($workingDir),
                escapeshellarg($scriptPath),
                escapeshellarg($logFile)
            );
            
            exec($cmd);
            sleep(2);
            
            header('Location: chatbot_details.php?id=' . $chatbotId);
            exit;
    }
}

// Recupera i dettagli del chatbot
$chatbot = $db->fetch(
    "SELECT * FROM chatbots WHERE id = ?",
    [$chatbotId]
);

if (!$chatbot || $chatbot['status'] !== 'url_selection') {
    header('Location: index.php');
    exit;
}

// Recupera le URL scoperte
$discoveredUrls = $db->fetchAll(
    "SELECT * FROM discovered_urls WHERE chatbot_id = ? ORDER BY discovered_at DESC",
    [$chatbotId]
);

// Recupera il conteggio delle URL selezionate
$selectedCount = $db->fetch(
    "SELECT COUNT(*) as count FROM discovered_urls WHERE chatbot_id = ? AND is_selected = TRUE",
    [$chatbotId]
);

$maxPages = $chatbot['max_pages'];
$selectedCount = $selectedCount['count'];
?>
<!DOCTYPE html>
<html lang="it" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selezione URL - <?php echo htmlspecialchars($chatbot['name']); ?></title>
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
                            <img class="h-8 w-auto" src="https://tailwindui.com/img/logos/mark.svg?color=indigo&shade=600" alt="Logo">
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
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">Selezione URL</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Seleziona le pagine da includere nel chatbot (massimo <?php echo $maxPages; ?> pagine)
                        </p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm text-gray-500">
                            Selezionate: <span id="selectedCount" class="font-medium text-gray-900">0</span> / <?php echo count($discoveredUrls); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <form id="urlSelectionForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_selections">
                    
                    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div class="relative">
                                    <input type="text" id="searchInput" placeholder="Cerca URL..." 
                                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <button type="button" id="selectAllBtn" 
                                        class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        Seleziona tutto
                                    </button>
                                    <button type="button" id="deselectAllBtn"
                                        class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        Deseleziona tutto
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-hidden">
                                <div class="max-h-[60vh] overflow-y-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50 sticky top-0">
                                            <tr>
                                                <th scope="col" class="relative w-12 px-6 sm:w-16 sm:px-8">
                                                    <input type="checkbox" id="selectAll"
                                                        class="absolute left-4 top-1/2 -mt-2 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                                                </th>
                                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">URL</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white">
                                            <?php foreach ($discoveredUrls as $url): ?>
                                            <tr class="url-row">
                                                <td class="relative w-12 px-6 sm:w-16 sm:px-8">
                                                    <input type="checkbox" name="selected_urls[]" value="<?php echo $url['id']; ?>"
                                                        class="url-checkbox absolute left-4 top-1/2 -mt-2 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                                        <?php echo $url['is_selected'] ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm">
                                                    <div class="flex items-center">
                                                        <div class="font-medium text-gray-900 hover:text-indigo-600">
                                                            <a href="<?php echo htmlspecialchars($url['url']); ?>" target="_blank" class="hover:underline">
                                                                <?php echo htmlspecialchars($url['url']); ?>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8">
                            <a href="index.php" class="text-sm font-semibold leading-6 text-gray-900">Annulla</a>
                            <button type="submit" id="submitBtn"
                                class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                <i class="fas fa-check -ml-0.5 h-5 w-5"></i>
                                Conferma selezione
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>

        <!-- Loading overlay -->
        <div id="loadingOverlay" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50">
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-sm sm:p-6">
                        <div>
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100">
                                <i class="fas fa-robot h-6 w-6 text-indigo-600 animate-bounce"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-5">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">
                                    Avvio analisi delle pagine
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Stiamo preparando il chatbot con le pagine selezionate...
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-6">
                            <div class="flex justify-center">
                                <div class="h-2 w-32 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-600 rounded-full animate-progress"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @keyframes progress {
            0% { width: 0; }
            100% { width: 100%; }
        }
        .animate-progress {
            animation: progress 30s ease-in-out infinite;
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('urlSelectionForm');
        const searchInput = document.getElementById('searchInput');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const deselectAllBtn = document.getElementById('deselectAllBtn');
        const selectAllCheckbox = document.getElementById('selectAll');
        const urlCheckboxes = document.querySelectorAll('.url-checkbox');
        const urlRows = document.querySelectorAll('.url-row');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const maxPages = <?php echo $maxPages; ?>;

        function updateSelectedCount() {
            const selectedCount = document.querySelectorAll('.url-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selectedCount;
            
            // Disabilita le checkbox non selezionate se abbiamo raggiunto il limite
            if (selectedCount >= maxPages) {
                urlCheckboxes.forEach(checkbox => {
                    if (!checkbox.checked) {
                        checkbox.disabled = true;
                    }
                });
            } else {
                urlCheckboxes.forEach(checkbox => {
                    checkbox.disabled = false;
                });
            }
        }

        // Gestione ricerca
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            urlRows.forEach(row => {
                const url = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                row.style.display = url.includes(searchTerm) ? '' : 'none';
            });
        });

        // Gestione selezione/deselezione tutto
        selectAllBtn.addEventListener('click', function() {
            let count = 0;
            urlCheckboxes.forEach(checkbox => {
                if (count < maxPages && !checkbox.disabled) {
                    checkbox.checked = true;
                    count++;
                }
            });
            updateSelectedCount();
        });

        deselectAllBtn.addEventListener('click', function() {
            urlCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        });

        // Gestione checkbox principale
        selectAllCheckbox.addEventListener('change', function() {
            let count = 0;
            urlCheckboxes.forEach(checkbox => {
                if (count < maxPages && !checkbox.disabled) {
                    checkbox.checked = this.checked;
                    count++;
                }
            });
            updateSelectedCount();
        });

        // Gestione checkbox individuali
        urlCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        // Gestione form submit
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selectedCount = document.querySelectorAll('.url-checkbox:checked').length;
            if (selectedCount === 0) {
                alert('Seleziona almeno una pagina da includere nel chatbot.');
                return;
            }
            
            loadingOverlay.classList.remove('hidden');
            this.submit();
        });

        // Inizializza il conteggio
        updateSelectedCount();
    });
    </script>
</body>
</html> 