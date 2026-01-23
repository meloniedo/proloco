#!/usr/bin/php
<?php
/**
 * ========================================
 * ESPORTA DATABASE IN XLSX
 * ========================================
 * Uso: php export_xlsx.php [percorso_output]
 * Se non specificato, salva in BACKUP_GIORNALIERI
 */

// Colori per output terminale
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

require_once __DIR__ . '/includes/config.php';

// Percorso output
$outputDir = '/home/pi/proloco/BACKUP_GIORNALIERI';
$timestamp = date('d-m-Y_H-i');
$outputFile = $outputDir . '/storico_' . $timestamp . '.xlsx';

if ($argc > 1) {
    $outputFile = $argv[1];
    $outputDir = dirname($outputFile);
}

// Crea directory se non esiste
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

echo GREEN . "
╔══════════════════════════════════════════════════════════════╗
║         ESPORTA DATABASE IN EXCEL - PROLOCO BAR              ║
╚══════════════════════════════════════════════════════════════╝
" . RESET;

try {
    $pdo = getDB();
    echo GREEN . "✅ Connessione database OK\n" . RESET;
} catch (Exception $e) {
    echo RED . "❌ Errore connessione DB: " . $e->getMessage() . "\n" . RESET;
    exit(1);
}

// Conta record
$venditeCount = $pdo->query("SELECT COUNT(*) FROM vendite")->fetchColumn();
$speseCount = $pdo->query("SELECT COUNT(*) FROM spese")->fetchColumn();

echo YELLOW . "\n📊 Dati da esportare:\n" . RESET;
echo "   Vendite: $venditeCount\n";
echo "   Spese:   $speseCount\n\n";

if ($venditeCount == 0 && $speseCount == 0) {
    echo RED . "⚠️  Il database è vuoto! Niente da esportare.\n" . RESET;
    exit(1);
}

