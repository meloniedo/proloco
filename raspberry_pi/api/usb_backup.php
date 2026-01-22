<?php
// ========================================
// API BACKUP SU USB - CON EXCEL REALE
// ========================================
require_once '../includes/config.php';

// Possibili mount point per USB
$USB_PATHS = [
    '/media/usb_backup',
    '/media/usb0',
    '/media/usb1',
    '/media/usb',
    '/mnt/usb',
    '/media/pi'
];

// Trova chiavetta USB montata
function findUSB() {
    global $USB_PATHS;
    
    // Prima controlla i mount point predefiniti
    foreach ($USB_PATHS as $path) {
        if (is_dir($path) && is_writable($path)) {
            // Verifica che non sia vuoto (indicherebbe mount attivo)
            $files = @scandir($path);
            if ($files && count($files) > 2) { // più di . e ..
                return $path;
            }
            // Verifica spazio disponibile
            $freeSpace = @disk_free_space($path);
            if ($freeSpace !== false && $freeSpace > 1048576) { // > 1MB
                return $path;
            }
        }
    }
    
    // Cerca mount point dinamici in /media
    $mediaDir = '/media';
    if (is_dir($mediaDir)) {
        $dirs = @scandir($mediaDir);
        if ($dirs) {
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') continue;
                $fullPath = $mediaDir . '/' . $dir;
                if (is_dir($fullPath) && is_writable($fullPath)) {
                    $freeSpace = @disk_free_space($fullPath);
                    if ($freeSpace !== false && $freeSpace > 1048576) {
                        return $fullPath;
                    }
                }
            }
        }
    }
    
    return null;
}

// Verifica stato USB
function checkUSBStatus() {
    $usbPath = findUSB();
    
    if ($usbPath) {
        $freeSpace = @disk_free_space($usbPath);
        $totalSpace = @disk_total_space($usbPath);
        
        return [
            'connected' => true,
            'path' => $usbPath,
            'free_space' => $freeSpace ? round($freeSpace / 1024 / 1024, 2) : 0,
            'total_space' => $totalSpace ? round($totalSpace / 1024 / 1024, 2) : 0,
            'free_space_formatted' => formatBytes($freeSpace ?: 0),
            'total_space_formatted' => formatBytes($totalSpace ?: 0)
        ];
    }
    
    return [
        'connected' => false,
        'error' => 'Nessuna chiavetta USB rilevata. Inserisci una chiavetta USB e riprova.'
    ];
}

function formatBytes($bytes) {
    if ($bytes <= 0) return '0 B';
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1024, 2) . ' KB';
}

