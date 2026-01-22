<?php
// ========================================
// API BACKUP SU USB
// ========================================
require_once '../includes/config.php';

// Possibili mount point per USB
$USB_PATHS = [
    '/media/usb_backup',
    '/media/usb0',
    '/media/usb1',
    '/media/pi',
    '/mnt/usb'
];

// Trova chiavetta USB montata
function findUSB() {
    global $USB_PATHS;
    
    foreach ($USB_PATHS as $path) {
        if (is_dir($path) && is_writable($path)) {
            // Verifica che sia effettivamente un mount point USB
            $mountInfo = shell_exec("mount | grep '$path'");
            if (!empty($mountInfo)) {
                return $path;
            }
            // Controlla anche se c'è spazio (indica che è montato)
            $freeSpace = disk_free_space($path);
            if ($freeSpace !== false && $freeSpace > 0) {
                return $path;
            }
        }
    }
    
    // Cerca qualsiasi dispositivo USB montato
    $mounts = shell_exec("lsblk -o MOUNTPOINT -n | grep '/media'");
    if (!empty($mounts)) {
        $lines = explode("\n", trim($mounts));
        foreach ($lines as $mount) {
            $mount = trim($mount);
            if (!empty($mount) && is_writable($mount)) {
                return $mount;
            }
        }
    }
    
    return null;
}

// Verifica stato USB
function checkUSBStatus() {
    $usbPath = findUSB();
    
    if ($usbPath) {
        $freeSpace = disk_free_space($usbPath);
        $totalSpace = disk_total_space($usbPath);
        
        return [
            'connected' => true,
            'path' => $usbPath,
            'free_space' => round($freeSpace / 1024 / 1024, 2), // MB
            'total_space' => round($totalSpace / 1024 / 1024, 2), // MB
            'free_space_formatted' => formatBytes($freeSpace),
            'total_space_formatted' => formatBytes($totalSpace)
        ];
    }
    
    return [
        'connected' => false,
        'message' => 'Nessuna chiavetta USB rilevata. Inserisci una chiavetta USB e riprova.'
    ];
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } else {
        return round($bytes / 1024, 2) . ' KB';
    }
}

