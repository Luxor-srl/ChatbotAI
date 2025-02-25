<?php
session_start();
require_once 'config/database.php';

// Verifica autenticazione (da implementare)

// Gestione delle azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'delete_chatbot':
            if (isset($_POST['id'])) {
                $db->query("DELETE FROM chatbots WHERE id = ?", [$_POST['id']]);
                $_SESSION['success'] = 'Chatbot eliminato con successo.';
            }
            header('Location: index.php');
            exit;
    }
}

// Recupera la lista dei chatbot
$db = Database::getInstance();
$chatbots = $db->fetchAll("SELECT * FROM chatbots ORDER BY created_at DESC");

// Gestione dei messaggi di feedback
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="it" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Chatbot</title>
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
                            <a href="#" class="border-indigo-500 text-gray-900 inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium">Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page header -->
        <div class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-semibold text-gray-900">I tuoi Chatbot</h1>
                    <a href="create_chatbot.php" class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        <i class="fas fa-plus -ml-0.5 h-5 w-5"></i>
                        Nuovo Chatbot
                    </a>
                </div>
            </div>
        </div>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <?php if (empty($chatbots)): ?>
                    <!-- Empty state -->
                    <div class="text-center">
                        <i class="fas fa-robot text-gray-400 text-6xl mb-4"></i>
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">Nessun chatbot</h3>
                        <p class="mt-1 text-sm text-gray-500">Inizia creando il tuo primo chatbot.</p>
                        <div class="mt-6">
                            <a href="create_chatbot.php" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                <i class="fas fa-plus -ml-0.5 mr-1.5 h-5 w-5"></i>
                                Nuovo Chatbot
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Chatbot grid -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($chatbots as $chatbot): ?>
                            <div class="relative group">
                                <div class="relative overflow-hidden rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition-all duration-200 hover:shadow-md">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0">
                                                <span class="inline-block h-10 w-10 overflow-hidden rounded-lg bg-gray-100">
                                                    <i class="fas fa-robot text-gray-400 text-2xl flex items-center justify-center h-full"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <h3 class="truncate text-sm font-medium text-gray-900">
                                                    <a href="chatbot_details.php?id=<?php echo $chatbot['id']; ?>">
                                                        <?php echo htmlspecialchars($chatbot['name']); ?>
                                                    </a>
                                                </h3>
                                                <p class="mt-1 truncate text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($chatbot['website_url']); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0">
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
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <div class="flex items-center justify-between text-sm">
                                            <div class="truncate">
                                                <span class="text-gray-500">Creato:</span>
                                                <time datetime="<?php echo $chatbot['created_at']; ?>" class="ml-1 text-gray-900">
                                                    <?php echo date('d/m/Y H:i', strtotime($chatbot['created_at'])); ?>
                                                </time>
                                            </div>
                                            <div class="ml-2 flex flex-shrink-0">
                                                <span class="inline-flex items-center text-sm">
                                                    <span class="text-gray-500">Max pagine:</span>
                                                    <span class="ml-1 text-gray-900"><?php echo $chatbot['max_pages']; ?></span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="absolute inset-x-0 bottom-0 flex justify-center p-4 opacity-0 transition-opacity group-hover:opacity-100">
                                        <div class="flex items-center space-x-3">
                                            <a href="chatbot_details.php?id=<?php echo $chatbot['id']; ?>" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                                Dettagli
                                            </a>
                                            <form action="index.php" method="POST" class="inline">
                                                <input type="hidden" name="action" value="delete_chatbot">
                                                <input type="hidden" name="id" value="<?php echo $chatbot['id']; ?>">
                                                <button type="submit" onclick="return confirm('Sei sicuro di voler eliminare questo chatbot?')" class="inline-flex items-center rounded-md bg-red-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                                                    Elimina
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html> 