// Genera file Excel semplice (formato XML che Excel apre)
function generateExcelXML($vendite, $spese, $prodotti) {
    $totaleVendite = 0;
    $totaleSpese = 0;
    
    foreach ($vendite as $v) $totaleVendite += floatval($v['prezzo']);
    foreach ($spese as $s) $totaleSpese += floatval($s['importo']);
    
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 
<Styles>
 <Style ss:ID="Header"><Font ss:Bold="1" ss:Size="12"/><Interior ss:Color="#8B4513" ss:Pattern="Solid"/><Font ss:Color="#FFFFFF"/></Style>
 <Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style>
 <Style ss:ID="Money"><NumberFormat ss:Format="€#,##0.00"/></Style>
 <Style ss:ID="Date"><NumberFormat ss:Format="DD/MM/YYYY HH:MM"/></Style>
 <Style ss:ID="Green"><Font ss:Color="#008000" ss:Bold="1"/></Style>
 <Style ss:ID="Red"><Font ss:Color="#FF0000" ss:Bold="1"/></Style>
</Styles>

<Worksheet ss:Name="Riepilogo">
 <Table>
  <Row><Cell ss:StyleID="Title"><Data ss:Type="String">PROLOCO SANTA BIANCA - RIEPILOGO</Data></Cell></Row>
  <Row><Cell><Data ss:Type="String">Data backup: ' . date('d/m/Y H:i:s') . '</Data></Cell></Row>
  <Row></Row>
  <Row><Cell ss:StyleID="Header"><Data ss:Type="String">Voce</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Valore</Data></Cell></Row>
  <Row><Cell><Data ss:Type="String">Totale Vendite</Data></Cell><Cell><Data ss:Type="Number">' . count($vendite) . '</Data></Cell></Row>
  <Row><Cell><Data ss:Type="String">Totale Spese</Data></Cell><Cell><Data ss:Type="Number">' . count($spese) . '</Data></Cell></Row>
  <Row><Cell><Data ss:Type="String">Incasso Totale</Data></Cell><Cell ss:StyleID="Green"><Data ss:Type="Number">' . $totaleVendite . '</Data></Cell></Row>
  <Row><Cell><Data ss:Type="String">Spese Totali</Data></Cell><Cell ss:StyleID="Red"><Data ss:Type="Number">' . $totaleSpese . '</Data></Cell></Row>
  <Row><Cell ss:StyleID="Title"><Data ss:Type="String">PROFITTO NETTO</Data></Cell><Cell ss:StyleID="' . ($totaleVendite - $totaleSpese >= 0 ? 'Green' : 'Red') . '"><Data ss:Type="Number">' . ($totaleVendite - $totaleSpese) . '</Data></Cell></Row>
 </Table>
</Worksheet>

<Worksheet ss:Name="Vendite">
 <Table>
  <Row>
   <Cell ss:StyleID="Header"><Data ss:Type="String">ID</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Data</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Prodotto</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Importo €</Data></Cell>
  </Row>';
  
    foreach ($vendite as $v) {
        $dt = new DateTime($v['timestamp']);
        $xml .= '
  <Row>
   <Cell><Data ss:Type="Number">' . $v['id'] . '</Data></Cell>
   <Cell><Data ss:Type="String">' . $dt->format('d/m/Y H:i') . '</Data></Cell>
   <Cell><Data ss:Type="String">' . htmlspecialchars($v['nome_prodotto']) . '</Data></Cell>
   <Cell><Data ss:Type="String">' . htmlspecialchars($v['categoria'] ?? '') . '</Data></Cell>
   <Cell ss:StyleID="Money"><Data ss:Type="Number">' . $v['prezzo'] . '</Data></Cell>
  </Row>';
    }
    
    $xml .= '
  <Row></Row>
  <Row><Cell></Cell><Cell></Cell><Cell></Cell><Cell ss:StyleID="Title"><Data ss:Type="String">TOTALE:</Data></Cell><Cell ss:StyleID="Green"><Data ss:Type="Number">' . $totaleVendite . '</Data></Cell></Row>
 </Table>
</Worksheet>

<Worksheet ss:Name="Spese">
 <Table>
  <Row>
   <Cell ss:StyleID="Header"><Data ss:Type="String">ID</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Data</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Note</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Importo €</Data></Cell>
  </Row>';
  
    foreach ($spese as $s) {
        $dt = new DateTime($s['timestamp']);
        $xml .= '
  <Row>
   <Cell><Data ss:Type="Number">' . $s['id'] . '</Data></Cell>
   <Cell><Data ss:Type="String">' . $dt->format('d/m/Y H:i') . '</Data></Cell>
   <Cell><Data ss:Type="String">' . htmlspecialchars($s['categoria_spesa']) . '</Data></Cell>
   <Cell><Data ss:Type="String">' . htmlspecialchars($s['note'] ?? '') . '</Data></Cell>
   <Cell ss:StyleID="Money"><Data ss:Type="Number">' . $s['importo'] . '</Data></Cell>
  </Row>';
    }
    
    $xml .= '
  <Row></Row>
  <Row><Cell></Cell><Cell></Cell><Cell></Cell><Cell ss:StyleID="Title"><Data ss:Type="String">TOTALE:</Data></Cell><Cell ss:StyleID="Red"><Data ss:Type="Number">' . $totaleSpese . '</Data></Cell></Row>
 </Table>
</Worksheet>

<Worksheet ss:Name="Listino">
 <Table>
  <Row>
   <Cell ss:StyleID="Header"><Data ss:Type="String">ID</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Nome</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Prezzo €</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Icona</Data></Cell>
  </Row>';
  
    foreach ($prodotti as $p) {
        $xml .= '
  <Row>
   <Cell><Data ss:Type="Number">' . $p['id'] . '</Data></Cell>
   <Cell><Data ss:Type="String">' . htmlspecialchars($p['nome']) . '</Data></Cell>
   <Cell ss:StyleID="Money"><Data ss:Type="Number">' . $p['prezzo'] . '</Data></Cell>
   <Cell><Data ss:Type="String">' . htmlspecialchars($p['categoria']) . '</Data></Cell>
   <Cell><Data ss:Type="String">' . $p['icona'] . '</Data></Cell>
  </Row>';
    }
    
    $xml .= '
 </Table>
</Worksheet>

</Workbook>';

    return $xml;
}

