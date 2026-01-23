<?php
// ========================================
// DOWNLOAD BACKUP - FILE EXCEL
// Prova XLSX, se fallisce usa XLS (XML)
// ========================================
require_once '../includes/config.php';

try {
    $pdo = getDB();
    
    $vendite = $pdo->query("SELECT * FROM vendite ORDER BY timestamp ASC")->fetchAll(PDO::FETCH_ASSOC);
    $spese = $pdo->query("SELECT * FROM spese ORDER BY timestamp ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Trova date min e max
    $dateMin = $dateMax = null;
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
    
    // Calcola totali
    $totaleVendite = 0;
    $totaleSpese = 0;
    foreach ($vendite as $v) $totaleVendite += floatval($v['prezzo']);
    foreach ($spese as $s) $totaleSpese += floatval($s['importo']);
    
    $meseUpper = strtoupper(date('F Y', $dateMax));
    
    // Prova a creare XLSX con ZipArchive
    $useXlsx = class_exists('ZipArchive');
    
    if ($useXlsx) {
        // ===== FORMATO XLSX =====
        $filename = "StoricoBarProloco-dal-{$dataInizio}-a-{$dataFine}.xlsx";
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        
        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $useXlsx = false;
        } else {
            // Content Types
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

            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>');

            $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>
<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>
<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF8B4513"/></patternFill></fill></fills>
<borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="2" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"/><xf numFmtId="164" fontId="1" fillId="0" borderId="1" applyNumberFormat="1" applyFont="1" applyBorder="1"/></cellXfs>
</styleSheet>');

            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="VENDITE" sheetId="1" r:id="rId1"/><sheet name="SPESE" sheetId="2" r:id="rId2"/><sheet name="RIEPILOGO" sheetId="3" r:id="rId3"/></sheets>
</workbook>');

            // Shared strings
            $strings = [];
            $stringMap = [];
            
            function addStr(&$strings, &$stringMap, $s) {
                $s = (string)$s;
                if (!isset($stringMap[$s])) {
                    $stringMap[$s] = count($strings);
                    $strings[] = htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                }
                return $stringMap[$s];
            }

            // Sheet 1: VENDITE
            $rows1 = [];
            $idx = addStr($strings, $stringMap, "PROLOCO SANTA BIANCA - VENDITE");
            $rows1[] = '<row r="1"><c r="A1" t="s" s="1"><v>'.$idx.'</v></c></row>';
            
            $cols = ['A','B','C','D','E'];
            $hcells = '';
            foreach (['Data','Ora','Prodotto','Categoria','Importo €'] as $i => $h) {
                $idx = addStr($strings, $stringMap, $h);
                $hcells .= '<c r="'.$cols[$i].'2" t="s" s="1"><v>'.$idx.'</v></c>';
            }
            $rows1[] = '<row r="2">'.$hcells.'</row>';
            
            $r = 3;
            foreach ($vendite as $v) {
                $dt = new DateTime($v['timestamp']);
                $rows1[] = '<row r="'.$r.'">'.
                    '<c r="A'.$r.'" t="s" s="3"><v>'.addStr($strings, $stringMap, $dt->format('d/m/Y')).'</v></c>'.
                    '<c r="B'.$r.'" t="s" s="3"><v>'.addStr($strings, $stringMap, $dt->format('H:i:s')).'</v></c>'.
                    '<c r="C'.$r.'" t="s" s="3"><v>'.addStr($strings, $stringMap, $v['nome_prodotto']).'</v></c>'.
                    '<c r="D'.$r.'" t="s" s="3"><v>'.addStr($strings, $stringMap, $v['categoria'] ?? '').'</v></c>'.
                    '<c r="E'.$r.'" s="2"><v>'.$v['prezzo'].'</v></c></row>';
                $r++;
            }
            $r++;
            $rows1[] = '<row r="'.$r.'"><c r="A'.$r.'" t="s" s="1"><v>'.addStr($strings, $stringMap, 'Totale:').'</v></c><c r="E'.$r.'" s="4"><v>'.$totaleVendite.'</v></c></row>';
            
            $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $rows1).'</sheetData></worksheet>');

            // Sheet 2: SPESE
            $rows2 = [];
            $rows2[] = '<row r="1"><c r="A1" t="s" s="1"><v>'.addStr($strings, $stringMap, "SPESE").'</v></c></row>';
            
            $cols2 = ['A','B','C','D','E','F'];
            $hcells2 = '';
            foreach (['Data','Ora','Categoria','Spesa','Note','Importo €'] as $i => $h) {
                $hcells2 .= '<c r="'.$cols2[$i].'2" t="s" s="1"><v>'.addStr($strings, $stringMap, $h).'</v></c>';
            }
            $rows2[] = '<row r="2">'.$hcells2.'</row>';
            
            $r = 3;
            foreach ($spese as $s) {
                $dt = new DateTime($s['timestamp']);
                $rows2[] = '<row r="'.$r.'">'.
                    '<c r="A'.$r.'" t="s" s="3"><v>'.addStr($strings, $stringMap, $dt->format('d/m/Y')).'</v></c>'.
                    '<c r="B'.$r.'" t="s" s="3"><v>'.addStr($strings, $stringMap, $dt->format('H:i:s')).'</v></c>'.
                    '<c r="C'.$r.'" t="s" s="3"><v>'.addStr($strings, $stringMap, $s['categoria_spesa']).'</v></c>'.
                    '<c r="D'.$r.'" t="s" s="3"><v>'.addStr($strings, $stringMap, '').'</v></c>'.
                    '<c r="E'.$r.'" t="s" s="3"><v>'.addStr($strings, $stringMap, $s['note'] ?? '').'</v></c>'.
                    '<c r="F'.$r.'" s="2"><v>'.$s['importo'].'</v></c></row>';
                $r++;
            }
            $r++;
            $rows2[] = '<row r="'.$r.'"><c r="A'.$r.'" t="s" s="1"><v>'.addStr($strings, $stringMap, 'Totale:').'</v></c><c r="F'.$r.'" s="4"><v>'.$totaleSpese.'</v></c></row>';
            
            $zip->addFromString('xl/worksheets/sheet2.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $rows2).'</sheetData></worksheet>');

            // Sheet 3: RIEPILOGO
            $rows3 = [];
            $rows3[] = '<row r="1"><c r="A1" t="s" s="1"><v>'.addStr($strings, $stringMap, "RIEPILOGO MENSILE").'</v></c></row>';
            $rows3[] = '<row r="2"><c r="A2" t="s" s="1"><v>'.addStr($strings, $stringMap, 'Descrizione').'</v></c><c r="B2" t="s" s="1"><v>'.addStr($strings, $stringMap, 'Importo €').'</v></c></row>';
            $rows3[] = '<row r="3"><c r="A3" t="s" s="3"><v>'.addStr($strings, $stringMap, 'Incassi Totali').'</v></c><c r="B3" s="2"><v>'.$totaleVendite.'</v></c></row>';
            $rows3[] = '<row r="4"><c r="A4" t="s" s="3"><v>'.addStr($strings, $stringMap, 'Spese Totali').'</v></c><c r="B4" s="2"><v>'.$totaleSpese.'</v></c></row>';
            $rows3[] = '<row r="5"><c r="A5" t="s" s="1"><v>'.addStr($strings, $stringMap, 'PROFITTO NETTO').'</v></c><c r="B5" s="4"><v>'.($totaleVendite - $totaleSpese).'</v></c></row>';
            
            $zip->addFromString('xl/worksheets/sheet3.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $rows3).'</sheetData></worksheet>');

            // Shared strings XML
            $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">';
            foreach ($strings as $s) $ssXml .= '<si><t>'.$s.'</t></si>';
            $ssXml .= '</sst>';
            $zip->addFromString('xl/sharedStrings.xml', $ssXml);
            
            $zip->close();
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($tmpFile));
            readfile($tmpFile);
            unlink($tmpFile);
            exit;
        }
    }
    
    // ===== FALLBACK: FORMATO XLS (XML) =====
    $filename = "StoricoBarProloco-dal-{$dataInizio}-a-{$dataFine}.xls";
    
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
<Style ss:ID="Header"><Font ss:Bold="1" ss:Size="11"/><Interior ss:Color="#8B4513" ss:Pattern="Solid"/><Font ss:Color="#FFFFFF"/></Style>
<Style ss:ID="Money"><NumberFormat ss:Format="#,##0.00"/></Style>
<Style ss:ID="Bold"><Font ss:Bold="1"/></Style>
</Styles>
<Worksheet ss:Name="VENDITE"><Table>
<Row><Cell ss:StyleID="Header"><Data ss:Type="String">Data</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Ora</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Prodotto</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Importo €</Data></Cell></Row>';
    
    foreach ($vendite as $v) {
        $dt = new DateTime($v['timestamp']);
        $xml .= '<Row><Cell><Data ss:Type="String">'.$dt->format('d/m/Y').'</Data></Cell><Cell><Data ss:Type="String">'.$dt->format('H:i:s').'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($v['nome_prodotto']).'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($v['categoria'] ?? '').'</Data></Cell><Cell ss:StyleID="Money"><Data ss:Type="Number">'.$v['prezzo'].'</Data></Cell></Row>';
    }
    $xml .= '<Row></Row><Row><Cell ss:StyleID="Bold"><Data ss:Type="String">Totale:</Data></Cell><Cell></Cell><Cell></Cell><Cell></Cell><Cell ss:StyleID="Bold"><Data ss:Type="Number">'.$totaleVendite.'</Data></Cell></Row></Table></Worksheet>
