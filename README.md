# AI Chatbot Dashboard

Una dashboard per creare e gestire chatbot AI che possono essere integrati in qualsiasi sito web. Il sistema effettua lo scraping automatico dei contenuti del sito per fornire risposte contestuali accurate.

## Caratteristiche

- Dashboard per la gestione dei chatbot
- Scraping automatico dei contenuti del sito
- Widget JavaScript personalizzabile
- Integrazione con Supabase per il database
- API RESTful per la comunicazione con il chatbot
- Interfaccia utente moderna e responsive

## Requisiti

- PHP 7.4 o superiore
- Estensione PHP cURL
- Account Supabase (gratuito o a pagamento)
- Account OpenAI per l'integrazione con GPT (opzionale)

## Installazione

1. Clona il repository:
```bash
git clone [url-repository]
```

2. Configura le credenziali Supabase:
   - Copia `config/database.php.example` in `config/database.php`
   - Inserisci l'URL e la chiave API del tuo progetto Supabase

3. Crea le tabelle necessarie in Supabase:

```sql
-- Tabella dei chatbot
create table chatbots (
  id uuid default uuid_generate_v4() primary key,
  name text not null,
  website_url text not null,
  status text not null,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- Tabella delle pagine scrapate
create table scraped_pages (
  id uuid default uuid_generate_v4() primary key,
  chatbot_id uuid references chatbots(id),
  url text not null,
  content jsonb not null,
  scraped_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- Tabella delle pagine da scrapare
create table pages_to_scrape (
  id uuid default uuid_generate_v4() primary key,
  chatbot_id uuid references chatbots(id),
  url text not null,
  status text not null,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- Tabella delle conversazioni
create table conversations (
  id uuid default uuid_generate_v4() primary key,
  chatbot_id uuid references chatbots(id),
  user_message text not null,
  bot_response text not null,
  timestamp timestamp with time zone default timezone('utc'::text, now()) not null
);
```

## Utilizzo

1. Accedi alla dashboard tramite browser
2. Crea un nuovo chatbot inserendo l'URL del sito da integrare
3. Attendi il completamento dello scraping
4. Copia lo script di integrazione generato
5. Incolla lo script nel tuo sito web prima della chiusura del tag `</body>`

Esempio di integrazione:
```html
<script src="[URL_BASE]/public/js/chatbot-widget.js"></script>
<script>
  window.initChatbot('[CHATBOT_ID]');
</script>
```

## Personalizzazione

Il widget del chatbot può essere personalizzato modificando il file `public/js/chatbot-widget.js`. Puoi modificare:

- Colori e stili
- Posizione del widget
- Dimensioni della finestra di chat
- Messaggi predefiniti
- Comportamento di apertura/chiusura

## Sicurezza

- Implementa l'autenticazione per la dashboard
- Configura correttamente i CORS headers
- Non esporre le chiavi API nel codice client
- Utilizza HTTPS per tutte le comunicazioni
- Implementa rate limiting per le API

## Contribuire

Sei libero di contribuire al progetto attraverso pull request. Per modifiche importanti, apri prima una issue per discutere le modifiche proposte.

## Licenza

MIT License - vedi il file LICENSE per i dettagli. # chatbotAI
# ChatbotAI
