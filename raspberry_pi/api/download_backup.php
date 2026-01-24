<?php
// ========================================
// DOWNLOAD BACKUP - FILE EXCEL
// Formato: UNICO FOGLIO (compatibile con import)
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
    
    $filename = "StoricoBarProloco-dal-{$dataInizio}-a-{$dataFine}.xlsx";
    
    // Prova a creare XLSX con ZipArchive
    if (class_exists('ZipArchive')) {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        
        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            
            // Content Types - SOLO 1 FOGLIO
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>');

            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>');

            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Storico" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

            // Shared strings e sheet data
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
            
            function colLetter($col) {
                $letter = '';
                while ($col >= 0) {
                    $letter = chr(65 + ($col % 26)) . $letter;
                    $col = intval($col / 26) - 1;
                }
                return $letter;
            }
            
            // Costruisci righe del foglio UNICO
            $rows = [];
            $rowNum = 1;
            
            // Titolo
            $rows[] = '<row r="' . $rowNum . '"><c r="A' . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, 'PROLOCO SANTA BIANCA - STORICO') . '</v></c></row>';
            $rowNum++;
            $rowNum++; // Riga vuota
            
            // Header vendite
            $headers = ['Data', 'Ora', 'Prodotto', 'Categoria', 'Importo €'];
            $row = '<row r="' . $rowNum . '">';
            foreach ($headers as $col => $header) {
                $row .= '<c r="' . colLetter($col) . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, $header) . '</v></c>';
            }
            $row .= '</row>';
            $rows[] = $row;
            $rowNum++;
            
            // Dati vendite
            foreach ($vendite as $v) {
                $dt = new DateTime($v['timestamp']);
                $row = '<row r="' . $rowNum . '">';
                $row .= '<c r="A' . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, $dt->format('d/m/Y')) . '</v></c>';
                $row .= '<c r="B' . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, $dt->format('H:i:s')) . '</v></c>';
                $row .= '<c r="C' . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, $v['nome_prodotto']) . '</v></c>';
                $row .= '<c r="D' . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, $v['categoria'] ?? '') . '</v></c>';
                $row .= '<c r="E' . $rowNum . '"><v>' . $v['prezzo'] . '</v></c>';
                $row .= '</row>';
                $rows[] = $row;
                $rowNum++;
            }
            
            // Totale vendite
            $row = '<row r="' . $rowNum . '">';
            $row .= '<c r="D' . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, 'TOTALE VENDITE:') . '</v></c>';
            $row .= '<c r="E' . $rowNum . '"><v>' . $totaleVendite . '</v></c>';
            $row .= '</row>';
            $rows[] = $row;
            $rowNum++;
            $rowNum++; // Riga vuota
            
            // Sezione SPESE
            $rows[] = '<row r="' . $rowNum . '"><c r="A' . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, 'SPESE') . '</v></c></row>';
            $rowNum++;
            
            // Header spese
            $headersSpese = ['Data', 'Ora', 'Categoria', '', 'Importo €'];
            $row = '<row r="' . $rowNum . '">';
            foreach ($headersSpese as $col => $header) {
                if ($header) {
                    $row .= '<c r="' . colLetter($col) . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, $header) . '</v></c>';
                }
            }
            $row .= '</row>';
            $rows[] = $row;
            $rowNum++;
            
            // Dati spese
            foreach ($spese as $s) {
                $dt = new DateTime($s['timestamp']);
                $row = '<row r="' . $rowNum . '">';
                $row .= '<c r="A' . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, $dt->format('d/m/Y')) . '</v></c>';
                $row .= '<c r="B' . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, $dt->format('H:i:s')) . '</v></c>';
                $row .= '<c r="C' . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, $s['categoria_spesa']) . '</v></c>';
                $row .= '<c r="E' . $rowNum . '"><v>' . $s['importo'] . '</v></c>';
                $row .= '</row>';
                $rows[] = $row;
                $rowNum++;
            }
            
            // Totale spese
            $row = '<row r="' . $rowNum . '">';
            $row .= '<c r="D' . $rowNum . '" t="s"><v>' . addStr($strings, $stringMap, 'TOTALE SPESE:') . '</v></c>';
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
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
            
            // xl/sharedStrings.xml
            $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
            foreach ($strings as $str) {
                $ssXml .= '<si><t>' . $str . '</t></si>';
            }
            $ssXml .= '</sst>';
            $zip->addFromString('xl/sharedStrings.xml', $ssXml);
            
            $zip->close();
            
            // Salva copia locale
            $backupLocaleDir = '/home/pi/proloco/BACKUP_GIORNALIERI';
            if (!is_dir($backupLocaleDir)) @mkdir($backupLocaleDir, 0755, true);
            @copy($tmpFile, $backupLocaleDir . '/' . $filename);
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($tmpFile));
            readfile($tmpFile);
            unlink($tmpFile);
            exit;
        }
    }
    
    // ===== FALLBACK: FORMATO XLS (XML) - UNICO FOGLIO =====
    $filename = "StoricoBarProloco-dal-{$dataInizio}-a-{$dataFine}.xlsx";
    
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
<Style ss:ID="Header"><Font ss:Bold="1" ss:Size="11"/><Interior ss:Color="#8B4513" ss:Pattern="Solid"/><Font ss:Color="#FFFFFF"/></Style>
<Style ss:ID="Money"><NumberFormat ss:Format="#,##0.00"/></Style>
<Style ss:ID="Bold"><Font ss:Bold="1"/></Style>
<Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style>
</Styles>
<Worksheet ss:Name="Storico"><Table>
<Row><Cell ss:StyleID="Title"><Data ss:Type="String">PROLOCO SANTA BIANCA - STORICO</Data></Cell></Row>
<Row></Row>
<Row><Cell ss:StyleID="Header"><Data ss:Type="String">Data</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Ora</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Prodotto</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Importo €</Data></Cell></Row>';
    
    foreach ($vendite as $v) {
        $dt = new DateTime($v['timestamp']);
        $xml .= '<Row><Cell><Data ss:Type="String">'.$dt->format('d/m/Y').'</Data></Cell><Cell><Data ss:Type="String">'.$dt->format('H:i:s').'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($v['nome_prodotto']).'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($v['categoria'] ?? '').'</Data></Cell><Cell ss:StyleID="Money"><Data ss:Type="Number">'.$v['prezzo'].'</Data></Cell></Row>';
    }
    
    $xml .= '<Row><Cell></Cell><Cell></Cell><Cell></Cell><Cell ss:StyleID="Bold"><Data ss:Type="String">TOTALE VENDITE:</Data></Cell><Cell ss:StyleID="Bold"><Data ss:Type="Number">'.$totaleVendite.'</Data></Cell></Row>';
    $xml .= '<Row></Row>';
    $xml .= '<Row><Cell ss:StyleID="Title"><Data ss:Type="String">SPESE</Data></Cell></Row>';
    $xml .= '<Row><Cell ss:StyleID="Header"><Data ss:Type="String">Data</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Ora</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell><Cell></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Importo €</Data></Cell></Row>';
    
    foreach ($spese as $s) {
        $dt = new DateTime($s['timestamp']);
        $xml .= '<Row><Cell><Data ss:Type="String">'.$dt->format('d/m/Y').'</Data></Cell><Cell><Data ss:Type="String">'.$dt->format('H:i:s').'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($s['categoria_spesa']).'</Data></Cell><Cell></Cell><Cell ss:StyleID="Money"><Data ss:Type="Number">'.$s['importo'].'</Data></Cell></Row>';
    }
    
    $xml .= '<Row><Cell></Cell><Cell></Cell><Cell></Cell><Cell ss:StyleID="Bold"><Data ss:Type="String">TOTALE SPESE:</Data></Cell><Cell ss:StyleID="Bold"><Data ss:Type="Number">'.$totaleSpese.'</Data></Cell></Row>';
    $xml .= '</Table></Worksheet></Workbook>';

    // Salva copia locale
    $backupLocaleDir = '/home/pi/proloco/BACKUP_GIORNALIERI';
    if (!is_dir($backupLocaleDir)) @mkdir($backupLocaleDir, 0755, true);
    @file_put_contents($backupLocaleDir . '/' . $filename, $xml);

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $xml;
    exit;
    
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Errore: " . $e->getMessage();
}
