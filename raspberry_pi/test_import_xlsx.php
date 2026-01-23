#!/usr/bin/php
<?php
/**
 * ========================================
 * TEST IMPORTAZIONE XLSX (SENZA SCRIVERE NEL DB)
 * ========================================
 * Uso: php test_import_xlsx.php /percorso/al/file.xlsx
 * 
 * Questo script LEGGE il file e mostra cosa verrebbe importato
 * SENZA effettivamente scrivere nel database.
 * 
 * STRUTTURA FILE: UN SOLO FOGLIO con:
 * - Sezione VENDITE (in alto)
 * - TOTALE VENDITE
 * - Riga "SPESE"
 * - Header spese
 * - Sezione SPESE (in basso)
 * - TOTALE SPESE
 */

define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

echo GREEN . "
╔══════════════════════════════════════════════════════════════╗
║      TEST IMPORTATORE XLSX - PROLOCO SANTA BIANCA            ║
║         (Solo lettura - non modifica il database)            ║
╚══════════════════════════════════════════════════════════════╝
" . RESET;

if ($argc < 2) {
    echo RED . "❌ Errore: Specifica il percorso del file xlsx\n" . RESET;
    echo "Uso: php test_import_xlsx.php /percorso/al/file.xlsx\n\n";
    exit(1);
}

$filePath = $argv[1];

if (!file_exists($filePath)) {
    echo RED . "❌ Errore: File non trovato: $filePath\n" . RESET;
    exit(1);
}

echo BLUE . "\n📂 Lettura file: $filePath\n" . RESET;

// Funzioni helper
function colToIndex($col) {
    $col = strtoupper($col);
    $index = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

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
                if (isset($cell['t']) && (string)$cell['t'] === 's') {
                    $value = $sharedStrings[(int)$value] ?? $value;
                }
            }
            $rowData[$colIndex] = $value;
        }
        
        for ($i = 0; $i <= $maxCol; $i++) {
            if (!isset($rowData[$i])) $rowData[$i] = '';
        }
        ksort($rowData);
        $rows[] = array_values($rowData);
    }
    
    return $rows;
}

function excelDateToMysql($excelDate, $time = '') {
    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $excelDate, $m)) {
        $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        return $time ? $date . ' ' . $time : $date . ' 12:00:00';
    }
    if (is_numeric($excelDate)) {
        $unixDate = ($excelDate - 25569) * 86400;
        $date = date('Y-m-d', $unixDate);
        return $time ? $date . ' ' . $time : $date . ' 12:00:00';
    }
    return date('Y-m-d H:i:s');
}

// Apri file
$zip = new ZipArchive();
if ($zip->open($filePath) !== true) {
    echo RED . "❌ Errore: Impossibile aprire il file xlsx\n" . RESET;
    echo YELLOW . "   Verifica che il file sia un xlsx valido e non corrotto.\n" . RESET;
    exit(1);
}

echo GREEN . "✅ File aperto correttamente\n" . RESET;

// Leggi shared strings
$sharedStrings = [];
$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
if ($sharedStringsXml) {
    $ssXml = simplexml_load_string($sharedStringsXml);
    foreach ($ssXml->si as $si) {
        $sharedStrings[] = (string)$si->t;
    }
    echo GREEN . "✅ Shared strings lette: " . count($sharedStrings) . " stringhe\n" . RESET;
}

// Leggi nomi fogli
$workbookXml = $zip->getFromName('xl/workbook.xml');
$workbook = simplexml_load_string($workbookXml);
$sheetNames = [];
foreach ($workbook->sheets->sheet as $sheet) {
    $sheetNames[] = (string)$sheet['name'];
}
echo YELLOW . "\n📋 Fogli trovati:\n" . RESET;
foreach ($sheetNames as $i => $name) {
    echo "   " . ($i + 1) . ". $name\n";
}

// LEGGI L'UNICO FOGLIO (sheet1) - vendite E spese sono nello stesso foglio
echo BLUE . "\n" . str_repeat("─", 60) . "\n" . RESET;
echo BLUE . "📊 ANALISI FOGLIO UNICO (sheet1) - VENDITE E SPESE\n" . RESET;
echo BLUE . str_repeat("─", 60) . "\n" . RESET;

$allRows = readSheet($zip, 1, $sharedStrings);
echo "Righe totali nel foglio: " . count($allRows) . "\n";

// PARSING A STATI: vendite -> spese
$modalita = 'none'; // 'vendite', 'spese', 'none'
$venditeValide = 0;
$speseValide = 0;
$venditePreview = [];
$spesePreview = [];

echo YELLOW . "\n🔍 Scansione righe per individuare sezioni...\n" . RESET;

