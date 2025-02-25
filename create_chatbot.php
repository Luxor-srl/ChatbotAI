<!DOCTYPE html>
<html lang="it" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Nuovo Chatbot</title>
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
                            <a href="#" class="border-indigo-500 text-gray-900 inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium">Nuovo Chatbot</a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page header -->
        <div class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Crea Nuovo Chatbot</h1>
                </div>
            </div>
        </div>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow">
                    <form id="createChatbotForm" action="process/create_chatbot.php" method="POST" class="p-6 space-y-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Website URL -->
                            <div class="col-span-2">
                                <label for="website_url" class="block text-sm font-medium leading-6 text-gray-900">
                                    URL del sito web
                                </label>
                                <div class="mt-2">
                                    <div class="flex rounded-md shadow-sm ring-1 ring-inset ring-gray-300 focus-within:ring-2 focus-within:ring-inset focus-within:ring-indigo-600">
                                        <input type="text" name="website_url" id="website_url" required
                                            class="block w-full rounded-md border-0 py-1.5 text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm sm:leading-6"
                                            placeholder="https://www.example.com">
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500">Inserisci l'URL completo del sito web da analizzare (incluso https://)</p>
                                </div>
                            </div>

                            <!-- Chatbot Name -->
                            <div class="col-span-2">
                                <label for="chatbot_name" class="block text-sm font-medium leading-6 text-gray-900">
                                    Nome del Chatbot
                                </label>
                                <div class="mt-2">
                                    <input type="text" name="chatbot_name" id="chatbot_name" required
                                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                        placeholder="Il mio Chatbot">
                                    <p class="mt-2 text-sm text-gray-500">Scegli un nome descrittivo per il tuo chatbot</p>
                                </div>
                            </div>

                            <!-- Scraping Depth -->
                            <div>
                                <label for="scraping_depth" class="block text-sm font-medium leading-6 text-gray-900">
                                    Profondità di scraping
                                </label>
                                <div class="mt-2">
                                    <select name="scraping_depth" id="scraping_depth"
                                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <option value="1">1 livello (solo homepage)</option>
                                        <option value="2" selected>2 livelli (homepage + link diretti)</option>
                                        <option value="3">3 livelli (esplorazione profonda)</option>
                                    </select>
                                    <p class="mt-2 text-sm text-gray-500">Determina quanto in profondità esplorare i link del sito</p>
                                </div>
                            </div>

                            <!-- Max Pages -->
                            <div>
                                <label for="max_pages" class="block text-sm font-medium leading-6 text-gray-900">
                                    Numero massimo di pagine
                                </label>
                                <div class="mt-2">
                                    <input type="number" name="max_pages" id="max_pages" value="50" min="1" max="100"
                                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    <p class="mt-2 text-sm text-gray-500">Limita il numero totale di pagine da analizzare</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-x-6">
                            <a href="index.php" class="text-sm font-semibold leading-6 text-gray-900">Annulla</a>
                            <button type="submit"
                                class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                <i class="fas fa-robot -ml-0.5 h-5 w-5"></i>
                                Crea Chatbot
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <!-- Loading overlay -->
        <div id="loadingOverlay" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50">
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-sm sm:p-6">
                        <div>
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100">
                                <i class="fas fa-robot h-6 w-6 text-indigo-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-5">
                                <h3 class="text-base font-semibold leading-6 text-gray-900" id="loadingTitle">
                                    Creazione del chatbot in corso
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500" id="loadingStatus">
                                        Inizializzazione...
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
    document.getElementById('createChatbotForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Validate URL format
        const websiteUrl = document.getElementById('website_url').value;
        if (!websiteUrl.startsWith('http://') && !websiteUrl.startsWith('https://')) {
            alert('L\'URL deve iniziare con http:// o https://');
            return;
        }
        
        const loadingOverlay = document.getElementById('loadingOverlay');
        const loadingStatus = document.getElementById('loadingStatus');
        loadingOverlay.classList.remove('hidden');
        
        try {
            const formData = new FormData(this);
            const response = await fetch(this.action, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                loadingStatus.textContent = 'Analisi del sito in corso...';
                
                // Monitora lo stato del chatbot
                let status = 'crawling';
                while (status === 'crawling') {
                    await new Promise(resolve => setTimeout(resolve, 2000));
                    const statusResponse = await fetch(`api/check_status.php?chatbot_id=${data.chatbotId}`);
                    const statusData = await statusResponse.json();
                    status = statusData.status;
                    
                    if (status === 'url_selection') {
                        window.location.href = `url_selection.php?id=${data.chatbotId}`;
                        return;
                    }
                }
            } else {
                alert(data.error || 'Si è verificato un errore durante la creazione del chatbot');
                loadingOverlay.classList.add('hidden');
            }
        } catch (error) {
            console.error('Errore durante la creazione del chatbot:', error);
            alert('Si è verificato un errore durante la creazione del chatbot');
            loadingOverlay.classList.add('hidden');
        }
    });
    </script>
</body>
</html>