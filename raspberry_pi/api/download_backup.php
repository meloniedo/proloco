<?php
// ========================================
// API DOWNLOAD BACKUP EXCEL (WiFi)
// ========================================
require_once '../includes/config.php';

// Genera file Excel XML compatibile con importazione
function generateExcelXML($vendite, $spese, $prodotti) {
    $totaleVendite = 0;
    $totaleSpese = 0;
    
    foreach ($vendite as $v) $totaleVendite += floatval($v['prezzo']);
    foreach ($spese as $s) $totaleSpese += floatval($s['importo']);
    
    $mese = strtoupper(strftime('%B %Y', strtotime('now')));
    
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 
<Styles>
 <Style ss:ID="Header"><Font ss:Bold="1" ss:Size="11"/><Interior ss:Color="#8B4513" ss:Pattern="Solid"/><Font ss:Color="#FFFFFF"/></Style>
 <Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style>
 <Style ss:ID="Money"><NumberFormat ss:Format="#,##0.00"/></Style>
 <Style ss:ID="Green"><Font ss:Color="#008000" ss:Bold="1"/></Style>
 <Style ss:ID="Red"><Font ss:Color="#FF0000" ss:Bold="1"/></Style>
</Styles>

<Worksheet ss:Name="VENDITE">
 <Table>
  <Row>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Data</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Ora</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Prodotto</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Importo €</Data></Cell>
  </Row>';
  
    foreach ($vendite as $v) {
        $dt = new DateTime($v['timestamp']);
        $xml .= '
  <Row>
   <Cell><Data ss:Type="String">' . $dt->format('d/m/Y') . '</Data></Cell>
   <Cell><Data ss:Type="String">' . $dt->format('H:i:s') . '</Data></Cell>
   <Cell><Data ss:Type="String">' . htmlspecialchars($v['nome_prodotto']) . '</Data></Cell>
   <Cell><Data ss:Type="String">' . htmlspecialchars($v['categoria'] ?? '') . '</Data></Cell>
   <Cell ss:StyleID="Money"><Data ss:Type="Number">' . $v['prezzo'] . '</Data></Cell>
  </Row>';
    }
    
    $xml .= '
  <Row></Row>
  <Row><Cell><Data ss:Type="String">Totale Vendite:</Data></Cell><Cell></Cell><Cell></Cell><Cell></Cell><Cell ss:StyleID="Green"><Data ss:Type="Number">' . $totaleVendite . '</Data></Cell></Row>
 </Table>
</Worksheet>

<Worksheet ss:Name="SPESE">
 <Table>
  <Row>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Data</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Ora</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Spesa</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Note</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Importo €</Data></Cell>
  </Row>';
  
    foreach ($spese as $s) {
        $dt = new DateTime($s['timestamp']);
        $xml .= '
  <Row>
   <Cell><Data ss:Type="String">' . $dt->format('d/m/Y') . '</Data></Cell>
   <Cell><Data ss:Type="String">' . $dt->format('H:i:s') . '</Data></Cell>
   <Cell><Data ss:Type="String">' . htmlspecialchars($s['categoria_spesa']) . '</Data></Cell>
   <Cell><Data ss:Type="String"></Data></Cell>
   <Cell><Data ss:Type="String">' . htmlspecialchars($s['note'] ?? '') . '</Data></Cell>
   <Cell ss:StyleID="Money"><Data ss:Type="Number">' . $s['importo'] . '</Data></Cell>
  </Row>';
    }
    
    $xml .= '
  <Row></Row>
  <Row><Cell><Data ss:Type="String">Totale Spese:</Data></Cell><Cell></Cell><Cell></Cell><Cell></Cell><Cell></Cell><Cell ss:StyleID="Red"><Data ss:Type="Number">' . $totaleSpese . '</Data></Cell></Row>
 </Table>
</Worksheet>

<Worksheet ss:Name="RIEPILOGO MENSILE">
 <Table>
  <Row>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Descrizione</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Importo €</Data></Cell>
  </Row>
  <Row>
   <Cell><Data ss:Type="String">Incassi Totali</Data></Cell>
   <Cell ss:StyleID="Green"><Data ss:Type="Number">' . $totaleVendite . '</Data></Cell>
  </Row>
  <Row>
   <Cell><Data ss:Type="String">Spese Totali</Data></Cell>
   <Cell ss:StyleID="Red"><Data ss:Type="Number">' . $totaleSpese . '</Data></Cell>
  </Row>
  <Row>
   <Cell ss:StyleID="Title"><Data ss:Type="String">PROFITTO NETTO</Data></Cell>
   <Cell ss:StyleID="' . ($totaleVendite - $totaleSpese >= 0 ? 'Green' : 'Red') . '"><Data ss:Type="Number">' . ($totaleVendite - $totaleSpese) . '</Data></Cell>
  </Row>
 </Table>
</Worksheet>

</Workbook>';

    return $xml;
}

try {
    $pdo = getDB();
    
    // Ottieni dati
    $vendite = $pdo->query("SELECT * FROM vendite ORDER BY timestamp DESC")->fetchAll();
    $spese = $pdo->query("SELECT * FROM spese ORDER BY timestamp DESC")->fetchAll();
    $prodotti = $pdo->query("SELECT * FROM prodotti ORDER BY categoria, nome")->fetchAll();
    
    // Genera Excel
    $excelContent = generateExcelXML($vendite, $spese, $prodotti);
    
    // Nome file nel formato storico_DD-MM-YYYY_HH-MM.xls
    $filename = "storico_" . date('d-m-Y_H-i') . ".xls";
    
    // Headers per download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    echo $excelContent;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
