#!/usr/bin/php
<?php
/**
 * ========================================
 * SCRIPT IMPORTAZIONE XLSX
 * ========================================
 * Uso: php import_xlsx.php /percorso/al/file.xlsx
 * 
 * Importa vendite e spese da un file Excel esportato
 * dalla versione precedente dell'app.
 */

// Colori per output terminale
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

echo GREEN . "
╔══════════════════════════════════════════════════════════════╗
║         IMPORTATORE XLSX - PROLOCO SANTA BIANCA              ║
╚══════════════════════════════════════════════════════════════╝
" . RESET;

// Verifica argomenti
if ($argc < 2) {
    echo RED . "❌ Errore: Specifica il percorso del file xlsx\n" . RESET;
    echo "Uso: php import_xlsx.php /percorso/al/file.xlsx\n\n";
    exit(1);
}

$filePath = $argv[1];

if (!file_exists($filePath)) {
    echo RED . "❌ Errore: File non trovato: $filePath\n" . RESET;
    exit(1);
}

// Connessione database
require_once __DIR__ . '/includes/config.php';

try {
    $pdo = getDB();
    echo GREEN . "✅ Connessione database OK\n" . RESET;
} catch (Exception $e) {
    echo RED . "❌ Errore connessione DB: " . $e->getMessage() . "\n" . RESET;
    exit(1);
}

// Leggi file xlsx
echo BLUE . "\n📂 Lettura file: $filePath\n" . RESET;

// Usa ZipArchive per leggere xlsx
$zip = new ZipArchive();
if ($zip->open($filePath) !== true) {
    echo RED . "❌ Errore: Impossibile aprire il file xlsx\n" . RESET;
    exit(1);
}

// Leggi shared strings
$sharedStrings = [];
$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
if ($sharedStringsXml) {
    $ssXml = simplexml_load_string($sharedStringsXml);
    foreach ($ssXml->si as $si) {
        $sharedStrings[] = (string)$si->t;
    }
}

// Leggi workbook per nomi fogli
$workbookXml = $zip->getFromName('xl/workbook.xml');
$workbook = simplexml_load_string($workbookXml);
$sheetNames = [];
foreach ($workbook->sheets->sheet as $sheet) {
    $sheetNames[] = (string)$sheet['name'];
}

echo YELLOW . "📋 Fogli trovati: " . implode(', ', $sheetNames) . "\n" . RESET;

// Funzione per convertire riferimento cella in indice colonna
function colToIndex($col) {
    $col = strtoupper($col);
    $index = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

// Funzione per leggere un foglio
function readSheet($zip, $sheetNum, $sharedStrings) {
    $sheetXml = $zip->getFromName("xl/worksheets/sheet{$sheetNum}.xml");
    if (!$sheetXml) return [];
    
    $sheet = simplexml_load_string($sheetXml);
    $rows = [];
    
    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        $maxCol = 0;
        
        foreach ($row->c as $cell) {
            $cellRef = (string)$cell['r'];
            preg_match('/([A-Z]+)(\d+)/', $cellRef, $matches);
            $colIndex = colToIndex($matches[1]);
            $maxCol = max($maxCol, $colIndex);
            
            $value = '';
            if (isset($cell->v)) {
                $value = (string)$cell->v;
                // Se è un riferimento a shared string
                if (isset($cell['t']) && (string)$cell['t'] === 's') {
                    $value = $sharedStrings[(int)$value] ?? $value;
                }
            }
            $rowData[$colIndex] = $value;
        }
        
        // Riempi celle vuote
        for ($i = 0; $i <= $maxCol; $i++) {
            if (!isset($rowData[$i])) $rowData[$i] = '';
        }
        ksort($rowData);
        $rows[] = array_values($rowData);
    }
    
    return $rows;
}

// Funzione per convertire data Excel in timestamp MySQL
function excelDateToMysql($excelDate, $time = '') {
    // Se è già in formato dd/mm/yyyy
    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $excelDate, $m)) {
        $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        if ($time) {
            return $date . ' ' . $time;
        }
        return $date . ' 12:00:00';
    }
    
    // Se è un numero seriale Excel
    if (is_numeric($excelDate)) {
        $unixDate = ($excelDate - 25569) * 86400;
        $date = date('Y-m-d', $unixDate);
        if ($time) {
            return $date . ' ' . $time;
        }
        return $date . ' 12:00:00';
    }
    
    return date('Y-m-d H:i:s');
}

