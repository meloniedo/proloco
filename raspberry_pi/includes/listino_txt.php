<?php
/**
 * ========================================
 * GESTIONE LISTINO.txt
 * Sincronizzazione bidirezionale tra DB e file TXT
 * ========================================
 */

$baseDir = dirname(__DIR__);
$listinoFile = $baseDir . '/LISTINO.txt';

/**
 * Genera il contenuto del file LISTINO.txt dal database
 */
function generaListinoTXT() {
    global $listinoFile;
    
    try {
        require_once __DIR__ . '/config.php';
        $pdo = getDB();
        
        $prodotti = $pdo->query("SELECT * FROM prodotti WHERE attivo = 1 ORDER BY 
            CASE categoria 
                WHEN 'CAFFETTERIA' THEN 1 
                WHEN 'BEVANDE' THEN 2 
                WHEN 'GELATI' THEN 3 
                WHEN 'CIBO E SNACK' THEN 4 
                WHEN 'PERSONALIZZATE' THEN 5 
            END, nome")->fetchAll(PDO::FETCH_ASSOC);
        
        $content = "# ════════════════════════════════════════════════════════════════\n";
        $content .= "# LISTINO PROLOCO SANTA BIANCA\n";
        $content .= "# Ultimo aggiornamento: " . date('d/m/Y H:i:s') . "\n";
        $content .= "# ════════════════════════════════════════════════════════════════\n";
        $content .= "#\n";
        $content .= "# ISTRUZIONI:\n";
        $content .= "# - Ogni riga rappresenta un prodotto\n";
        $content .= "# - Formato: ICONA | NOME | PREZZO\n";
        $content .= "# - Per aggiungere: scrivi una nuova riga nella categoria giusta\n";
        $content .= "# - Per rimuovere: cancella la riga\n";
        $content .= "# - Le righe che iniziano con # sono commenti (ignorate)\n";
        $content .= "# - Il file viene sincronizzato automaticamente ogni minuto\n";
        $content .= "#\n";
        $content .= "# ════════════════════════════════════════════════════════════════\n\n";
        
        $categoriaCorrente = '';
        foreach ($prodotti as $p) {
            if ($p['categoria'] !== $categoriaCorrente) {
                $categoriaCorrente = $p['categoria'];
                $content .= "\n";
                $content .= "═══════════════════════════════════════\n";
                $content .= "  " . $categoriaCorrente . "\n";
                $content .= "═══════════════════════════════════════\n";
            }
            $prezzo = number_format(floatval($p['prezzo']), 2, '.', '');
            $content .= $p['icona'] . " | " . $p['nome'] . " | " . $prezzo . "\n";
        }
        
        $content .= "\n\n# ════════════════════════════════════════════════════════════════\n";
        $content .= "# FINE LISTINO\n";
        $content .= "# ════════════════════════════════════════════════════════════════\n";
        
        file_put_contents($listinoFile, $content);
        return true;
        
    } catch (Exception $e) {
        error_log("Errore generazione LISTINO.txt: " . $e->getMessage());
        return false;
    }
}

/**
 * Legge il file LISTINO.txt e restituisce array di prodotti
 */
function leggiListinoTXT() {
    global $listinoFile;
    
    if (!file_exists($listinoFile)) {
        return [];
    }
    
    $prodotti = [];
    $categoriaCorrente = '';
    $lines = file($listinoFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Salta commenti e righe vuote
        if (empty($line) || $line[0] === '#') continue;
        
        // Rileva intestazione categoria
        if (strpos($line, '═') !== false) continue;
        
        // Rileva nome categoria
        if (preg_match('/^\s*(CAFFETTERIA|BEVANDE|GELATI|CIBO E SNACK|PERSONALIZZATE)\s*$/i', $line, $matches)) {
            $categoriaCorrente = strtoupper(trim($matches[1]));
            continue;
        }
        
        // Parse riga prodotto: ICONA | NOME | PREZZO
        if (strpos($line, '|') !== false && !empty($categoriaCorrente)) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) >= 3) {
                $prodotti[] = [
                    'icona' => $parts[0],
                    'nome' => $parts[1],
                    'prezzo' => floatval(str_replace(',', '.', $parts[2])),
                    'categoria' => $categoriaCorrente
                ];
            }
        }
    }
    
    return $prodotti;
}

/**
 * Sincronizza il file LISTINO.txt con il database (bidirezionale)
 */
function sincronizzaListino() {
    global $listinoFile;
    
    try {
        require_once __DIR__ . '/config.php';
        $pdo = getDB();
        
        // Se il file non esiste, crealo dal DB
        if (!file_exists($listinoFile)) {
            generaListinoTXT();
            return ['action' => 'created', 'message' => 'File LISTINO.txt creato'];
        }
        
        // Leggi prodotti dal file
        $prodottiFile = leggiListinoTXT();
        
        // Leggi prodotti dal database
        $prodottiDB = $pdo->query("SELECT * FROM prodotti WHERE attivo = 1")->fetchAll(PDO::FETCH_ASSOC);
        
        // Crea indici per confronto
        $nomiFile = array_map(function($p) { return strtolower($p['nome']); }, $prodottiFile);
        $nomiDB = [];
        foreach ($prodottiDB as $p) {
            $nomiDB[strtolower($p['nome'])] = $p;
        }
        
        $aggiunti = 0;
        $rimossi = 0;
        $aggiornati = 0;
        
        // Aggiungi al DB i prodotti presenti nel file ma non nel DB
        foreach ($prodottiFile as $pFile) {
            $nomeKey = strtolower($pFile['nome']);
            
            if (!isset($nomiDB[$nomeKey])) {
                // Prodotto nuovo - aggiungi al DB
                $stmt = $pdo->prepare("INSERT INTO prodotti (nome, prezzo, categoria, icona, attivo) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$pFile['nome'], $pFile['prezzo'], $pFile['categoria'], $pFile['icona']]);
                $aggiunti++;
            } else {
                // Prodotto esistente - aggiorna se diverso
                $pDB = $nomiDB[$nomeKey];
                if ($pDB['prezzo'] != $pFile['prezzo'] || $pDB['icona'] != $pFile['icona'] || $pDB['categoria'] != $pFile['categoria']) {
                    $stmt = $pdo->prepare("UPDATE prodotti SET prezzo = ?, icona = ?, categoria = ? WHERE id = ?");
                    $stmt->execute([$pFile['prezzo'], $pFile['icona'], $pFile['categoria'], $pDB['id']]);
                    $aggiornati++;
                }
            }
        }
        
        // Disattiva nel DB i prodotti rimossi dal file
        foreach ($nomiDB as $nomeKey => $pDB) {
            if (!in_array($nomeKey, $nomiFile)) {
                // Prodotto rimosso dal file - disattiva nel DB
                $stmt = $pdo->prepare("UPDATE prodotti SET attivo = 0 WHERE id = ?");
                $stmt->execute([$pDB['id']]);
                $rimossi++;
            }
        }
        
        // Rigenera il file per avere formato consistente
        if ($aggiunti > 0 || $rimossi > 0 || $aggiornati > 0) {
            generaListinoTXT();
        }
        
        return [
            'action' => 'synced',
            'aggiunti' => $aggiunti,
            'rimossi' => $rimossi,
            'aggiornati' => $aggiornati
        ];
        
    } catch (Exception $e) {
        error_log("Errore sincronizzazione listino: " . $e->getMessage());
        return ['action' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Aggiorna il file LISTINO.txt dal database (DB -> FILE)
 */
function aggiornaListinoDaDB() {
    return generaListinoTXT();
}
