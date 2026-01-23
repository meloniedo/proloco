-- ========================================
-- DATABASE PROLOCO BAR MANAGER
-- Per MySQL su Raspberry Pi
-- ========================================

CREATE DATABASE IF NOT EXISTS proloco_bar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE proloco_bar;

-- Tabella Prodotti
CREATE TABLE IF NOT EXISTS prodotti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    prezzo DECIMAL(10,2) DEFAULT 0.00,
    categoria ENUM('CAFFETTERIA', 'BEVANDE', 'GELATI', 'CIBO E SNACK', 'PERSONALIZZATE') NOT NULL,
    icona VARCHAR(10) DEFAULT '📦',
    attivo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabella Vendite
CREATE TABLE IF NOT EXISTS vendite (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prodotto_id INT,
    nome_prodotto VARCHAR(100) NOT NULL,
    prezzo DECIMAL(10,2) NOT NULL,
    categoria VARCHAR(50),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabella Spese
CREATE TABLE IF NOT EXISTS spese (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_spesa VARCHAR(100) NOT NULL,
    importo DECIMAL(10,2) NOT NULL,
    note TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabella Configurazione
CREATE TABLE IF NOT EXISTS configurazione (
    chiave VARCHAR(100) PRIMARY KEY,
    valore TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ========================================
-- DATI INIZIALI (solo se tabella vuota)
-- ========================================

-- Prodotti di default (INSERT IGNORE evita duplicati)
INSERT IGNORE INTO prodotti (nome, prezzo, categoria, icona) VALUES
-- CAFFETTERIA
('Caffè', 1.20, 'CAFFETTERIA', '☕'),
('Caffè Deca', 1.20, 'CAFFETTERIA', '☕'),
('Caffè Corretto', 2.00, 'CAFFETTERIA', '🥃'),
-- BEVANDE
('Caraffa di Vino 0,5L', 6.00, 'BEVANDE', '🍷'),
('Caraffa di Vino 1LT', 11.00, 'BEVANDE', '🍷'),
('Calice di Vino', 1.50, 'BEVANDE', '🍷'),
('Sguazzone', 1.00, 'BEVANDE', '🍷'),
('Liquori, Grappe, Vodka e Amari', 2.50, 'BEVANDE', '🥃'),
('Bibite in Lattina', 2.20, 'BEVANDE', '🥤'),
('Bottiglietta d''Acqua', 1.00, 'BEVANDE', '💧'),
-- GELATI
('Cremino', 1.20, 'GELATI', '🍦'),
('Cucciolone', 1.50, 'GELATI', '🍦'),
('Magnum, Soia e altri Gelati', 2.00, 'GELATI', '🍦'),
-- PERSONALIZZATE
('Bigliardo', 0.00, 'PERSONALIZZATE', '🎱'),
('Extra', 0.00, 'PERSONALIZZATE', '➕');

-- Configurazione iniziale
INSERT INTO configurazione (chiave, valore) VALUES
('nome_bar', 'Proloco Santa Bianca'),
('email_destinatari', 'alberto.melorec@gmail.com,meloni.edo@gmail.com'),
('password_reset', '5054'),
('ultimo_resoconto_inviato', ''),
('invio_resoconto_automatico', 'true'),
('giorno_invio_report', '1'),
('ora_invio_report', '8'),
('giorni_minimo_tra_report', '7'),
('backup_giorni', '0'),
('backup_ora', '23:59')
ON DUPLICATE KEY UPDATE chiave=chiave;