<Worksheet ss:Name="SPESE"><Table>
<Row><Cell ss:StyleID="Header"><Data ss:Type="String">Data</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Ora</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Spesa</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Note</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Importo €</Data></Cell></Row>';
    
    foreach ($spese as $s) {
        $dt = new DateTime($s['timestamp']);
        $xml .= '<Row><Cell><Data ss:Type="String">'.$dt->format('d/m/Y').'</Data></Cell><Cell><Data ss:Type="String">'.$dt->format('H:i:s').'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($s['categoria_spesa']).'</Data></Cell><Cell><Data ss:Type="String"></Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($s['note'] ?? '').'</Data></Cell><Cell ss:StyleID="Money"><Data ss:Type="Number">'.$s['importo'].'</Data></Cell></Row>';
    }
    $xml .= '<Row></Row><Row><Cell ss:StyleID="Bold"><Data ss:Type="String">Totale:</Data></Cell><Cell></Cell><Cell></Cell><Cell></Cell><Cell></Cell><Cell ss:StyleID="Bold"><Data ss:Type="Number">'.$totaleSpese.'</Data></Cell></Row></Table></Worksheet>
<Worksheet ss:Name="RIEPILOGO"><Table>
<Row><Cell ss:StyleID="Header"><Data ss:Type="String">Descrizione</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Importo €</Data></Cell></Row>
<Row><Cell><Data ss:Type="String">Incassi Totali</Data></Cell><Cell ss:StyleID="Money"><Data ss:Type="Number">'.$totaleVendite.'</Data></Cell></Row>
<Row><Cell><Data ss:Type="String">Spese Totali</Data></Cell><Cell ss:StyleID="Money"><Data ss:Type="Number">'.$totaleSpese.'</Data></Cell></Row>
<Row><Cell ss:StyleID="Bold"><Data ss:Type="String">PROFITTO NETTO</Data></Cell><Cell ss:StyleID="Bold"><Data ss:Type="Number">'.($totaleVendite - $totaleSpese).'</Data></Cell></Row>
</Table></Worksheet></Workbook>';

    // Salva copia locale in /home/pi/proloco/BACKUP_GIORNALIERI
    $backupLocaleDir = '/home/pi/proloco/BACKUP_GIORNALIERI';
    if (!is_dir($backupLocaleDir)) @mkdir($backupLocaleDir, 0755, true);
    @file_put_contents($backupLocaleDir . '/' . $filename, $xml);
    
    // Aggiorna anche il resoconto totale
    require_once 'cron_resoconto.php';
    aggiornaResocontoTotale();

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $xml;
    exit;
    
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Errore: " . $e->getMessage();
}