// Recupera dati
$vendite = $pdo->query("
    SELECT 
        DATE_FORMAT(timestamp, '%d/%m/%Y') as Data,
        DATE_FORMAT(timestamp, '%H:%i:%s') as Ora,
        nome_prodotto as Prodotto,
        categoria as Categoria,
        prezzo as Importo
    FROM vendite 
    ORDER BY timestamp ASC
")->fetchAll(PDO::FETCH_ASSOC);

$spese = $pdo->query("
    SELECT 
        DATE_FORMAT(timestamp, '%d/%m/%Y') as Data,
        DATE_FORMAT(timestamp, '%H:%i:%s') as Ora,
        categoria_spesa as Categoria,
        COALESCE(note, '') as Note,
        importo as Importo
    FROM spese 
    ORDER BY timestamp ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Calcola totali
$totaleVendite = $pdo->query("SELECT COALESCE(SUM(prezzo), 0) FROM vendite")->fetchColumn();
$totaleSpese = $pdo->query("SELECT COALESCE(SUM(importo), 0) FROM spese")->fetchColumn();

echo BLUE . "📝 Generazione file Excel...\n" . RESET;

// Crea XLSX manualmente (formato Open XML)
$xlsx = new ZipArchive();
if ($xlsx->open($outputFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo RED . "❌ Impossibile creare il file Excel\n" . RESET;
    exit(1);
}

// [Content_Types].xml
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>';
$xlsx->addFromString('[Content_Types].xml', $contentTypes);

// _rels/.rels
$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
$xlsx->addFromString('_rels/.rels', $rels);

// xl/_rels/workbook.xml.rels
$workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>';
$xlsx->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);

// xl/workbook.xml
$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Storico" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';
$xlsx->addFromString('xl/workbook.xml', $workbook);

// Costruisci shared strings e sheet data
$sharedStrings = [];
$sharedStringsIndex = [];

function getStringIndex($str, &$sharedStrings, &$sharedStringsIndex) {
    $str = htmlspecialchars($str, ENT_XML1, 'UTF-8');
    if (!isset($sharedStringsIndex[$str])) {
        $sharedStringsIndex[$str] = count($sharedStrings);
        $sharedStrings[] = $str;
    }
    return $sharedStringsIndex[$str];
}

function colLetter($col) {
    $letter = '';
    while ($col >= 0) {
        $letter = chr(65 + ($col % 26)) . $letter;
        $col = intval($col / 26) - 1;
    }
    return $letter;
}

// Costruisci righe del foglio
$rows = [];
$rowNum = 1;

// Titolo
$rows[] = '<row r="' . $rowNum . '"><c r="A' . $rowNum . '" t="s"><v>' . getStringIndex('PROLOCO SANTA BIANCA - STORICO', $sharedStrings, $sharedStringsIndex) . '</v></c></row>';
$rowNum++;

// Riga vuota
$rowNum++;

// Header vendite
$headers = ['Data', 'Ora', 'Prodotto', 'Categoria', 'Importo €'];
$row = '<row r="' . $rowNum . '">';
foreach ($headers as $col => $header) {
    $row .= '<c r="' . colLetter($col) . $rowNum . '" t="s"><v>' . getStringIndex($header, $sharedStrings, $sharedStringsIndex) . '</v></c>';
}
$row .= '</row>';
$rows[] = $row;
$rowNum++;

// Dati vendite
foreach ($vendite as $v) {
    $row = '<row r="' . $rowNum . '">';
    $row .= '<c r="A' . $rowNum . '" t="s"><v>' . getStringIndex($v['Data'], $sharedStrings, $sharedStringsIndex) . '</v></c>';
    $row .= '<c r="B' . $rowNum . '" t="s"><v>' . getStringIndex($v['Ora'], $sharedStrings, $sharedStringsIndex) . '</v></c>';
    $row .= '<c r="C' . $rowNum . '" t="s"><v>' . getStringIndex($v['Prodotto'], $sharedStrings, $sharedStringsIndex) . '</v></c>';
    $row .= '<c r="D' . $rowNum . '" t="s"><v>' . getStringIndex($v['Categoria'], $sharedStrings, $sharedStringsIndex) . '</v></c>';
    $row .= '<c r="E' . $rowNum . '"><v>' . $v['Importo'] . '</v></c>';
    $row .= '</row>';
    $rows[] = $row;
    $rowNum++;
}

// Totale vendite
$row = '<row r="' . $rowNum . '">';
$row .= '<c r="D' . $rowNum . '" t="s"><v>' . getStringIndex('TOTALE VENDITE:', $sharedStrings, $sharedStringsIndex) . '</v></c>';
$row .= '<c r="E' . $rowNum . '"><v>' . $totaleVendite . '</v></c>';
$row .= '</row>';
$rows[] = $row;
$rowNum++;

// Riga vuota
$rowNum++;

// Riga SPESE
$rows[] = '<row r="' . $rowNum . '"><c r="A' . $rowNum . '" t="s"><v>' . getStringIndex('SPESE', $sharedStrings, $sharedStringsIndex) . '</v></c></row>';
$rowNum++;

// Header spese
$headersSpese = ['Data', 'Ora', 'Categoria', '', 'Importo €'];
$row = '<row r="' . $rowNum . '">';
foreach ($headersSpese as $col => $header) {
    if ($header) {
        $row .= '<c r="' . colLetter($col) . $rowNum . '" t="s"><v>' . getStringIndex($header, $sharedStrings, $sharedStringsIndex) . '</v></c>';
    }
}
$row .= '</row>';
$rows[] = $row;
$rowNum++;

// Dati spese
foreach ($spese as $s) {
    $row = '<row r="' . $rowNum . '">';
    $row .= '<c r="A' . $rowNum . '" t="s"><v>' . getStringIndex($s['Data'], $sharedStrings, $sharedStringsIndex) . '</v></c>';
    $row .= '<c r="B' . $rowNum . '" t="s"><v>' . getStringIndex($s['Ora'], $sharedStrings, $sharedStringsIndex) . '</v></c>';
    $row .= '<c r="C' . $rowNum . '" t="s"><v>' . getStringIndex($s['Categoria'], $sharedStrings, $sharedStringsIndex) . '</v></c>';
    $row .= '<c r="E' . $rowNum . '"><v>' . $s['Importo'] . '</v></c>';
    $row .= '</row>';
    $rows[] = $row;
    $rowNum++;
}

// Totale spese
$row = '<row r="' . $rowNum . '">';
$row .= '<c r="D' . $rowNum . '" t="s"><v>' . getStringIndex('TOTALE SPESE:', $sharedStrings, $sharedStringsIndex) . '</v></c>';
$row .= '<c r="E' . $rowNum . '"><v>' . $totaleSpese . '</v></c>';
$row .= '</row>';
$rows[] = $row;

// xl/worksheets/sheet1.xml
$sheetData = implode("\n", $rows);
$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
' . $sheetData . '
    </sheetData>
</worksheet>';
$xlsx->addFromString('xl/worksheets/sheet1.xml', $sheet);

// xl/sharedStrings.xml
$ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
foreach ($sharedStrings as $str) {
    $ssXml .= '<si><t>' . $str . '</t></si>';
}
$ssXml .= '</sst>';
$xlsx->addFromString('xl/sharedStrings.xml', $ssXml);

$xlsx->close();

// Verifica file creato
if (file_exists($outputFile)) {
    $size = round(filesize($outputFile) / 1024, 1);
    echo GREEN . "
╔══════════════════════════════════════════════════════════════╗
║                 ✅ ESPORTAZIONE COMPLETATA!                   ║
╚══════════════════════════════════════════════════════════════╝
" . RESET;
    echo "   📁 File: " . BLUE . basename($outputFile) . RESET . "\n";
    echo "   📏 Dimensione: {$size} KB\n";
    echo "   📂 Cartella: $outputDir/\n";
    echo "   📊 Contenuto: $venditeCount vendite + $speseCount spese\n\n";
    echo YELLOW . "   💡 Questo file può essere reimportato con:\n" . RESET;
    echo "      php import_xlsx.php $outputFile\n\n";
} else {
    echo RED . "❌ Errore: file non creato\n" . RESET;
    exit(1);
}
