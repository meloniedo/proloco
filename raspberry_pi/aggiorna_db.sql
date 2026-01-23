-- ========================================
-- AGGIORNAMENTO DATABASE PROLOCO
-- Esegui con: sudo mysql -u edo -p5054 proloco_bar < aggiorna_db.sql
-- ========================================

-- Aggiungi nuova categoria "CIBO E SNACK" alla tabella prodotti
ALTER TABLE prodotti MODIFY COLUMN categoria ENUM('CAFFETTERIA', 'BEVANDE', 'GELATI', 'CIBO E SNACK', 'PERSONALIZZATE') NOT NULL;

-- Aggiungi prodotto Pop Corn se non esiste
INSERT IGNORE INTO prodotti (nome, prezzo, categoria, icona, attivo) 
VALUES ('Pop Corn', 3.00, 'CIBO E SNACK', '🍿', 1);

-- Mostra risultato
SELECT 'Database aggiornato con successo!' AS risultato;
SELECT * FROM prodotti WHERE categoria = 'CIBO E SNACK';
