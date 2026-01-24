<?php
// ========================================
// API BACKUP SU USB - CON EXCEL REALE
// ========================================
require_once '../includes/config.php';

// Trova chiavetta USB montata su Raspberry Pi
function findUSB() {
    $usbPath = null;
    
    // Metodo 1: Usa 'lsblk' per trovare dispositivi USB montati
    $lsblk = @shell_exec('lsblk -o NAME,MOUNTPOINT,SIZE,TYPE -J 2>/dev/null');
    if ($lsblk) {
        $data = json_decode($lsblk, true);
        if (isset($data['blockdevices'])) {
            foreach ($data['blockdevices'] as $device) {
                // Cerca partizioni montate
                if (isset($device['children'])) {
                    foreach ($device['children'] as $part) {
                        if (!empty($part['mountpoint']) && 
                            strpos($part['mountpoint'], '/media') === 0 &&
                            $part['mountpoint'] !== '/media') {
                            $mp = $part['mountpoint'];
                            if (is_dir($mp) && is_writable($mp)) {
                                return $mp;
                            }
                        }
                    }
                }
                // Dispositivo senza partizioni
                if (!empty($device['mountpoint']) && 
                    strpos($device['mountpoint'], '/media') === 0) {
                    $mp = $device['mountpoint'];
                    if (is_dir($mp) && is_writable($mp)) {
                        return $mp;
                    }
                }
            }
        }
    }
    
    // Metodo 2: Cerca in /media/* (chiavette montate direttamente)
    $mediaDir = '/media';
    if (is_dir($mediaDir)) {
        $dirs = @scandir($mediaDir);
        if ($dirs) {
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') continue;
                $fullPath = $mediaDir . '/' . $dir;
                if (is_dir($fullPath)) {
                    // Verifica che sia scrivibile
                    if (is_writable($fullPath)) {
                        $freeSpace = @disk_free_space($fullPath);
                        if ($freeSpace !== false && $freeSpace > 1048576) {
                            return $fullPath;
                        }
                    }
                    // Prova a renderlo scrivibile
                    @exec("sudo chmod 777 '$fullPath' 2>/dev/null");
                    if (is_writable($fullPath)) {
                        return $fullPath;
                    }
                }
            }
        }
    }
    
    // Metodo 3: Cerca anche in sottocartelle /media/utente/*
    if (is_dir($mediaDir)) {
        $dirs = @scandir($mediaDir);
        if ($dirs) {
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') continue;
                $userMediaPath = $mediaDir . '/' . $dir;
                if (is_dir($userMediaPath)) {
                    $subDirs = @scandir($userMediaPath);
                    if ($subDirs) {
                        foreach ($subDirs as $subDir) {
                            if ($subDir === '.' || $subDir === '..') continue;
                            $fullPath = $userMediaPath . '/' . $subDir;
                            if (is_dir($fullPath) && is_writable($fullPath)) {
                                $freeSpace = @disk_free_space($fullPath);
                                if ($freeSpace !== false && $freeSpace > 1048576) {
                                    return $fullPath;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    }
    
    // Metodo 4: Cerca in /mnt/*
    $mntDir = '/mnt';
    if (is_dir($mntDir)) {
        $dirs = @scandir($mntDir);
        if ($dirs) {
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') continue;
                $fullPath = $mntDir . '/' . $dir;
                if (is_dir($fullPath) && is_writable($fullPath)) {
                    $freeSpace = @disk_free_space($fullPath);
                    if ($freeSpace !== false && $freeSpace > 1048576) {
                        return $fullPath;
                    }
                }
            }
        }
    }
    
    // Metodo 5: Usa 'mount' per trovare dispositivi rimovibili
    $mount = @shell_exec('mount | grep -E "/media|/mnt" 2>/dev/null');
    if ($mount) {
        $lines = explode("\n", trim($mount));
        foreach ($lines as $line) {
            if (preg_match('/on\s+(\S+)\s+type/', $line, $matches)) {
                $mp = $matches[1];
                if (is_dir($mp) && is_writable($mp) && $mp !== '/media' && $mp !== '/mnt') {
                    return $mp;
                }
            }
        }
    }
    
    return null;
}

// Verifica stato USB con info dettagliate
function checkUSBStatus() {
    $usbPath = findUSB();
    
    // Debug info
    $debugInfo = [];
    $debugInfo['media_contents'] = is_dir('/media') ? @scandir('/media') : [];
    
    if ($usbPath) {
        $freeSpace = @disk_free_space($usbPath);
        $totalSpace = @disk_total_space($usbPath);
        
        return [
            'connected' => true,
            'path' => $usbPath,
            'free_space' => $freeSpace ? round($freeSpace / 1024 / 1024, 2) : 0,
            'total_space' => $totalSpace ? round($totalSpace / 1024 / 1024, 2) : 0,
            'free_space_formatted' => formatBytes($freeSpace ?: 0),
            'total_space_formatted' => formatBytes($totalSpace ?: 0),
            'writable' => is_writable($usbPath),
            'debug' => $debugInfo
        ];
    }
    
    return [
        'connected' => false,
        'error' => 'Nessuna chiavetta USB rilevata.',
        'suggerimento' => 'Inserisci la chiavetta USB nel Raspberry Pi e riprova.',
        'debug' => $debugInfo
    ];
}

function formatBytes($bytes) {
    if ($bytes <= 0) return '0 B';
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1024, 2) . ' KB';
}

// Genera file Excel XML compatibile con importazione - UNICO FOGLIO
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
 <Style ss:ID="Header"><Font ss:Bold="1" ss:Size="11"/><Interior ss:Color="#8B4513" ss:Pattern="Solid"/><Font ss:Color="#FFFFFF"/></Style>
 <Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style>
 <Style ss:ID="Money"><NumberFormat ss:Format="#,##0.00"/></Style>
 <Style ss:ID="Bold"><Font ss:Bold="1"/></Style>
</Styles>

<Worksheet ss:Name="Storico">
 <Table>
  <Row><Cell ss:StyleID="Title"><Data ss:Type="String">PROLOCO SANTA BIANCA - STORICO</Data></Cell></Row>
  <Row></Row>
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
  <Row><Cell></Cell><Cell></Cell><Cell></Cell><Cell ss:StyleID="Bold"><Data ss:Type="String">TOTALE VENDITE:</Data></Cell><Cell ss:StyleID="Bold"><Data ss:Type="Number">' . $totaleVendite . '</Data></Cell></Row>
  <Row></Row>
  <Row><Cell ss:StyleID="Title"><Data ss:Type="String">SPESE</Data></Cell></Row>
  <Row>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Data</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Ora</Data></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell>
   <Cell></Cell>
   <Cell ss:StyleID="Header"><Data ss:Type="String">Importo €</Data></Cell>
  </Row>';
  
    foreach ($spese as $s) {
        $dt = new DateTime($s['timestamp']);
        $xml .= '
  <Row>
   <Cell><Data ss:Type="String">' . $dt->format('d/m/Y') . '</Data></Cell>
   <Cell><Data ss:Type="String">' . $dt->format('H:i:s') . '</Data></Cell>
   <Cell><Data ss:Type="String">' . htmlspecialchars($s['categoria_spesa']) . '</Data></Cell>
   <Cell></Cell>
   <Cell ss:StyleID="Money"><Data ss:Type="Number">' . $s['importo'] . '</Data></Cell>
  </Row>';
    }
    
    $xml .= '
  <Row></Row>
  <Row><Cell></Cell><Cell></Cell><Cell></Cell><Cell ss:StyleID="Bold"><Data ss:Type="String">TOTALE SPESE:</Data></Cell><Cell ss:StyleID="Bold"><Data ss:Type="Number">' . $totaleSpese . '</Data></Cell></Row>
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
    
    // Nome file con data nel formato storico_DD-MM-YYYY_HH-MM.xls
    $date = date('d-m-Y');
    $time = date('H-i');
    
    // Controlla backup esistenti oggi
    $existingBackups = glob($backupDir . "/storico_{$date}*.xls");
    $backupNum = count($existingBackups) + 1;
    
    $filename = "storico_{$date}_{$time}";
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
        
        // Scrivi file su USB
        $written = @file_put_contents($filepath, $excelContent);
        
        if ($written === false) {
            return ['success' => false, 'error' => 'Errore durante la scrittura del file. Spazio insufficiente o chiavetta rimossa.'];
        }
        
        // Salva copia locale in /home/pi/proloco/BACKUP_GIORNALIERI
        $backupLocaleDir = '/home/pi/proloco/BACKUP_GIORNALIERI';
        if (!is_dir($backupLocaleDir)) @mkdir($backupLocaleDir, 0755, true);
        @file_put_contents($backupLocaleDir . '/' . $filename, $excelContent);
        
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