for ($i = 0; $i < count($allRows); $i++) {
    $row = $allRows[$i];
    $firstCell = trim($row[0] ?? '');
    $thirdCell = trim($row[2] ?? '');
    
    // Unisci tutte le celle per cercare parole chiave in qualsiasi colonna
    $rowText = implode(' ', array_map('trim', $row));
    
    // Debug: mostra riga corrente (prime 5 righe)
    if ($i < 5) {
        echo "   Riga " . ($i + 1) . ": [" . implode("] [", array_slice($row, 0, 5)) . "]\n";
    }
    
    // Rileva intestazione VENDITE: riga con "Data" e "Prodotto"
    if ($firstCell === 'Data' && $thirdCell === 'Prodotto') {
        $modalita = 'vendite';
        echo GREEN . "   ✅ Trovata intestazione VENDITE alla riga " . ($i + 1) . "\n" . RESET;
        continue;
    }
    
    // Rileva "TOTALE VENDITE" in QUALSIASI colonna - passa a modalità attesa spese
    if (stripos($rowText, 'TOTALE VENDITE') !== false) {
        $modalita = 'attesa_spese';
        echo YELLOW . "   📍 Trovato TOTALE VENDITE alla riga " . ($i + 1) . "\n" . RESET;
        continue;
    }
    
    // Rileva riga "SPESE" in qualsiasi colonna (ma NON "TOTALE SPESE")
    if ($modalita === 'attesa_spese' && stripos($rowText, 'SPESE') !== false && stripos($rowText, 'TOTALE') === false) {
        $modalita = 'attesa_header_spese';
        echo YELLOW . "   📍 Trovata riga SPESE alla riga " . ($i + 1) . "\n" . RESET;
        continue;
    }
    
    // Rileva intestazione SPESE: riga con "Data" nella prima colonna (dopo riga SPESE)
    if (($modalita === 'attesa_spese' || $modalita === 'attesa_header_spese') && $firstCell === 'Data') {
        $modalita = 'spese';
        echo GREEN . "   ✅ Trovata intestazione SPESE alla riga " . ($i + 1) . "\n" . RESET;
        continue;
    }
    
    // Rileva "TOTALE SPESE" in qualsiasi colonna - fine parsing spese
    if (stripos($rowText, 'TOTALE SPESE') !== false) {
        $modalita = 'none';
        echo YELLOW . "   📍 Trovato TOTALE SPESE alla riga " . ($i + 1) . "\n" . RESET;
        continue;
    }
    
    // Rileva RIEPILOGO - fine parsing
    if (stripos($rowText, 'RIEPILOGO') !== false) {
        $modalita = 'none';
        continue;
    }
    
    // Salta righe vuote o non valide (deve iniziare con una data)
    if (empty($firstCell) || !preg_match('/\d{1,2}\/\d{1,2}\/\d{4}/', $firstCell)) {
        continue;
    }
    
    // PARSING VENDITE
    if ($modalita === 'vendite') {
        if (count($row) < 5) continue;
        
        $prodotto = trim($row[2]);
        $categoria = trim($row[3]);
        $importo = floatval(str_replace(',', '.', $row[4]));
        
        if (empty($prodotto) || $importo <= 0) continue;
        
        $timestamp = excelDateToMysql($row[0], $row[1]);
        $venditeValide++;
        
        if (count($venditePreview) < 5) {
            $venditePreview[] = [
                'data' => $timestamp,
                'prodotto' => $prodotto,
                'categoria' => $categoria,
                'importo' => $importo
            ];
        }
    }
    
    // PARSING SPESE
    if ($modalita === 'spese') {
        // Struttura SPESE: Data(0), Ora(1), Categoria(2), [vuoto](3), Importo(4)
        // Nel tuo file, l'importo è nella colonna E (indice 4)
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
        
        $timestamp = excelDateToMysql($row[0], $row[1]);
        $speseValide++;
        
        if (count($spesePreview) < 5) {
            $spesePreview[] = [
                'data' => $timestamp,
                'categoria' => $categoria,
                'importo' => $importo
            ];
        }
    }
}

$zip->close();

// MOSTRA RISULTATI
echo BLUE . "\n" . str_repeat("─", 60) . "\n" . RESET;
echo GREEN . "📊 VENDITE TROVATE: $venditeValide\n" . RESET;
echo BLUE . str_repeat("─", 60) . "\n" . RESET;

if (count($venditePreview) > 0) {
    echo YELLOW . "Anteprima prime 5 vendite:\n" . RESET;
    foreach ($venditePreview as $v) {
        echo sprintf("   📅 %s | %-25s | %-15s | €%.2f\n", 
            $v['data'], substr($v['prodotto'], 0, 25), $v['categoria'], $v['importo']);
    }
}

echo BLUE . "\n" . str_repeat("─", 60) . "\n" . RESET;
echo GREEN . "💸 SPESE TROVATE: $speseValide\n" . RESET;
echo BLUE . str_repeat("─", 60) . "\n" . RESET;

if (count($spesePreview) > 0) {
    echo YELLOW . "Anteprima prime 5 spese:\n" . RESET;
    foreach ($spesePreview as $s) {
        echo sprintf("   📅 %s | %-20s | €%.2f\n", 
            $s['data'], $s['categoria'], $s['importo']);
    }
} else {
    echo RED . "⚠️ Nessuna spesa trovata!\n" . RESET;
}

// RIEPILOGO FINALE
echo GREEN . "
╔══════════════════════════════════════════════════════════════╗
║                    RIEPILOGO TEST                             ║
╠══════════════════════════════════════════════════════════════╣
║  Vendite da importare:   " . str_pad($venditeValide, 8) . "                           ║
║  Spese da importare:     " . str_pad($speseValide, 8) . "                           ║
╠══════════════════════════════════════════════════════════════╣" . RESET;

if ($venditeValide > 0 && $speseValide > 0) {
    echo GREEN . "
║  ✅ Il file può essere importato!                             ║" . RESET;
} elseif ($venditeValide > 0) {
    echo YELLOW . "
║  ⚠️ Solo vendite trovate, nessuna spesa                       ║" . RESET;
} else {
    echo RED . "
║  ❌ Problema: verifica la struttura del file                  ║" . RESET;
}

echo GREEN . "
╚══════════════════════════════════════════════════════════════╝
" . RESET;

echo YELLOW . "\nPer procedere con l'importazione reale, esegui:\n" . RESET;
echo "   php import_xlsx.php $filePath\n\n";