// Leggi foglio VENDITE (sheet1)
echo BLUE . "\n📊 Importazione VENDITE...\n" . RESET;
$venditeRows = readSheet($zip, 1, $sharedStrings);

$venditeImportate = 0;
$venditeErrori = 0;

// Salta intestazione (prima riga)
$stmtVendita = $pdo->prepare("INSERT INTO vendite (nome_prodotto, prezzo, categoria, timestamp) VALUES (?, ?, ?, ?)");

for ($i = 1; $i < count($venditeRows); $i++) {
    $row = $venditeRows[$i];
    
    // Struttura: Data, Ora, Prodotto, Categoria, Importo
    if (count($row) < 5) continue;
    if (empty($row[2])) continue; // Salta righe senza prodotto
    
    $data = $row[0];
    $ora = $row[1];
    $prodotto = trim($row[2]);
    $categoria = trim($row[3]);
    $importo = floatval(str_replace(',', '.', $row[4]));
    
    // Salta righe di totale o intestazioni
    if (empty($prodotto) || stripos($prodotto, 'TOTALE') !== false) continue;
    if ($importo <= 0) continue;
    
    $timestamp = excelDateToMysql($data, $ora);
    
    try {
        $stmtVendita->execute([$prodotto, $importo, $categoria, $timestamp]);
        $venditeImportate++;
    } catch (Exception $e) {
        $venditeErrori++;
        echo RED . "  ⚠️ Errore riga $i: " . $e->getMessage() . "\n" . RESET;
    }
}

echo GREEN . "  ✅ Vendite importate: $venditeImportate\n" . RESET;
if ($venditeErrori > 0) {
    echo YELLOW . "  ⚠️ Errori: $venditeErrori\n" . RESET;
}

// Leggi foglio SPESE (sheet2)
echo BLUE . "\n💸 Importazione SPESE...\n" . RESET;
$speseRows = readSheet($zip, 2, $sharedStrings);

$speseImportate = 0;
$speseErrori = 0;

// Struttura effettiva: Data, Ora, (vuoto), Spesa/Categoria, Note, Importo
// La colonna "Categoria" (indice 2) è vuota, "Spesa" (indice 3) contiene il nome categoria
$stmtSpesa = $pdo->prepare("INSERT INTO spese (categoria_spesa, importo, note, timestamp) VALUES (?, ?, ?, ?)");

for ($i = 1; $i < count($speseRows); $i++) {
    $row = $speseRows[$i];
    
    if (count($row) < 6) continue;
    
    $data = $row[0];
    $ora = $row[1];
    // Colonna 2 è "Categoria" (vuota), colonna 3 è "Spesa" che contiene il nome della categoria
    $categoria = trim($row[3]); 
    $note = trim($row[4]);
    $importo = floatval(str_replace(',', '.', $row[5]));
    
    // Salta righe di totale o senza categoria
    if (empty($categoria) || stripos($categoria, 'TOTALE') !== false) continue;
    if ($importo <= 0) continue;
    
    $timestamp = excelDateToMysql($data, $ora);
    
    try {
        $stmtSpesa->execute([$categoria, $importo, $note, $timestamp]);
        $speseImportate++;
    } catch (Exception $e) {
        $speseErrori++;
        echo RED . "  ⚠️ Errore riga $i: " . $e->getMessage() . "\n" . RESET;
    }
}

echo GREEN . "  ✅ Spese importate: $speseImportate\n" . RESET;
if ($speseErrori > 0) {
    echo YELLOW . "  ⚠️ Errori: $speseErrori\n" . RESET;
}

$zip->close();

// Riepilogo finale
echo GREEN . "
╔══════════════════════════════════════════════════════════════╗
║                    IMPORTAZIONE COMPLETATA                    ║
╠══════════════════════════════════════════════════════════════╣
║  Vendite importate:  " . str_pad($venditeImportate, 8) . "                            ║
║  Spese importate:    " . str_pad($speseImportate, 8) . "                            ║
╚══════════════════════════════════════════════════════════════╝
" . RESET;

// Aggiorna STORICO.txt
echo BLUE . "\n🔄 Aggiornamento STORICO.txt...\n" . RESET;
require_once __DIR__ . '/includes/storico_txt.php';
sincronizzaDBversoTXT();
echo GREEN . "✅ STORICO.txt aggiornato\n" . RESET;

echo "\n" . GREEN . "🎉 Fatto! Puoi ora aprire l'app per vedere i dati importati.\n" . RESET;
