<?php
// ========================================
// DOWNLOAD BACKUP - FILE EXCEL XLSX VERO
// ========================================
require_once '../includes/config.php';

try {
    $pdo = getDB();
    
    // Ottieni dati ordinati per data crescente
    $vendite = $pdo->query("SELECT * FROM vendite ORDER BY timestamp ASC")->fetchAll(PDO::FETCH_ASSOC);
    $spese = $pdo->query("SELECT * FROM spese ORDER BY timestamp ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Trova date min e max per il nome file
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
    
    // Mese per titolo
    $meseUpper = strtoupper(date('F Y', $dateMax));
    
    // ===== CREA FILE XLSX =====
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    
    $zip = new ZipArchive();
    $result = $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($result !== true) {
        throw new Exception('Errore creazione ZIP: ' . $result);
    }
    
    // [Content_Types].xml
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>');

    // _rels/.rels
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

    // xl/_rels/workbook.xml.rels
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>');

    // xl/styles.xml
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>
<fonts count="3">
<font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
</fonts>
<fills count="3">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FF8B4513"/></patternFill></fill>
</fills>
<borders count="2">
<border/>
<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="5">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
<xf numFmtId="0" fontId="2" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/>
<xf numFmtId="164" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>
<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"/>
<xf numFmtId="164" fontId="1" fillId="0" borderId="1" applyNumberFormat="1" applyFont="1" applyBorder="1"/>
</cellXfs>
</styleSheet>');

    // xl/workbook.xml
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
<sheet name="VENDITE" sheetId="1" r:id="rId1"/>
<sheet name="SPESE" sheetId="2" r:id="rId2"/>
<sheet name="RIEPILOGO" sheetId="3" r:id="rId3"/>
</sheets>
</workbook>');

    // Raccogli tutte le stringhe
    $strings = [];
    $stringMap = [];
    
    function addString(&$strings, &$stringMap, $s) {
        $s = (string)$s;
        if (!isset($stringMap[$s])) {
            $stringMap[$s] = count($strings);
            $strings[] = htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }
        return $stringMap[$s];
    }
    
    // ===== FOGLIO 1: VENDITE =====
    $rows1 = [];
    
    // Titolo
    $idx = addString($strings, $stringMap, "PROLOCO SANTA BIANCA - {$meseUpper} VENDITE");
    $rows1[] = '<row r="1"><c r="A1" t="s" s="1"><v>'.$idx.'</v></c></row>';
    
    // Header
    $headers = ['Data', 'Ora', 'Prodotto', 'Categoria', 'Importo €'];
    $cols = ['A','B','C','D','E'];
    $hcells = '';
    foreach ($headers as $i => $h) {
        $idx = addString($strings, $stringMap, $h);
        $hcells .= '<c r="'.$cols[$i].'2" t="s" s="1"><v>'.$idx.'</v></c>';
    }
    $rows1[] = '<row r="2">'.$hcells.'</row>';
    
    // Dati vendite
    $r = 3;
    foreach ($vendite as $v) {
        $dt = new DateTime($v['timestamp']);
        $idxData = addString($strings, $stringMap, $dt->format('d/m/Y'));
        $idxOra = addString($strings, $stringMap, $dt->format('H:i:s'));
        $idxProd = addString($strings, $stringMap, $v['nome_prodotto']);
        $idxCat = addString($strings, $stringMap, $v['categoria'] ?? '');
        
        $rows1[] = '<row r="'.$r.'">'.
            '<c r="A'.$r.'" t="s" s="3"><v>'.$idxData.'</v></c>'.
            '<c r="B'.$r.'" t="s" s="3"><v>'.$idxOra.'</v></c>'.
            '<c r="C'.$r.'" t="s" s="3"><v>'.$idxProd.'</v></c>'.
            '<c r="D'.$r.'" t="s" s="3"><v>'.$idxCat.'</v></c>'.
            '<c r="E'.$r.'" s="2"><v>'.$v['prezzo'].'</v></c>'.
            '</row>';
        $r++;
    }
    
    // Riga totale
    $r++;
    $idxTot = addString($strings, $stringMap, 'Totale Vendite:');
    $rows1[] = '<row r="'.$r.'"><c r="A'.$r.'" t="s" s="1"><v>'.$idxTot.'</v></c><c r="E'.$r.'" s="4"><v>'.$totaleVendite.'</v></c></row>';
    
    $sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>'.implode("\n", $rows1).'</sheetData>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
    
    // ===== FOGLIO 2: SPESE =====
    $rows2 = [];
    
    $idx = addString($strings, $stringMap, "SPESE");
    $rows2[] = '<row r="1"><c r="A1" t="s" s="1"><v>'.$idx.'</v></c></row>';
    
    $headers2 = ['Data', 'Ora', 'Categoria', 'Spesa', 'Note', 'Importo €'];
    $cols2 = ['A','B','C','D','E','F'];
    $hcells2 = '';
    foreach ($headers2 as $i => $h) {
        $idx = addString($strings, $stringMap, $h);
        $hcells2 .= '<c r="'.$cols2[$i].'2" t="s" s="1"><v>'.$idx.'</v></c>';
    }
    $rows2[] = '<row r="2">'.$hcells2.'</row>';
    
    $r = 3;
    foreach ($spese as $s) {
        $dt = new DateTime($s['timestamp']);
        $idxData = addString($strings, $stringMap, $dt->format('d/m/Y'));
        $idxOra = addString($strings, $stringMap, $dt->format('H:i:s'));
        $idxCat = addString($strings, $stringMap, $s['categoria_spesa']);
        $idxSpesa = addString($strings, $stringMap, '');
        $idxNote = addString($strings, $stringMap, $s['note'] ?? '');
        
        $rows2[] = '<row r="'.$r.'">'.
            '<c r="A'.$r.'" t="s" s="3"><v>'.$idxData.'</v></c>'.
            '<c r="B'.$r.'" t="s" s="3"><v>'.$idxOra.'</v></c>'.
            '<c r="C'.$r.'" t="s" s="3"><v>'.$idxCat.'</v></c>'.
            '<c r="D'.$r.'" t="s" s="3"><v>'.$idxSpesa.'</v></c>'.
            '<c r="E'.$r.'" t="s" s="3"><v>'.$idxNote.'</v></c>'.
            '<c r="F'.$r.'" s="2"><v>'.$s['importo'].'</v></c>'.
            '</row>';
        $r++;
    }
    
    $r++;
    $idxTot2 = addString($strings, $stringMap, 'Totale Spese:');
    $rows2[] = '<row r="'.$r.'"><c r="A'.$r.'" t="s" s="1"><v>'.$idxTot2.'</v></c><c r="F'.$r.'" s="4"><v>'.$totaleSpese.'</v></c></row>';
    
    $sheet2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>'.implode("\n", $rows2).'</sheetData>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet2.xml', $sheet2);
    
    // ===== FOGLIO 3: RIEPILOGO =====
    $rows3 = [];
    
    $idx = addString($strings, $stringMap, "RIEPILOGO MENSILE");
    $rows3[] = '<row r="1"><c r="A1" t="s" s="1"><v>'.$idx.'</v></c></row>';
    
    $idxDesc = addString($strings, $stringMap, 'Descrizione');
    $idxImp = addString($strings, $stringMap, 'Importo €');
    $rows3[] = '<row r="2"><c r="A2" t="s" s="1"><v>'.$idxDesc.'</v></c><c r="B2" t="s" s="1"><v>'.$idxImp.'</v></c></row>';
    
    $idxInc = addString($strings, $stringMap, 'Incassi Totali');
    $idxSpe = addString($strings, $stringMap, 'Spese Totali');
    $idxProf = addString($strings, $stringMap, 'PROFITTO NETTO');
    
    $rows3[] = '<row r="3"><c r="A3" t="s" s="3"><v>'.$idxInc.'</v></c><c r="B3" s="2"><v>'.$totaleVendite.'</v></c></row>';
    $rows3[] = '<row r="4"><c r="A4" t="s" s="3"><v>'.$idxSpe.'</v></c><c r="B4" s="2"><v>'.$totaleSpese.'</v></c></row>';
    $rows3[] = '<row r="5"><c r="A5" t="s" s="1"><v>'.$idxProf.'</v></c><c r="B5" s="4"><v>'.($totaleVendite - $totaleSpese).'</v></c></row>';
    
    $sheet3 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>'.implode("\n", $rows3).'</sheetData>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet3.xml', $sheet3);
    
    // xl/sharedStrings.xml
    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">';
    foreach ($strings as $s) {
        $ssXml .= '<si><t>'.$s.'</t></si>';
    }
    $ssXml .= '</sst>';
    $zip->addFromString('xl/sharedStrings.xml', $ssXml);
    
    // Chiudi ZIP
    $zip->close();
    
    // Invia il file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
    
} catch (Exception $e) {
    header('Content-Type: text/plain');
    echo "Errore: " . $e->getMessage();
    exit;
}