// Esegui backup
function doBackup() {
    $usbPath = findUSB();
    
    if (!$usbPath) {
        return ['success' => false, 'error' => 'Nessuna chiavetta USB trovata'];
    }
    
    // Crea directory backup
    $backupDir = $usbPath . '/proloco_backup';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }
    
    // Nome file con data
    $date = date('Y-m-d');
    $time = date('H-i-s');
    $filename = "backup_{$date}_{$time}.csv";
    $filepath = $backupDir . '/' . $filename;
    
    // Controlla se esiste già un backup oggi (senza orario)
    $existingBackups = glob($backupDir . "/backup_{$date}_*.csv");
    $backupNumber = count($existingBackups) + 1;
    
    if ($backupNumber > 1) {
        $filename = "backup_{$date}_n{$backupNumber}.csv";
        $filepath = $backupDir . '/' . $filename;
    }
    
    try {
        $pdo = getDB();
        
        // Ottieni dati
        $vendite = $pdo->query("SELECT * FROM vendite ORDER BY timestamp DESC")->fetchAll();
        $spese = $pdo->query("SELECT * FROM spese ORDER BY timestamp DESC")->fetchAll();
        $prodotti = $pdo->query("SELECT * FROM prodotti ORDER BY categoria, nome")->fetchAll();
        
        // Apri file
        $file = fopen($filepath, 'w');
        if (!$file) {
            return ['success' => false, 'error' => 'Impossibile creare file su USB'];
        }
        
        // BOM per Excel
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($file, ['=== BACKUP PROLOCO SANTA BIANCA ==='], ';');
        fputcsv($file, ['Data backup: ' . date('d/m/Y H:i:s')], ';');
        fputcsv($file, [''], ';');
        
        // PRODOTTI (LISTINO)
        fputcsv($file, ['=== LISTINO PRODOTTI ==='], ';');
        fputcsv($file, ['ID', 'Nome', 'Prezzo', 'Categoria', 'Icona', 'Attivo'], ';');
        foreach ($prodotti as $p) {
            fputcsv($file, [
                $p['id'],
                $p['nome'],
                number_format($p['prezzo'], 2, ',', ''),
                $p['categoria'],
                $p['icona'],
                $p['attivo']
            ], ';');
        }
        fputcsv($file, [''], ';');
        
        // VENDITE
        fputcsv($file, ['=== VENDITE ==='], ';');
        fputcsv($file, ['ID', 'Data', 'Ora', 'Prodotto', 'Categoria', 'Importo'], ';');
        $totaleVendite = 0;
        foreach ($vendite as $v) {
            $dt = new DateTime($v['timestamp']);
            fputcsv($file, [
                $v['id'],
                $dt->format('d/m/Y'),
                $dt->format('H:i:s'),
                $v['nome_prodotto'],
                $v['categoria'],
                number_format($v['prezzo'], 2, ',', '')
            ], ';');
            $totaleVendite += $v['prezzo'];
        }
        fputcsv($file, ['', '', '', '', 'TOTALE VENDITE:', number_format($totaleVendite, 2, ',', '')], ';');
        fputcsv($file, [''], ';');
        
        // SPESE
        fputcsv($file, ['=== SPESE ==='], ';');
        fputcsv($file, ['ID', 'Data', 'Ora', 'Categoria', 'Note', 'Importo'], ';');
        $totaleSpese = 0;
        foreach ($spese as $s) {
            $dt = new DateTime($s['timestamp']);
            fputcsv($file, [
                $s['id'],
                $dt->format('d/m/Y'),
                $dt->format('H:i:s'),
                $s['categoria_spesa'],
                $s['note'],
                number_format($s['importo'], 2, ',', '')
            ], ';');
            $totaleSpese += $s['importo'];
        }
        fputcsv($file, ['', '', '', '', 'TOTALE SPESE:', number_format($totaleSpese, 2, ',', '')], ';');
        fputcsv($file, [''], ';');
        
        // RIEPILOGO
        fputcsv($file, ['=== RIEPILOGO ==='], ';');
        fputcsv($file, ['Totale Vendite', count($vendite)], ';');
        fputcsv($file, ['Totale Spese', count($spese)], ';');
        fputcsv($file, ['Incasso Totale', number_format($totaleVendite, 2, ',', '') . ' €'], ';');
        fputcsv($file, ['Spese Totali', number_format($totaleSpese, 2, ',', '') . ' €'], ';');
        fputcsv($file, ['PROFITTO NETTO', number_format($totaleVendite - $totaleSpese, 2, ',', '') . ' €'], ';');
        
        fclose($file);
        
        // Sync per assicurarsi che sia scritto
        shell_exec('sync');
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $filepath,
            'vendite' => count($vendite),
            'spese' => count($spese),
            'totale_incasso' => $totaleVendite,
            'totale_spese' => $totaleSpese,
            'profitto' => $totaleVendite - $totaleSpese
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Errore: ' . $e->getMessage()];
    }
}

// Lista backup esistenti su USB
function listBackups() {
    $usbPath = findUSB();
    
    if (!$usbPath) {
        return ['success' => false, 'error' => 'Nessuna chiavetta USB trovata'];
    }
    
    $backupDir = $usbPath . '/proloco_backup';
    if (!is_dir($backupDir)) {
        return ['success' => true, 'backups' => []];
    }
    
    $files = glob($backupDir . '/backup_*.csv');
    $backups = [];
    
    foreach ($files as $file) {
        $backups[] = [
            'filename' => basename($file),
            'size' => formatBytes(filesize($file)),
            'date' => date('d/m/Y H:i', filemtime($file))
        ];
    }
    
    // Ordina per data decrescente
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
