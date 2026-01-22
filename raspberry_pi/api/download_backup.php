<?php
// ========================================
// DOWNLOAD BACKUP - FILE EXCEL XLSX REALE
// ========================================
require_once '../includes/config.php';

// Funzione per creare Excel XLSX reale
function createRealExcel($vendite, $spese) {
    // Trova date min e max
    $dateMin = null;
    $dateMax = null;
    
    foreach ($vendite as $v) {
        $d = strtotime($v['timestamp']);
        if ($dateMin === null || $d < $dateMin) $dateMin = $d;
        if ($dateMax === null || $d > $dateMax) $dateMax = $d;
    }
    foreach ($spese as $s) {
        $d = strtotime($s['timestamp']);
        if ($dateMin === null || $d < $dateMin) $dateMin = $d;
        if ($dateMax === null || $d > $dateMax) $dateMax = $d;
    }
    
    if ($dateMin === null) $dateMin = time();
    if ($dateMax === null) $dateMax = time();
    
    $dataInizio = date('d-m-Y', $dateMin);
    $dataFine = date('d-m-Y', $dateMax);
    $filename = "StoricoBarProloco-dal-{$dataInizio}-a-{$dataFine}.xlsx";
    
    // Calcola totali
    $totaleVendite = 0;
    $totaleSpese = 0;
    foreach ($vendite as $v) $totaleVendite += floatval($v['prezzo']);
    foreach ($spese as $s) $totaleSpese += floatval($s['importo']);
    
    $mese = strftime('%B %Y', $dateMax);
    $meseUpper = strtoupper($mese);
    
    // Crea file XLSX usando ZipArchive (formato Office Open XML)
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
    
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('Impossibile creare file Excel');
    }
    
    // Content Types
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    
    // Relationships
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    $zip->addFromString('_rels/.rels', $rels);
    
    // Workbook relationships
    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
    <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    
    // Styles
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="1">
        <numFmt numFmtId="164" formatCode="#,##0.00\ &quot;€&quot;"/>
    </numFmts>
    <fonts count="4">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
        <font><b/><sz val="14"/><name val="Calibri"/></font>
    </fonts>
    <fills count="4">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF8B4513"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFE8DCC8"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border/>
        <border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border>
    </borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="6">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
        <xf numFmtId="0" fontId="2" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/>
        <xf numFmtId="164" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"/>
        <xf numFmtId="0" fontId="1" fillId="3" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/>
        <xf numFmtId="164" fontId="1" fillId="3" borderId="1" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>
    </cellXfs>
