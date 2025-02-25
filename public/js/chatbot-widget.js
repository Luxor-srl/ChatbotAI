class ChatbotWidget {
    constructor(chatbotId, customQuestions = [], tone = 'professionale', palette = 'indigo', logo = null, bookingUrl = null) {
        this.chatbotId = chatbotId;
        this.apiEndpoint = 'https://apichatbot.gabrimarchedev.it/api/chat.php';
        this.customQuestions = customQuestions.slice(0, 3); // Limita a 3 domande
        this.tone = tone;
        this.palette = palette;
        this.logo = logo;
        this.bookingUrl = bookingUrl;
        
        // Genera un ID utente casuale se non esiste
        this.userId = this.generateUserId();
        
        // Inizializza lo stato del chatbot
        this.state = this.loadChatState();
        
        // Definizione delle palette di colori
        this.palettes = {
            'indigo': {
                primary: '#4F46E5',
                primaryDark: '#3730A3',
                hover: '#4338CA',
                text: '#1E1B4B',
                bg: '#E0E7FF'
            },
            'emerald': {
                primary: '#059669',
                primaryDark: '#047857',
                hover: '#065F46',
                text: '#064E3B',
                bg: '#D1FAE5'
            },
            'rose': {
                primary: '#E11D48',
                primaryDark: '#BE123C',
                hover: '#9F1239',
                text: '#881337',
                bg: '#FFE4E6'
            },
            'amber': {
                primary: '#D97706',
                primaryDark: '#B45309',
                hover: '#92400E',
                text: '#78350F',
                bg: '#FEF3C7'
            },
            'sky': {
                primary: '#0284C7',
                primaryDark: '#0369A1',
                hover: '#075985',
                text: '#0C4A6E',
                bg: '#E0F2FE'
            }
        };

        console.log('ChatbotWidget initialized with endpoint:', this.apiEndpoint);
        this.isExpanded = false;
        this.createWidget();
        this.initializeEventListeners();
        this.loadPreviousMessages();
        
        // Mostra il messaggio di benvenuto solo se non è già stato mostrato
        if (!this.state.introShown) {
            this.showWelcomeMessage();
            this.state.introShown = true;
            this.saveChatState();
        }
        
        // Ripristina lo stato visuale del chatbot
        this.restoreVisualState();

        this.inactivityTimeout = null;
        this.inactivityDelay = 10000; // 10 secondi di inattività prima di mostrare il fumetto

        // Inizializza il caricamento delle dipendenze
        this.loadDateRangePickerDependencies();

        // Add viewport meta tag for mobile devices if not present
        if (!document.querySelector('meta[name="viewport"]')) {
            const viewportMeta = document.createElement('meta');
            viewportMeta.name = 'viewport';
            viewportMeta.content = 'width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1';
            document.head.appendChild(viewportMeta);
        } else {
            // Update existing viewport meta to prevent zoom
            const existingViewport = document.querySelector('meta[name="viewport"]');
            existingViewport.content = 'width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1';
        }
    }

    generateUserId() {
        const storedState = this.loadChatState();
        if (storedState.userId) {
            return storedState.userId;
        }
        
        // Genera un UUID v4
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    loadChatState() {
        const defaultState = {
            isChatOpen: false,
            isMaximized: true,
            introShown: false,
            hasBeenOpened: false,
            messages: [],
            lastWelcomeTime: 0 // Aggiungi questo campo per tracciare l'ultimo messaggio di benvenuto
        };

        try {
            const savedState = localStorage.getItem(`chatbot_state_${this.chatbotId}`);
            if (savedState) {
                const parsedState = JSON.parse(savedState);
                // Verifica se messages esiste e non è vuoto prima di ripristinarlo
                if (!parsedState.messages || !Array.isArray(parsedState.messages)) {
                    parsedState.messages = [];
                }
                // Rimuovi eventuali duplicati basandoti sul contenuto e il timestamp
                parsedState.messages = this.removeDuplicateMessages(parsedState.messages);
                return { ...defaultState, ...parsedState };
            }
        } catch (error) {
            console.error('Error loading chat state:', error);
            // In caso di errore, pulisci lo storage per evitare problemi futuri
            localStorage.removeItem(`chatbot_state_${this.chatbotId}`);
        }

        return defaultState;
    }

    removeDuplicateMessages(messages) {
        const seen = new Set();
        return messages.filter(message => {
            const key = `${message.role}-${message.content}-${message.timestamp || Date.now()}`;
            if (seen.has(key)) {
                return false;
            }
            seen.add(key);
            return true;
        });
    }

    saveChatState() {
        try {
            localStorage.setItem(`chatbot_state_${this.chatbotId}`, JSON.stringify(this.state));
        } catch (error) {
            console.error('Error saving chat state:', error);
        }
    }

    restoreVisualState() {
        const container = document.querySelector('.chatbot-widget-container');
        const toggle = document.querySelector('.chatbot-toggle');
        const isMobile = window.innerWidth <= 768;

        if (this.state.isChatOpen) {
            container.style.display = 'flex';
            toggle.style.display = 'none';
            if (isMobile) {
                container.style.transform = 'none';
                container.style.right = '0';
                container.style.bottom = '0';
            }
        } else {
            container.style.display = 'none';
            toggle.style.display = 'flex';
        }
    }

    createWidget() {
        const widget = document.createElement('div');
        widget.id = 'chatbot-root';
        widget.innerHTML = `
            <div class="chatbot-widget-container" style="display: none;">
                <div class="chatbot-inner-container">
                    <div class="chatbot-header">
                        <div class="logo-container">
                            ${this.logo ? `<img src="${this.logo}" alt="Logo" class="logo-img">` : ''}
                        </div>
                        <div class="header-buttons">
                            <button class="header-button expand-button">
                                <div class="expand-icon-wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
                                    </svg>
                                </div>
                            </button>
                            <button class="header-button">
                                <div class="close-icon-wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path class="icon-path" d="M18 6L6 18M6 6l12 12"></path>
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </div>
                    <div class="chatbot-messages" id="chatbot-message-area">
                        <!-- Messages will be inserted here -->
                    </div>
                    <div class="chatbot-footer">
                        <form class="message-form">
                            <button type="button" class="header-button" id="new-chat-open">
                                <div class="delete-icon-wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <g class="lid">
                                            <path d="M3 6h18"></path>
                                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                        </g>
                                        <path class="bin" d="M19 8v12c0 1-1 2-2 2H7c-1 0-2-1-2-2V8"></path>
                                        <line class="line1" x1="10" x2="10" y1="11" y2="17"></line>
                                        <line class="line2" x1="14" x2="14" y1="11" y2="17"></line>
                                    </svg>
                                </div>
                            </button>
                            <div class="input-container">
                                <input type="text" placeholder="Scrivi un messaggio..." class="message-input" />
                            </div>
                            <button type="button" class="send-button">
                                <div class="send-icon-wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path class="send-path" d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path>
                                    </svg>
                                </div>
                            </button>
                        </form>
                        <div class="powered-by">
                         <svg id="Raggruppa_9" data-name="Raggruppa 9" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="56.152" height="13.118" viewBox="0 0 56.152 13.118">
  <defs>
    <clipPath id="clip-path">
      <path id="Tracciato_8" data-name="Tracciato 8" d="M0-30.361H56.152V-43.479H0Z" transform="translate(0 43.479)" fill="#c34242"/>
    </clipPath>
  </defs>
  <g id="Raggruppa_8" data-name="Raggruppa 8" transform="translate(0 0)" clip-path="url(#clip-path)">
    <g id="Raggruppa_6" data-name="Raggruppa 6" transform="translate(0 0)">
      <path id="Tracciato_6" data-name="Tracciato 6" d="M-96.057-7.891a1.949,1.949,0,0,0-.945-.2,2.195,2.195,0,0,0-1.779.834q-.056.074-.1.037a.157.157,0,0,1-.046-.13v-.259a.311.311,0,0,0-.352-.352h-2.779a.311.311,0,0,0-.352.352v8.93a.311.311,0,0,0,.352.352h2.779a.311.311,0,0,0,.352-.352V-3.259a1.049,1.049,0,0,1,.343-.825,1.691,1.691,0,0,1,.825-.4,2.733,2.733,0,0,1,.537-.056,1.927,1.927,0,0,1,.5.056q.352.074.408-.241l.445-2.723a.379.379,0,0,0-.185-.445m-7.522,6.1a6.826,6.826,0,0,0,.148-1.408,5.981,5.981,0,0,0-.222-1.649,4.466,4.466,0,0,0-1.621-2.381,4.705,4.705,0,0,0-2.9-.88,4.787,4.787,0,0,0-2.918.88,4.432,4.432,0,0,0-1.64,2.418,5.731,5.731,0,0,0-.222,1.649,5.538,5.538,0,0,0,.185,1.482,4.454,4.454,0,0,0,1.6,2.557,4.693,4.693,0,0,0,2.974.945,4.661,4.661,0,0,0,3.011-.973,4.608,4.608,0,0,0,1.6-2.64m-3.353-1.39a4.11,4.11,0,0,1-.074.834,1.778,1.778,0,0,1-.408.861,1,1,0,0,1-.778.324q-.908,0-1.186-1.186a4.068,4.068,0,0,1-.074-.834,3.131,3.131,0,0,1,.093-.852q.241-1.093,1.167-1.093.889,0,1.167,1.093a4.02,4.02,0,0,1,.093.852m-5.985-4.354a.321.321,0,0,0,.074-.2.194.194,0,0,0-.084-.157.337.337,0,0,0-.213-.065h-3.187a.447.447,0,0,0-.426.241l-1.075,1.76a.118.118,0,0,1-.111.074.119.119,0,0,1-.111-.074l-1.074-1.76a.448.448,0,0,0-.426-.241h-2.965a.337.337,0,0,0-.213.065.194.194,0,0,0-.083.157.319.319,0,0,0,.074.2l2.8,4.28a.236.236,0,0,1,0,.222l-2.8,4.28a.318.318,0,0,0-.074.2.193.193,0,0,0,.083.157.335.335,0,0,0,.213.065h3.187a.447.447,0,0,0,.426-.241l.371-.607.037-.022,4.679-6.965Zm-10.875,8.856v-8.93a.311.311,0,0,0-.352-.352h-2.779a.311.311,0,0,0-.352.352V-2.24a.793.793,0,0,1-.018.185,1.2,1.2,0,0,1-.361.649.951.951,0,0,1-.658.241.932.932,0,0,1-.75-.343,1.351,1.351,0,0,1-.287-.9V-7.613a.311.311,0,0,0-.352-.352h-2.779a.311.311,0,0,0-.352.352v5.966A3.647,3.647,0,0,0-132,.9a2.88,2.88,0,0,0,2.242.917,2.692,2.692,0,0,0,2.316-1c.037-.049.074-.068.111-.056s.056.049.056.111v.445a.311.311,0,0,0,.352.352h2.779a.311.311,0,0,0,.352-.352m-10.283,0V-10.948a.311.311,0,0,0-.352-.352h-2.779a.311.311,0,0,0-.352.352V1.317a.311.311,0,0,0,.352.352h2.779a.311.311,0,0,0,.352-.352m16.955-.857.591.968a.447.447,0,0,0,.426.241h2.965a.336.336,0,0,0,.213-.065.193.193,0,0,0,.084-.157.32.32,0,0,0-.074-.2l-2.677-4.1Z" transform="translate(137.559 11.3)" fill="#fff"/>
    </g>
    <g id="Raggruppa_7" data-name="Raggruppa 7" transform="translate(43.337 3.387)">
      <path id="Tracciato_7" data-name="Tracciato 7" d="M-29.66-18.817v-6.651a1.479,1.479,0,0,0-1.479-1.479H-41a1.479,1.479,0,0,0-1.479,1.479v6.651A1.479,1.479,0,0,0-41-17.338h9.857a1.479,1.479,0,0,0,1.479-1.479m-5.559-.707q0,.086-.11.086h-1.338a.122.122,0,0,1-.133-.1l-.172-.61c-.011-.021-.024-.031-.039-.031h-1.658c-.016,0-.029.011-.039.031l-.172.61a.122.122,0,0,1-.133.1h-1.338c-.094,0-.128-.044-.1-.133l1.651-5.25a.127.127,0,0,1,.133-.094h1.658a.127.127,0,0,1,.133.094l1.651,5.25a.111.111,0,0,1,.008.047m2.731-.031a.116.116,0,0,1-.031.086.116.116,0,0,1-.086.031h-1.236a.115.115,0,0,1-.086-.031.116.116,0,0,1-.031-.086V-24.8a.117.117,0,0,1,.031-.086.117.117,0,0,1,.086-.031H-32.6a.118.118,0,0,1,.086.031.118.118,0,0,1,.031.086Zm-4.867-1.753c.031,0,.042-.016.031-.047l-.5-1.753c-.005-.016-.013-.023-.023-.023s-.018.008-.024.023l-.493,1.753c-.005.031.005.047.031.047Z" transform="translate(42.474 26.947)" fill="#fff"/>
    </g>
  </g>
</svg>

                        </div>
                    </div>
                </div>
            </div>
            <div class="chatbot-toggle" style="display: flex;">
                <button class="toggle-button">
                    <div class="toggle-icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                </button>
            </div>
        `;

        // Add styles
        const styles = document.createElement('style');
        styles.textContent = `
            #chatbot-root {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 2147483647;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                font-size: 14px;
            }

            .chatbot-widget-container {
                position: fixed;
                background-color: white;
                width: 370px;
                height: 78vh;
                max-height: 700px;
                right: 40px;
                bottom: 60px;
                border-radius: 12px;
                transition: all 0.3s ease;
                z-index: 2147483649;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            }

            .chatbot-inner-container {
                display: flex;
                flex-direction: column;
                height: 100%;
                width: 100%;
                overflow: hidden;
            }

            .chatbot-widget-container.expanded {
                position: fixed;
                width: 90%;
                max-width: 800px;
                height: 90vh;
                max-height: 800px;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }

            .chatbot-header {
                background-color: ${this.palettes[this.palette].primary};
                color: white;
                padding: 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                min-height: 74px;
                flex-shrink: 0;
            }

            .chatbot-messages {
                flex: 1;
                overflow-y: auto;
                padding: 20px;
                background-color: #ffffff;
            }

            .chatbot-footer {
                padding: 16px;
                background-color: ${this.palettes[this.palette].primary};
                flex-shrink: 0;
            }

            .chatbot-toggle {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 2147483647;
            }

            .toggle-button {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background-color: ${this.palettes[this.palette].primary};
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                transition: transform 0.2s ease;
            }

            .toggle-button:hover {
                transform: none;
                background-color: ${this.palettes[this.palette].primaryDark};
            }

            .toggle-icon-wrapper {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .chatbot-header {
                background-color: ${this.palettes[this.palette].primary};
                color: white;
                padding: 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                height: 74px;
                min-height: 74px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }

            .logo-container {
                flex: 2;
                display: flex;
                justify-content: flex-start;
                align-items: center;
                height: 100%;
                padding: 0 16px;
            }

            .logo-img {
                max-height: 42px;
                width: auto;
                object-fit: contain;
            }

            .header-button {
                background-color: rgba(255, 255, 255, 0.2);
                width: 32px;
                height: 32px;
                border-radius: 50%;
                border: none;
                padding: 5px;
                cursor: pointer;
                transition: background-color 0.2s;
                display: flex;
                justify-content: center;
                color: white;
                margin-left: auto;
            }

            .header-button:hover {
                background-color: rgba(255, 255, 255, 0.3);
            }

            .header-button#new-chat-open {
                background-color: transparent;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                border: none;
                padding: 5px;
                cursor: pointer;
                transition: background-color 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                color: ${this.palettes[this.palette].primary};
                margin: 0;
            }

            .header-button#new-chat-open:hover {
                background-color: rgba(0, 0, 0, 0.05);
            }

            .chatbot-messages {
                flex-grow: 1;
                overflow-y: auto;
                padding: 20px;
                background-color: #ffffff;
            }

            .chatbot-footer {
                padding: 16px;
                background-color: ${this.palettes[this.palette].primary};
            }

            .message-form {
                background-color: white;
                border-radius: 12px;
                padding: 6px 12px;
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 12px;
                border: 1px solid #E5E7EB;
            }

            .input-container {
                flex: 1;
            }

            .message-input {
                width: 100%;
                border: none;
                padding: 8px 0;
                font-size: 14px;
                outline: none;
                background: transparent;
                font-family: inherit;
            }

            .send-button {
                background-color: transparent;
                border: none;
                padding: 0;
                cursor: pointer;
                color: ${this.palettes[this.palette].primary};
                display: flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                margin: 0;
            }

            .send-button:hover {
                color: ${this.palettes[this.palette].hover};
            }

            .send-icon-wrapper, .delete-icon-wrapper {
                display: flex;
                align-items: center;
                width: 100%;
                height: 100%;
            }

            .powered-by {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 16px 8px 8px 8px;
                border: none;
            }

            .powered-by svg {
                border: none;
                outline: none;
            }

            .svg-sama {
                border: none;
                outline: none;
                height: 60px;
                width: 240px;
                min-height: 60px;
                object-fit: contain;
            }

            .luxor-ai-logo {
                max-height: 150px;  // increased size for better visibility
                width: auto;
            }

            .luxor-ai-text {
                color: white;
                font-size: 14px;
                font-weight: normal;
            }

            .message-container {
                margin: 4px 0;
                max-width: 85%;
                clear: both;
                opacity: 0;
                transform-origin: center left;
                animation: fadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            }

            @keyframes fadeIn {
                from { 
                    opacity: 0; 
                    transform: translateY(10px);
                }
                to { 
                    opacity: 1; 
                    transform: translateY(0);
                }
            }

            .bot-message-container {
                float: left;
            }

            .user-message-container {
                float: right;
            }

            .bot-message-wrapper {
                display: flex;
                align-items: flex-start;
                gap: 8px;
            }

            .bot-avatar {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                overflow: hidden;
                flex-shrink: 0;
                border: 2px solid ${this.palettes[this.palette].primary};
                background-color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .message {
                padding: 6px 10px;
                border-radius: 12px;
                line-height: 1.4;
                box-shadow: none;
                max-width: 100%;
                word-wrap: break-word;
                font-size: 14px;
            }

            .bot-message {
                background-color: #F8F9FA;
                color: #1F2937;
                border-bottom-left-radius: 4px;
                border: 1px solid #E5E7EB;
            }

            .user-message {
                background-color: ${this.palettes[this.palette].primary};
                color: white;
                border-bottom-right-radius: 4px;
            }

            .message:hover {
                transform: none;
            }

            /* Style custom question buttons */
            .custom-question {
                display: inline-block;
                margin: 4px;
                padding: 4px 10px;
                background-color: white;
                border: 1px solid ${this.palettes[this.palette].primary};
                border-radius: 12px;
                color: ${this.palettes[this.palette].primary};
                font-weight: normal;
                cursor: pointer;
                transition: background-color 0.2s ease;
                font-size: 14px;
                box-shadow: none;
            }

            .custom-question:hover {
                background-color: ${this.palettes[this.palette].primary}10;
            }

            .booking-select,
            .booking-date-input,
            button {
                font-size: 14px;
                font-family: inherit;
            }

            /* Styles for markdown content inside messages */
            .message h1,
            .message h2,
            .message h3 {
                font-size: 14px;
                font-weight: bold;
                margin: 8px 0;
            }

            .message p {
                font-size: 14px;
                margin: 8px 0;
            }

            .message a {
                font-size: 14px;
                color: inherit;
            }

            .message code {
                font-size: 14px;
                font-family: monospace;
            }

            .message ul,
            .message ol {
                font-size: 14px;
                margin: 8px 0;
                padding-left: 20px;
            }

            .message blockquote {
                font-size: 14px;
                margin: 8px 0;
                padding-left: 12px;
                border-left: 2px solid currentColor;
            }

            .svg-sama {
                border: none;
                outline: none;
            }

            .header-buttons {
                display: flex;
                gap: 8px;
                align-items: center;
            }
            .expand-icon-wrapper svg {
                transition: transform 0.3s ease;
            }

            
            .chatbot-widget-container.expanded {
                position: fixed;
                width: 90%;
                max-width: 800px;
                height: 90vh;
                max-height: 800px;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }

            .chatbot-widget-container.expanded .MuiBox-root {
                height: 100%;
                display: flex;
                flex-direction: column;
            }

            .chatbot-widget-container.expanded .chatbot-header {
                height: auto;
                min-height: 74px;
                padding: 20px;
            }

            .chatbot-widget-container.expanded .chatbot-messages {
                flex: 1;
                height: auto;
                overflow-y: auto;
            }

            .chatbot-widget-container.expanded .chatbot-footer {
                padding: 20px;
            }

            .chatbot-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 90000;
            }

            .chatbot-widget-container.expanded {
                z-index: 90001;
                
            }

            @media (max-width: 768px) {
                .chatbot-widget-container {
                    width: 100%;
                    height: 100%;
                    height: -webkit-fill-available;
                    height: -moz-available;
                    height: fill-available;
                    max-height: 100%;
                    max-height: -webkit-fill-available;
                    max-height: -moz-available;
                    max-height: fill-available;
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    border-radius: 0;
                    margin: 0;
                    padding: env(safe-area-inset-top, 0) 0 env(safe-area-inset-bottom, 0) 0;
                }

                .chatbot-widget-container .chatbot-inner-container {
                    height: 100%;
                    display: flex;
                    flex-direction: column;
                }

                .chatbot-widget-container .chatbot-messages {
                    flex: 1;
                    height: auto;
                }

                .chatbot-widget-container .chatbot-footer {
                    padding-bottom: calc(16px + env(safe-area-inset-bottom, 0));
                }

                #chatbot-root {
                    bottom: 0;
                    right: 0;
                }

                .chatbot-toggle {
                    bottom: max(20px, env(safe-area-inset-bottom, 20px));
                    right: 20px;
                }

                .expand-button {
                    display: none;
                }

                .message-input {
                    font-size: 16px !important;
                }
            }

            @supports (-webkit-touch-callout: none) {
                .chatbot-widget-container {
                    /* Fix per iOS Safari */
                    height: -webkit-fill-available;
                    height: 100dvh;
                }

                .chatbot-messages {
                    /* Previene il bounce su iOS */
                    overscroll-behavior-y: contain;
                    -webkit-overflow-scrolling: touch;
                }

                .message-form {
                    /* Assicura che la form rimanga visibile sopra la tastiera */
                    position: relative;
                    z-index: 1;
                }
            }

            @media (max-width: 768px) {
                .chatbot-messages {
                    /* Migliore gestione dello scroll su mobile */
                    -webkit-overflow-scrolling: touch;
                    overscroll-behavior-y: contain;
                    padding: 16px;
                }

                .chatbot-widget-container {
                    /* Usa dynamic viewport height quando disponibile */
                    height: 100dvh;
                }

                .message-input {
                    /* Previene lo zoom su iOS */
                    font-size: 16px !important;
                    transform: translateZ(0);
                    -webkit-transform: translateZ(0);
                }
            }
        `;

        document.head.appendChild(styles);
        document.body.appendChild(widget);
    }

    formatMarkdown(text) {
        // Converti markdown in HTML con classi CSS uniformi
        return text
            // Headers
            .replace(/^# (.*$)/gm, '<h1>$1</h1>')
            .replace(/^## (.*$)/gm, '<h2>$1</h2>')
            .replace(/^### (.*$)/gm, '<h3>$1</h3>')
            
            // Grassetto
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            
            // Corsivo
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            
            // Link
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
            
            // Code inline
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            
            // Liste non ordinate
            .replace(/^\* (.+)$/gm, '<li>$1</li>')
            .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
            
            // Liste ordinate
            .replace(/^\d+\. (.+)$/gm, '<li>$1</li>')
            .replace(/(<li>.*<\/li>)/s, '<ol>$1</ol>')
            
            // Citazioni
            .replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>')
            
            // Paragrafi
            .replace(/\n\n/g, '</p><p>')
            .replace(/^(.+)$/gm, function(match) {
                if (!/^<[h|p|ul|ol|blockquote]/.test(match)) {
                    return '<p>' + match + '</p>';
                }
                return match;
            })
            
            // Pulizia
            .replace(/<\/p><p><\/p><p>/g, '</p><p>')
            .replace(/^<p>/, '')
            .replace(/<\/p>$/, '');
    }

    addMessage(text, sender, persist = true) {
        const messageArea = document.getElementById('chatbot-message-area');
        const messageContainer = document.createElement('div');
        messageContainer.className = `message-container ${sender}-message-container`;
        
        let messageHTML = '';
        if (sender === 'bot') {
            messageHTML = `
                <div class="bot-message-wrapper">
                    <div class="bot-avatar">
                        <img src="https://img.freepik.com/free-psd/3d-rendering-hair-style-avatar-design_23-2151869153.jpg?t=st=1740125450~exp=1740129050~hmac=805c02a43a4a70a65a99fe711e0fbb629cd7f69290d463bfb9ca090e90291cc6&w=2000" alt="Bot Avatar">
                    </div>
                    <div class="message bot-message">
                        ${this.formatMarkdown(text)}
                    </div>
                </div>
            `;
        } else {
            messageHTML = `
                <div class="user-message-wrapper">
                    <div class="message user-message">
                        ${text}
                    </div>
                </div>
            `;
        }
        
        messageContainer.innerHTML = messageHTML;
        messageArea.appendChild(messageContainer);
        messageArea.scrollTop = messageArea.scrollHeight;

        if (persist) {
            // Add message to state only if persisting
            this.state.messages.push({
                role: sender,
                content: text,
                timestamp: Date.now()
            });
            this.saveChatState();
        }

        if (!document.querySelector('#chat-message-styles')) {
            const styles = document.createElement('style');
            styles.id = 'chat-message-styles';
            styles.textContent = `
                .message-container {
                    margin: 4px 0;
                    max-width: 85%;
                    clear: both;
                    opacity: 0;
                    transform-origin: center left;
                    animation: fadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
                    font-size: 14px;
                }

                @keyframes fadeIn {
                    from { 
                        opacity: 0; 
                        transform: translateY(10px) scale(0.95);
                    }
                    to { 
                        opacity: 1; 
                        transform: translateY(0) scale(1);
                    }
                }

                .bot-message-container {
                    float: left;
                }

                .user-message-container {
                    float: right;
                }

                .bot-message-wrapper {
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                }

                .bot-avatar {
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    overflow: hidden;
                    flex-shrink: 0;
                    border: 2px solid ${this.palettes[this.palette].primary};
                    background-color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }

                .message {
                    padding: 6px 10px;
                    border-radius: 12px;
                    font-size: 14px;
                    line-height: 1.5;
                    box-shadow: none;
                    max-width: 100%;
                    word-wrap: break-word;
                    transition: transform 0.2s ease;
                }

                .bot-message {
                    background-color: #F8F9FA;
                    color: #1F2937;
                    border-bottom-left-radius: 4px;
                    border: 1px solid #E5E7EB;
                }

                .user-message {
                    background-color: ${this.palettes[this.palette].primary};
                    color: white;
                    border-bottom-right-radius: 4px;
                }

                .message:hover {
                    transform: translateY(-1px);
                }

                /* Style custom question buttons */
                .custom-question {
                    display: inline-block;
                    margin: 4px;
                    padding: 4px 10px;
                    background-color: white;
                    border: 1px solid ${this.palettes[this.palette].primary};
                    border-radius: 12px;
                    color: ${this.palettes[this.palette].primary};
                    font-weight: normal;
                    cursor: pointer;
                    transition: background-color 0.2s ease;
                    font-size: 14px;
                    box-shadow: none;
                }

                .custom-question:hover {
                    background-color: ${this.palettes[this.palette].primary}10;
                }

                .custom-question:active {
                    transform: none;
                    background-color: ${this.palettes[this.palette].primary}25;
                }
            `;
            document.head.appendChild(styles);
        }
    }

    loadPreviousMessages() {
        const messages = document.querySelector('.chatbot-messages');
        messages.innerHTML = ''; // Pulisci i messaggi esistenti
        this.state.messages.forEach(message => {
            this.addMessage(message.content, message.role, false);
        });
        messages.scrollTop = messages.scrollHeight;
    }

    showWelcomeMessage() {
        // Show welcome message only if there are no previous messages
        if (this.state.messages && this.state.messages.length > 0) return;
        
        let welcomeMessage = 'Benvenuto! Come posso aiutarti oggi?';
        
        if (this.customQuestions && this.customQuestions.length > 0) {
            welcomeMessage += '\n\nPuoi chiedermi ad esempio:';
            const questionsHtml = this.customQuestions.map(q => 
                `<button class="custom-question">${q}</button>`
            ).join('');
            this.addMessage(welcomeMessage + '\n\n' + questionsHtml, 'bot');
        } else {
            this.addMessage(welcomeMessage, 'bot');
        }
    }

    initializeEventListeners() {
        const messageArea = document.getElementById('chatbot-message-area');
        const input = document.querySelector('.message-input');
        const sendButton = document.querySelector('.send-button');
        const closeButton = document.querySelector('.close-icon-wrapper').closest('button');
        const newChatButton = document.getElementById('new-chat-open');
        const chatRoot = document.getElementById('chatbot-root');
        const toggleButton = document.querySelector('.toggle-button');
        const chatContainer = document.querySelector('.chatbot-widget-container');
        const toggleContainer = document.querySelector('.chatbot-toggle');

        // Send message on button click
        sendButton.addEventListener('click', () => {
            const message = input.value.trim();
            if (message) {
                this.addMessage(message, 'user');
                this.sendToBackend(message);
                input.value = '';
            }
        });

        // Send message on Enter key
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const message = input.value.trim();
                if (message) {
                    this.addMessage(message, 'user');
                    this.sendToBackend(message);
                    input.value = '';
                }
            }
        });

        // Remove duplicate close event listener that hid the whole chatRoot
        // (Removed: chatRoot.style.display = 'none'; ...)

        // Close chat (keeps widget container hidden and shows toggle bubble)
        closeButton.addEventListener('click', () => {
            chatContainer.style.display = 'none';
            toggleContainer.style.display = 'flex';
            this.state.isChatOpen = false;
            this.saveChatState();
        });

        // Start new chat
        newChatButton.addEventListener('click', () => {
            // Pulisci completamente lo storage prima di iniziare una nuova chat
            localStorage.removeItem(`chatbot_state_${this.chatbotId}`);
            this.state = this.loadChatState();
            this.loadPreviousMessages();
            this.showWelcomeMessage();
            this.saveChatState();
        });

        // Auto-scroll messages
        const observer = new MutationObserver(() => {
            messageArea.scrollTop = messageArea.scrollHeight;
        });
        observer.observe(messageArea, {
            childList: true,
            subtree: true
        });

        // Toggle chat visibility
        toggleButton.addEventListener('click', () => {
            chatContainer.style.display = 'flex';
            toggleContainer.style.display = 'none';
            this.state.isChatOpen = true;
            this.saveChatState();
            if (!this.state.hasBeenOpened) {
                this.state.hasBeenOpened = true;
                this.showWelcomeMessage();
                this.saveChatState();
            }
        });

        // Event delegation for custom question buttons inside messages
        messageArea.addEventListener('click', (e) => {
            const target = e.target;
            if (target.classList.contains('custom-question') && !target.id) {
                const question = target.textContent;
                this.addMessage(question, 'user');
                this.sendToBackend(question);
            }
        });

        // Add expand functionality
        const expandButton = document.querySelector('.expand-button');
        
        // Create overlay element
        const overlay = document.createElement('div');
        overlay.className = 'chatbot-overlay';
        document.body.appendChild(overlay);

        expandButton.addEventListener('click', () => {
            this.isExpanded = !this.isExpanded;
            if (this.isExpanded) {
                chatContainer.classList.add('expanded');
                overlay.style.display = 'block';
                expandButton.querySelector('svg').style.transform = 'rotate(180deg)';
            } else {
                chatContainer.classList.remove('expanded');
                overlay.style.display = 'none';
                expandButton.querySelector('svg').style.transform = 'rotate(0deg)';
            }
        });

        // Close expanded view when clicking overlay
        overlay.addEventListener('click', () => {
            if (this.isExpanded) {
                this.isExpanded = false;
                chatContainer.classList.remove('expanded');
                overlay.style.display = 'none';
                expandButton.querySelector('svg').style.transform = 'rotate(0deg)';
            }
        });
    }

    async sendToBackend(message) {
        console.log('Sending message to:', this.apiEndpoint);
        try {
            // Se il messaggio contiene una richiesta di prenotazione
            if (message.toLowerCase().includes('prenot') || message.toLowerCase().includes('book')) {
                // Inizia il flusso di prenotazione conversazionale
                this.startBookingFlow();
                return;
            }

            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    chatbotId: this.chatbotId,
                    message: message,
                    tone: this.tone,
                    conversationHistory: this.state.messages
                })
            });

            if (!response.ok) {
                throw new Error(`Errore nella risposta del server: ${response.status}`);
            }

            const data = await response.json();
            console.log('Response data:', data);
            
            if (data.status === 'success') {
                this.addMessage(data.response, 'bot');
            } else {
                throw new Error(data.error || 'Formato risposta non valido');
            }
        } catch (error) {
            console.error('Error details:', error);
            this.addMessage('Mi dispiace, si è verificato un errore nella comunicazione con il server.', 'bot');
        }
    }

    loadDateRangePickerDependencies() {
        return new Promise((resolve) => {
            // Carica Flatpickr CSS
            const flatpickrCSS = document.createElement('link');
            flatpickrCSS.rel = 'stylesheet';
            flatpickrCSS.href = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css';
            document.head.appendChild(flatpickrCSS);

            // Carica tema custom per Flatpickr
            const flatpickrThemeCSS = document.createElement('link');
            flatpickrThemeCSS.rel = 'stylesheet';
            flatpickrThemeCSS.href = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css';
            document.head.appendChild(flatpickrThemeCSS);

            // Carica Flatpickr JS
            const flatpickrScript = document.createElement('script');
            flatpickrScript.src = 'https://cdn.jsdelivr.net/npm/flatpickr';
            
            flatpickrScript.onload = () => {
                // Carica la localizzazione italiana
                const flatpickrLocale = document.createElement('script');
                flatpickrLocale.src = 'https://npmcdn.com/flatpickr/dist/l10n/it.js';
                flatpickrLocale.onload = () => resolve();
                document.head.appendChild(flatpickrLocale);
            };
            
            document.head.appendChild(flatpickrScript);
        });
    }

    async startBookingFlow() {
        // Non carichiamo librerie esterne, usiamo nativamente gli input type="date".
        this.bookingData = {};
        
        this.addMessage("Fantastico! Ti aiuterò a prenotare il tuo soggiorno. 😊", 'bot');
        this.addMessage("Quando vorresti fare il check-in?", 'bot');
        
        const dateContainer = document.createElement('div');
        // Data minima per il check-in (oggi)
        const today = new Date().toISOString().split('T')[0];
        
        dateContainer.innerHTML = `
            <div class="booking-input-container">
                <input type="date" class="booking-date-input" id="checkinDate" 
                       placeholder="Data check-in"
                       min="${today}"
                       style="width: 100%; padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 14px; margin: 8px 0; background-color: white;">
                <button type="button" id="confirmCheckin" disabled
                        style="width: 100%; margin-top: 8px; padding: 8px; background: ${this.palettes[this.palette].primary}; color: white; border: none; border-radius: 6px; cursor: pointer; opacity: 0.5;">
                    Conferma data check-in
                </button>
            </div>
        `;
        
        const messages = document.querySelector('.chatbot-messages');
        messages.appendChild(dateContainer);
        messages.scrollTop = messages.scrollHeight;
        
        const checkinInput = dateContainer.querySelector('#checkinDate');
        const confirmCheckinBtn = dateContainer.querySelector('#confirmCheckin');
        
        checkinInput.addEventListener('change', () => {
            if (checkinInput.value) {
                confirmCheckinBtn.disabled = false;
                confirmCheckinBtn.style.opacity = '1';
                this.bookingData.checkin = checkinInput.value;
            } else {
                confirmCheckinBtn.disabled = true;
                confirmCheckinBtn.style.opacity = '0.5';
            }
        });
        
        confirmCheckinBtn.addEventListener('click', () => {
            if (this.bookingData.checkin) {
                const checkinDateFormatted = new Date(this.bookingData.checkin).toLocaleDateString('it-IT');
                this.addMessage(`Check-in il ${checkinDateFormatted}`, 'user');
                checkinInput.disabled = true;
                confirmCheckinBtn.disabled = true;
                confirmCheckinBtn.style.opacity = '0.5';
                
                this.addMessage("Perfetto! Quando vorresti fare il check-out?", 'bot');
                
                const checkoutContainer = document.createElement('div');
                // Calcola la data minima per il check-out (check-in + 1 giorno)
                let minCheckout = new Date(this.bookingData.checkin);
                minCheckout.setDate(minCheckout.getDate() + 1);
                minCheckout = minCheckout.toISOString().split('T')[0];
                
                checkoutContainer.innerHTML = `
                    <div class="booking-input-container">
                        <input type="date" class="booking-date-input" id="checkoutDate" 
                               placeholder="Data check-out"
                               min="${minCheckout}"
                               style="width: 100%; padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 14px; margin: 8px 0; background-color: white;">
                        <button type="button" id="confirmCheckout" disabled
                                style="width: 100%; margin-top: 8px; padding: 8px; background: ${this.palettes[this.palette].primary}; color: white; border: none; border-radius: 6px; cursor: pointer; opacity: 0.5;">
                            Conferma data check-out
                        </button>
                    </div>
                `;
                
                messages.appendChild(checkoutContainer);
                messages.scrollTop = messages.scrollHeight;
                
                const checkoutInput = checkoutContainer.querySelector('#checkoutDate');
                const confirmCheckoutBtn = checkoutContainer.querySelector('#confirmCheckout');
                
                checkoutInput.addEventListener('change', () => {
                    if (checkoutInput.value) {
                        confirmCheckoutBtn.disabled = false;
                        confirmCheckoutBtn.style.opacity = '1';
                        this.bookingData.checkout = checkoutInput.value;
                    } else {
                        confirmCheckoutBtn.disabled = true;
                        confirmCheckoutBtn.style.opacity = '0.5';
                    }
                });
                
                confirmCheckoutBtn.addEventListener('click', () => {
                    if (this.bookingData.checkout) {
                        const checkoutDateFormatted = new Date(this.bookingData.checkout).toLocaleDateString('it-IT');
                        this.addMessage(`Check-out il ${checkoutDateFormatted}`, 'user');
                        checkoutInput.disabled = true;
                        confirmCheckoutBtn.disabled = true;
                        confirmCheckoutBtn.style.opacity = '0.5';
                        
                        // Continua con la richiesta del numero di adulti
                        this.addMessage("Quanti adulti soggiorneranno?", 'bot');
                        
                        const adultsSelect = document.createElement('div');
                        adultsSelect.innerHTML = `
                            <div class="booking-input-container">
                                <select class="booking-select" id="adults" 
                                        style="width: 100%; padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 14px; margin: 8px 0; background-color: white;">
                                    ${Array.from({length: 10}, (_, i) => `<option value="${i + 1}">${i + 1} adult${i === 0 ? 'o' : 'i'}</option>`).join('')}
                                </select>
                            </div>
                        `;
                        
                        messages.appendChild(adultsSelect);
                        messages.scrollTop = messages.scrollHeight;
                        
                        adultsSelect.querySelector('#adults').addEventListener('change', (e) => {
                            const adultsCount = e.target.value;
                            this.bookingData.adults = adultsCount;
                            
                            this.addMessage(`Ho selezionato ${adultsCount} adult${adultsCount === '1' ? 'o' : 'i'}.`, 'user');
                            this.addMessage("Ci saranno bambini? Se sì, quanti?", 'bot');
                            
                            const childrenSelect = document.createElement('div');
                            childrenSelect.innerHTML = `
                                <div class="booking-input-container">
                                    <select class="booking-select" id="children"
                                            style="width: 100%; padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 14px; margin: 8px 0; background-color: white;">
                                        ${Array.from({length: 11}, (_, i) => `<option value="${i}">${i} bambin${i === 1 ? 'o' : 'i'}</option>`).join('')}
                                    </select>
                                </div>
                            `;
                            
                            messages.appendChild(childrenSelect);
                            messages.scrollTop = messages.scrollHeight;
                            
                            childrenSelect.querySelector('#children').addEventListener('change', (e) => {
                                const childrenCount = e.target.value;
                                this.bookingData.children = childrenCount;
                                
                                this.addMessage(`Ho selezionato ${childrenCount} bambin${childrenCount === '1' ? 'o' : 'i'}.`, 'user');
                                
                                // Reintroduce summary and booking link generation logic
                                this.addMessage(
                                    `Perfetto! Ecco il riepilogo della tua prenotazione 🎉\n` +
                                    `- Check-in: ${new Date(this.bookingData.checkin).toLocaleDateString('it-IT')}\n` +
                                    `- Check-out: ${new Date(this.bookingData.checkout).toLocaleDateString('it-IT')}\n` +
                                    `- Adulti: ${this.bookingData.adults}\n` +
                                    `- Bambini: ${this.bookingData.children}\n` +
                                    `Vuoi confermare questa prenotazione?\n\n` +
                                    `<div style="display: flex; gap: 8px; margin-top: 12px;">\n` +
                                    `  <button class="custom-question" id="confirmBooking">✅ Conferma prenotazione</button>\n` +
                                    `  <button class="custom-question" id="restartBooking">🔄 Ricomincia</button>\n` +
                                    `</div>`,
                                    'bot'
                                );
                                
                                setTimeout(() => {
                                    const confirmBtn = document.getElementById('confirmBooking');
                                    const restartBtn = document.getElementById('restartBooking');
                                    
                                    confirmBtn.addEventListener('click', async () => {
                                        try {
                                            const bookingUrl = await this.generateBookingUrl(this.bookingUrl, this.bookingData);
                                            this.addMessage(
                                                `Ottimo! Ho preparato il link per completare la prenotazione: ` +
                                                `<a href="${bookingUrl}" target="_blank" class="booking-link" style="color: ${this.palettes[this.palette].primary}; text-decoration: underline;">Clicca qui per procedere</a>`,
                                                'bot'
                                            );
                                        } catch (error) {
                                            this.addMessage('Mi dispiace, si è verificato un errore nella generazione del link di prenotazione. Riprova più tardi.', 'bot');
                                        }
                                    });
                                    
                                    restartBtn.addEventListener('click', () => {
                                        this.addMessage('Va bene, ricominciamo la prenotazione da capo!', 'bot');
                                        this.startBookingFlow();
                                    });
                                    
                                }, 100);
                            });
                        });
                    }
                });
            }
        });
    }

    async generateBookingUrl(baseUrl, formData) {
        try {
            const prompt = {
                role: "system",
                content: `Sei un esperto di sistemi di prenotazione online per hotel. 
                Analizza l'URL di prenotazione fornito e i dati inseriti dall'utente.
                Genera un nuovo URL valido mantenendo tutti i parametri necessari e aggiungendo/aggiornando i parametri di prenotazione.
                Rispondi SOLO con l'URL generato, senza spiegazioni o altro testo.`
            };

            const userPrompt = {
                role: "user",
                content: `URL di base: ${baseUrl}
                Dati prenotazione:
                - Check-in: ${formData.checkin}
                - Check-out: ${formData.checkout}
                - Adulti: ${formData.adults}
                - Bambini: ${formData.children}`
            };

            const response = await fetch(this.apiEndpoint.replace('chat.php', 'booking_url.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    messages: [prompt, userPrompt],
                    baseUrl: baseUrl,
                    formData: formData
                })
            });

            if (!response.ok) {
                throw new Error('Errore nella generazione dell\'URL di prenotazione');
            }

            const data = await response.json();
            if (data.bookingUrl) {
                return data.bookingUrl;
            }
            
            throw new Error('URL di prenotazione non valido nella risposta');
            
        } catch (error) {
            console.error('Error generating booking URL:', error);
            return baseUrl;
        }
    }
}

// Funzione di inizializzazione globale
window.initChatbot = function(chatbotId, customQuestions = [], tone = 'professionale', palette = 'indigo', logo = null, bookingUrl = null) {
    new ChatbotWidget(chatbotId, customQuestions, tone, palette, logo, bookingUrl);
};