-- Create database if not exists
CREATE DATABASE IF NOT EXISTS apichatbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE apichatbot;

-- Chatbots table
CREATE TABLE IF NOT EXISTS chatbots (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    website_url VARCHAR(255) NOT NULL,
    status ENUM('crawling', 'url_selection', 'scraping', 'completed', 'error') DEFAULT 'crawling',
    scraping_depth INT DEFAULT 2,
    max_pages INT DEFAULT 50,
    tone_of_voice ENUM('professionale', 'amichevole', 'informale', 'formale', 'entusiasta') DEFAULT 'professionale',
    color_palette ENUM('indigo', 'emerald', 'rose', 'amber', 'sky') DEFAULT 'indigo',
    logo_url VARCHAR(255) DEFAULT NULL,
    booking_online_url TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabella per le URL scoperte durante il crawling
CREATE TABLE IF NOT EXISTS discovered_urls (
    id CHAR(36) PRIMARY KEY,
    chatbot_id CHAR(36) NOT NULL,
    url VARCHAR(255) NOT NULL,
    title VARCHAR(255),
    depth INT DEFAULT 0,
    is_selected BOOLEAN DEFAULT FALSE,
    discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    UNIQUE KEY unique_url_per_chatbot (chatbot_id, url),
    INDEX idx_chatbot_selected (chatbot_id, is_selected)
);

-- Scraping queue table
CREATE TABLE IF NOT EXISTS scraping_queue (
    id CHAR(36) PRIMARY KEY,
    chatbot_id CHAR(36) NOT NULL,
    url VARCHAR(255) NOT NULL,
    depth INT DEFAULT 0,
    status ENUM('pending', 'processing', 'completed', 'error') DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    UNIQUE KEY unique_url_per_chatbot (chatbot_id, url),
    INDEX idx_status (status),
    INDEX idx_chatbot_status (chatbot_id, status)
);

-- Scraped pages table
CREATE TABLE IF NOT EXISTS scraped_pages (
    id CHAR(36) PRIMARY KEY,
    chatbot_id CHAR(36) NOT NULL,
    url VARCHAR(255) NOT NULL,
    content LONGTEXT,
    scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    UNIQUE KEY unique_url_per_chatbot (chatbot_id, url),
    INDEX idx_chatbot_url (chatbot_id, url)
);

-- Conversations table
CREATE TABLE IF NOT EXISTS conversations (
    id CHAR(36) PRIMARY KEY,
    chatbot_id CHAR(36) NOT NULL,
    user_message TEXT NOT NULL,
    bot_response TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    INDEX idx_chatbot_timestamp (chatbot_id, timestamp)
);

-- Tabella delle domande personalizzate
CREATE TABLE IF NOT EXISTS custom_questions (
    id CHAR(36) PRIMARY KEY,
    chatbot_id CHAR(36) NOT NULL,
    question TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    INDEX idx_chatbot (chatbot_id)
); 