</styleSheet>';
    $zip->addFromString('xl/styles.xml', $styles);
    
    // Collect all strings for sharedStrings
    $strings = [];
    $stringIndex = [];
    
    function getStringIndex(&$strings, &$stringIndex, $str) {
        $str = htmlspecialchars($str, ENT_XML1);
        if (!isset($stringIndex[$str])) {
            $stringIndex[$str] = count($strings);
            $strings[] = $str;
        }
        return $stringIndex[$str];
    }
    
    // Build VENDITE sheet
    $sheet1Rows = [];
    
    // Title row
    $titleIdx = getStringIndex($strings, $stringIndex, "PROLOCO SANTA BIANCA - " . $meseUpper . " VENDITE");
    $sheet1Rows[] = '<row r="1"><c r="A1" t="s" s="1"><v>' . $titleIdx . '</v></c></row>';
    
    // Header row
    $headers = ['Data', 'Ora', 'Prodotto', 'Categoria', 'Importo €'];
    $headerCells = [];
    $cols = ['A', 'B', 'C', 'D', 'E'];
    foreach ($headers as $i => $h) {
        $idx = getStringIndex($strings, $stringIndex, $h);
        $headerCells[] = '<c r="' . $cols[$i] . '2" t="s" s="1"><v>' . $idx . '</v></c>';
    }
    $sheet1Rows[] = '<row r="2">' . implode('', $headerCells) . '</row>';
    
    // Data rows
    $rowNum = 3;
    foreach ($vendite as $v) {
        $dt = new DateTime($v['timestamp']);
        $dataIdx = getStringIndex($strings, $stringIndex, $dt->format('d/m/Y'));
        $oraIdx = getStringIndex($strings, $stringIndex, $dt->format('H:i:s'));
        $prodIdx = getStringIndex($strings, $stringIndex, $v['nome_prodotto']);
        $catIdx = getStringIndex($strings, $stringIndex, $v['categoria'] ?? '');
        
        $sheet1Rows[] = '<row r="' . $rowNum . '">' .
            '<c r="A' . $rowNum . '" t="s" s="3"><v>' . $dataIdx . '</v></c>' .
            '<c r="B' . $rowNum . '" t="s" s="3"><v>' . $oraIdx . '</v></c>' .
            '<c r="C' . $rowNum . '" t="s" s="3"><v>' . $prodIdx . '</v></c>' .
            '<c r="D' . $rowNum . '" t="s" s="3"><v>' . $catIdx . '</v></c>' .
            '<c r="E' . $rowNum . '" s="2"><v>' . $v['prezzo'] . '</v></c>' .
            '</row>';
        $rowNum++;
    }
    
    // Total row
    $totaleIdx = getStringIndex($strings, $stringIndex, 'Totale Vendite:');
    $sheet1Rows[] = '<row r="' . ($rowNum + 1) . '">' .
        '<c r="A' . ($rowNum + 1) . '" t="s" s="4"><v>' . $totaleIdx . '</v></c>' .
        '<c r="E' . ($rowNum + 1) . '" s="5"><v>' . $totaleVendite . '</v></c>' .
        '</row>';
    
    $sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>' . implode("\n", $sheet1Rows) . '</sheetData>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
    
    // Build SPESE sheet
    $sheet2Rows = [];
    
    $titleIdx2 = getStringIndex($strings, $stringIndex, "SPESE");
    $sheet2Rows[] = '<row r="1"><c r="A1" t="s" s="1"><v>' . $titleIdx2 . '</v></c></row>';
    
    $headers2 = ['Data', 'Ora', 'Categoria', 'Spesa', 'Note', 'Importo €'];
    $cols2 = ['A', 'B', 'C', 'D', 'E', 'F'];
    $headerCells2 = [];
    foreach ($headers2 as $i => $h) {
        $idx = getStringIndex($strings, $stringIndex, $h);
        $headerCells2[] = '<c r="' . $cols2[$i] . '2" t="s" s="1"><v>' . $idx . '</v></c>';
    }
    $sheet2Rows[] = '<row r="2">' . implode('', $headerCells2) . '</row>';
    
    $rowNum2 = 3;
    foreach ($spese as $s) {
        $dt = new DateTime($s['timestamp']);
        $dataIdx = getStringIndex($strings, $stringIndex, $dt->format('d/m/Y'));
        $oraIdx = getStringIndex($strings, $stringIndex, $dt->format('H:i:s'));
        $catIdx = getStringIndex($strings, $stringIndex, $s['categoria_spesa']);
        $spesaIdx = getStringIndex($strings, $stringIndex, '');
        $noteIdx = getStringIndex($strings, $stringIndex, $s['note'] ?? '');
        
        $sheet2Rows[] = '<row r="' . $rowNum2 . '">' .
            '<c r="A' . $rowNum2 . '" t="s" s="3"><v>' . $dataIdx . '</v></c>' .
            '<c r="B' . $rowNum2 . '" t="s" s="3"><v>' . $oraIdx . '</v></c>' .
            '<c r="C' . $rowNum2 . '" t="s" s="3"><v>' . $catIdx . '</v></c>' .
            '<c r="D' . $rowNum2 . '" t="s" s="3"><v>' . $spesaIdx . '</v></c>' .
            '<c r="E' . $rowNum2 . '" t="s" s="3"><v>' . $noteIdx . '</v></c>' .
            '<c r="F' . $rowNum2 . '" s="2"><v>' . $s['importo'] . '</v></c>' .
            '</row>';
        $rowNum2++;
    }
    
    $totaleIdx2 = getStringIndex($strings, $stringIndex, 'Totale Spese:');
    $sheet2Rows[] = '<row r="' . ($rowNum2 + 1) . '">' .
        '<c r="A' . ($rowNum2 + 1) . '" t="s" s="4"><v>' . $totaleIdx2 . '</v></c>' .
        '<c r="F' . ($rowNum2 + 1) . '" s="5"><v>' . $totaleSpese . '</v></c>' .
        '</row>';
    
    $sheet2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>' . implode("\n", $sheet2Rows) . '</sheetData>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet2.xml', $sheet2);
    
    // Build RIEPILOGO sheet
    $sheet3Rows = [];
    
    $titleIdx3 = getStringIndex($strings, $stringIndex, "RIEPILOGO MENSILE");
    $sheet3Rows[] = '<row r="1"><c r="A1" t="s" s="1"><v>' . $titleIdx3 . '</v></c></row>';
    
    $descIdx = getStringIndex($strings, $stringIndex, 'Descrizione');
    $impIdx = getStringIndex($strings, $stringIndex, 'Importo €');
    $sheet3Rows[] = '<row r="2"><c r="A2" t="s" s="1"><v>' . $descIdx . '</v></c><c r="B2" t="s" s="1"><v>' . $impIdx . '</v></c></row>';
    
    $incassiIdx = getStringIndex($strings, $stringIndex, 'Incassi Totali');
    $speseIdx = getStringIndex($strings, $stringIndex, 'Spese Totali');
    $profittoIdx = getStringIndex($strings, $stringIndex, 'PROFITTO NETTO');
    
    $sheet3Rows[] = '<row r="3"><c r="A3" t="s" s="3"><v>' . $incassiIdx . '</v></c><c r="B3" s="2"><v>' . $totaleVendite . '</v></c></row>';
    $sheet3Rows[] = '<row r="4"><c r="A4" t="s" s="3"><v>' . $speseIdx . '</v></c><c r="B4" s="2"><v>' . $totaleSpese . '</v></c></row>';
    $sheet3Rows[] = '<row r="5"><c r="A5" t="s" s="4"><v>' . $profittoIdx . '</v></c><c r="B5" s="5"><v>' . ($totaleVendite - $totaleSpese) . '</v></c></row>';
    
    $sheet3 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>' . implode("\n", $sheet3Rows) . '</sheetData>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet3.xml', $sheet3);
    
    // SharedStrings
    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
    foreach ($strings as $s) {
        $ssXml .= '<si><t>' . $s . '</t></si>';
    }
    $ssXml .= '</sst>';
    $zip->addFromString('xl/sharedStrings.xml', $ssXml);
    
    // Workbook
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="VENDITE" sheetId="1" r:id="rId1"/>
        <sheet name="SPESE" sheetId="2" r:id="rId2"/>
        <sheet name="RIEPILOGO MENSILE" sheetId="3" r:id="rId3"/>
    </sheets>
</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbook);
    
    $zip->close();
    
    return ['file' => $tmpFile, 'filename' => $filename];
}

try {
    $pdo = getDB();
    
    $vendite = $pdo->query("SELECT * FROM vendite ORDER BY timestamp ASC")->fetchAll();
    $spese = $pdo->query("SELECT * FROM spese ORDER BY timestamp ASC")->fetchAll();
    
    $result = createRealExcel($vendite, $spese);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
    header('Content-Length: ' . filesize($result['file']));
    header('Cache-Control: max-age=0');
    
    readfile($result['file']);
    unlink($result['file']);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
