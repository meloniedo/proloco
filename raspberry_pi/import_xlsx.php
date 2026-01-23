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
 * 
 * STRUTTURA FILE: UN SOLO FOGLIO con:
 * - Sezione VENDITE (in alto)
 * - TOTALE VENDITE
 * - Riga "SPESE"
 * - Header spese
 * - Sezione SPESE (in basso)
 * - TOTALE SPESE
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

// Leggi UNICO foglio (sheet1)
$allRows = readSheet($zip, 1, $sharedStrings);
echo "Righe totali nel foglio: " . count($allRows) . "\n";

// PARSING A STATI: vendite -> spese
$modalita = 'none'; // 'vendite', 'spese', 'attesa_spese', 'attesa_header_spese', 'none'
$venditeImportate = 0;
$venditeErrori = 0;
$speseImportate = 0;
$speseErrori = 0;

$stmtVendita = $pdo->prepare("INSERT INTO vendite (nome_prodotto, prezzo, categoria, timestamp) VALUES (?, ?, ?, ?)");
$stmtSpesa = $pdo->prepare("INSERT INTO spese (categoria_spesa, importo, note, timestamp) VALUES (?, ?, ?, ?)");

echo BLUE . "\n📊 Analisi e importazione dati...\n" . RESET;

for ($i = 0; $i < count($allRows); $i++) {
    $row = $allRows[$i];
    $firstCell = trim($row[0] ?? '');
    $thirdCell = trim($row[2] ?? '');
    
    // Rileva intestazione VENDITE: riga con "Data" e "Prodotto"
    if ($firstCell === 'Data' && $thirdCell === 'Prodotto') {
        $modalita = 'vendite';
        echo YELLOW . "  📍 Trovata intestazione VENDITE alla riga " . ($i + 1) . "\n" . RESET;
        continue;
    }
    
    // Rileva "TOTALE VENDITE" - passa a modalità attesa spese
    if (stripos($firstCell, 'TOTALE VENDITE') !== false) {
        $modalita = 'attesa_spese';
        echo YELLOW . "  📍 Trovato TOTALE VENDITE alla riga " . ($i + 1) . "\n" . RESET;
        continue;
    }
    
    // Rileva riga singola "SPESE" che indica inizio sezione spese
    if ($firstCell === 'SPESE' || ($modalita === 'attesa_spese' && stripos($firstCell, 'SPESE') !== false && stripos($firstCell, 'TOTALE') === false)) {
        $modalita = 'attesa_header_spese';
        echo YELLOW . "  📍 Trovata riga SPESE alla riga " . ($i + 1) . "\n" . RESET;
        continue;
    }
    
    // Rileva intestazione SPESE: riga con "Data" nella prima colonna (dopo riga SPESE)
    if (($modalita === 'attesa_spese' || $modalita === 'attesa_header_spese') && $firstCell === 'Data') {
        $modalita = 'spese';
        echo YELLOW . "  📍 Trovata intestazione SPESE alla riga " . ($i + 1) . "\n" . RESET;
        continue;
    }
    
    // Rileva "TOTALE SPESE" - fine parsing spese
    if (stripos($firstCell, 'TOTALE SPESE') !== false) {
        $modalita = 'none';
        echo YELLOW . "  📍 Trovato TOTALE SPESE alla riga " . ($i + 1) . "\n" . RESET;
        continue;
    }
    
    // Rileva RIEPILOGO - fine parsing
    if (stripos($firstCell, 'RIEPILOGO') !== false) {
        $modalita = 'none';
        continue;
    }
    
    // Salta righe vuote o non valide (deve iniziare con una data)
    if (empty($firstCell) || !preg_match('/\d{1,2}\/\d{1,2}\/\d{4}/', $firstCell)) {
        continue;
    }
    
    // IMPORTA VENDITE
    if ($modalita === 'vendite') {
        // Struttura VENDITE: Data(0), Ora(1), Prodotto(2), Categoria(3), Importo(4)
        if (count($row) < 5) continue;
        
        $data = $row[0];
        $ora = $row[1];
        $prodotto = trim($row[2]);
        $categoria = trim($row[3]);
        $importo = floatval(str_replace(',', '.', $row[4]));
        
        if (empty($prodotto) || $importo <= 0) continue;
        
        $timestamp = excelDateToMysql($data, $ora);
        
        try {
            $stmtVendita->execute([$prodotto, $importo, $categoria, $timestamp]);
            $venditeImportate++;
        } catch (Exception $e) {
            $venditeErrori++;
        }
    }
    // IMPORTA SPESE
    elseif ($modalita === 'spese') {
        // Struttura SPESE: Data(0), Ora(1), Categoria(2), [vuoto](3), Importo(4)
        $data = $row[0];
        $ora = $row[1];
        $categoria = trim($row[2]);
        
        // Cerca l'importo - potrebbe essere in colonna 4 o oltre
        $importo = 0;
        for ($col = 3; $col < count($row); $col++) {
            $val = str_replace(',', '.', trim($row[$col]));
            if (is_numeric($val) && floatval($val) > 0) {
                $importo = floatval($val);
                break;
            }
        }
        
        if (empty($categoria) || $importo <= 0) continue;
        
        $timestamp = excelDateToMysql($data, $ora);
        
        try {
            $stmtSpesa->execute([$categoria, $importo, '', $timestamp]);
            $speseImportate++;
        } catch (Exception $e) {
            $speseErrori++;
        }
    }
}

$zip->close();

echo GREEN . "\n  ✅ Vendite importate: $venditeImportate\n" . RESET;
if ($venditeErrori > 0) {
    echo YELLOW . "  ⚠️ Vendite con errori: $venditeErrori\n" . RESET;
}
echo GREEN . "  ✅ Spese importate: $speseImportate\n" . RESET;
if ($speseErrori > 0) {
    echo YELLOW . "  ⚠️ Spese con errori: $speseErrori\n" . RESET;
}

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
