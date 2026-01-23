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

// ANALISI VENDITE
echo BLUE . "\n" . str_repeat("─", 60) . "\n" . RESET;
echo BLUE . "📊 ANALISI FOGLIO VENDITE (sheet1)\n" . RESET;
echo BLUE . str_repeat("─", 60) . "\n" . RESET;

$venditeRows = readSheet($zip, 1, $sharedStrings);
echo "Righe totali nel foglio: " . count($venditeRows) . "\n";

if (count($venditeRows) > 0) {
    echo YELLOW . "\nIntestazioni (riga 1):\n" . RESET;
    echo "   " . implode(" | ", $venditeRows[0]) . "\n";
}

$venditeValide = 0;
$venditePreview = [];

for ($i = 1; $i < count($venditeRows); $i++) {
    $row = $venditeRows[$i];
    if (count($row) < 5 || empty($row[2])) continue;
    
    $prodotto = trim($row[2]);
    $categoria = trim($row[3]);
    $importo = floatval(str_replace(',', '.', $row[4]));
    
    if (empty($prodotto) || stripos($prodotto, 'TOTALE') !== false) continue;
    if ($importo <= 0) continue;
    
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

echo GREEN . "\nVendite valide trovate: $venditeValide\n" . RESET;

if (count($venditePreview) > 0) {
    echo YELLOW . "\nAnteprima prime 5 vendite:\n" . RESET;
    foreach ($venditePreview as $v) {
        echo sprintf("   📅 %s | %-25s | %-15s | €%.2f\n", 
            $v['data'], $v['prodotto'], $v['categoria'], $v['importo']);
    }
}

// ANALISI SPESE
echo BLUE . "\n" . str_repeat("─", 60) . "\n" . RESET;
echo BLUE . "💸 ANALISI FOGLIO SPESE (sheet2)\n" . RESET;
echo BLUE . str_repeat("─", 60) . "\n" . RESET;

$speseRows = readSheet($zip, 2, $sharedStrings);
echo "Righe totali nel foglio: " . count($speseRows) . "\n";

if (count($speseRows) > 0) {
    echo YELLOW . "\nIntestazioni (riga 1):\n" . RESET;
    echo "   " . implode(" | ", $speseRows[0]) . "\n";
}

$speseValide = 0;
$spesePreview = [];

for ($i = 1; $i < count($speseRows); $i++) {
    $row = $speseRows[$i];
    if (count($row) < 6) continue;
    
    // Colonna C (indice 2) contiene il nome della spesa
    $categoria = trim($row[2]);
    $importo = floatval(str_replace(',', '.', $row[5]));
    
    if (empty($categoria) || stripos($categoria, 'TOTALE') !== false) continue;
    if ($importo <= 0) continue;
    
    $timestamp = excelDateToMysql($row[0], $row[1]);
    $note = trim($row[4] ?? '');
    
    $speseValide++;
    if (count($spesePreview) < 5) {
        $spesePreview[] = [
            'data' => $timestamp,
            'categoria' => $categoria,
            'importo' => $importo,
            'note' => $note
        ];
    }
}

echo GREEN . "\nSpese valide trovate: $speseValide\n" . RESET;

if (count($spesePreview) > 0) {
    echo YELLOW . "\nAnteprima prime 5 spese:\n" . RESET;
    foreach ($spesePreview as $s) {
        echo sprintf("   📅 %s | %-20s | €%.2f\n", 
            $s['data'], $s['categoria'], $s['importo']);
    }
}

$zip->close();

// RIEPILOGO FINALE
echo GREEN . "
╔══════════════════════════════════════════════════════════════╗
║                    RIEPILOGO TEST                             ║
╠══════════════════════════════════════════════════════════════╣
║  Vendite da importare:   " . str_pad($venditeValide, 8) . "                           ║
║  Spese da importare:     " . str_pad($speseValide, 8) . "                           ║
╠══════════════════════════════════════════════════════════════╣
║  ✅ Il file può essere importato!                             ║
╚══════════════════════════════════════════════════════════════╝
" . RESET;

echo YELLOW . "\nPer procedere con l'importazione reale, esegui:\n" . RESET;
echo "   php import_xlsx.php $filePath\n\n";