// Esegui backup
function doBackup() {
    $usbPath = findUSB();
    
    if (!$usbPath) {
        return ['success' => false, 'error' => 'Chiavetta USB non trovata. Assicurati che sia inserita correttamente.'];
    }
    
    // Verifica scrittura
    if (!is_writable($usbPath)) {
        return ['success' => false, 'error' => 'Impossibile scrivere sulla chiavetta USB. Potrebbe essere protetta da scrittura.'];
    }
    
    // Crea directory backup
    $backupDir = $usbPath . '/ProlocoBackup';
    if (!is_dir($backupDir)) {
        if (!@mkdir($backupDir, 0777, true)) {
            return ['success' => false, 'error' => 'Impossibile creare la cartella di backup sulla chiavetta USB.'];
        }
    }
    
    // Nome file con data
    $date = date('Y-m-d');
    $time = date('H-i-s');
    
    // Controlla backup esistenti oggi
    $existingBackups = glob($backupDir . "/backup_{$date}*.xls");
    $backupNum = count($existingBackups) + 1;
    
    $filename = "backup_{$date}";
    if ($backupNum > 1) {
        $filename .= "_n{$backupNum}";
    }
    $filename .= ".xls";
    
    $filepath = $backupDir . '/' . $filename;
    
    try {
        $pdo = getDB();
        
        // Ottieni dati
        $vendite = $pdo->query("SELECT * FROM vendite ORDER BY timestamp DESC")->fetchAll();
        $spese = $pdo->query("SELECT * FROM spese ORDER BY timestamp DESC")->fetchAll();
        $prodotti = $pdo->query("SELECT * FROM prodotti ORDER BY categoria, nome")->fetchAll();
        
        // Genera Excel XML
        $excelContent = generateExcelXML($vendite, $spese, $prodotti);
        
        // Scrivi file
        $written = @file_put_contents($filepath, $excelContent);
        
        if ($written === false) {
            return ['success' => false, 'error' => 'Errore durante la scrittura del file. Spazio insufficiente o chiavetta rimossa.'];
        }
        
        // Sync per assicurarsi che sia scritto
        @exec('sync');
        
        // Calcola totali
        $totaleVendite = 0;
        $totaleSpese = 0;
        foreach ($vendite as $v) $totaleVendite += floatval($v['prezzo']);
        foreach ($spese as $s) $totaleSpese += floatval($s['importo']);
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $filepath,
            'vendite' => count($vendite),
            'spese' => count($spese),
            'totale_incasso' => $totaleVendite,
            'totale_spese' => $totaleSpese,
            'profitto' => $totaleVendite - $totaleSpese,
            'size' => formatBytes(filesize($filepath))
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore database: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Errore: ' . $e->getMessage()];
    }
}

// Lista backup esistenti
function listBackups() {
    $usbPath = findUSB();
    
    if (!$usbPath) {
        return ['success' => false, 'error' => 'Nessuna chiavetta USB trovata', 'backups' => []];
    }
    
    $backupDir = $usbPath . '/ProlocoBackup';
    if (!is_dir($backupDir)) {
        return ['success' => true, 'backups' => []];
    }
    
    $files = glob($backupDir . '/backup_*.xls');
    $backups = [];
    
    foreach ($files as $file) {
        $backups[] = [
            'filename' => basename($file),
            'size' => formatBytes(filesize($file)),
            'date' => date('d/m/Y H:i', filemtime($file))
        ];
    }
    
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    return ['success' => true, 'backups' => $backups];
}

// ==================== HANDLER ====================
jsonHeaders();

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

switch ($action) {
    case 'status':
        jsonResponse(checkUSBStatus());
        break;
        
    case 'backup':
        jsonResponse(doBackup());
        break;
        
    case 'list':
        jsonResponse(listBackups());
        break;
        
    default:
        jsonResponse(['error' => 'Azione non valida'], 400);
}
