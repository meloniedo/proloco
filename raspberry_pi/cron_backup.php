#!/usr/bin/php
<?php
// ========================================
// CRON BACKUP AUTOMATICO
// Esegue backup settimanale programmato
// ========================================

$baseDir = '/home/pi/proloco/raspberry_pi';

require_once $baseDir . '/includes/config.php';

// Log
$logFile = $baseDir . '/logs/backup.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) mkdir($logDir, 0755, true);

function logBackup($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
}

// Verifica se oggi è un giorno programmato per il backup
function isBackupDay() {
    $pdo = getDB();
    
    // Leggi giorni programmati dalla configurazione
    $stmt = $pdo->prepare("SELECT valore FROM configurazione WHERE chiave = 'backup_giorni'");
    $stmt->execute();
    $row = $stmt->fetch();
    
    // Default: solo domenica (0)
    $giorni = $row ? $row['valore'] : '0';
    $giorniArray = explode(',', $giorni);
    
    $oggi = date('w'); // 0 = domenica, 6 = sabato
    
    return in_array($oggi, $giorniArray);
}

// Verifica se è l'ora giusta
function isBackupTime() {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT valore FROM configurazione WHERE chiave = 'backup_ora'");
    $stmt->execute();
    $row = $stmt->fetch();
    
    // Default: 23:59
    $oraProgrammata = $row ? $row['valore'] : '23:59';
    $oraAttuale = date('H:i');
    
    return $oraAttuale === $oraProgrammata;
}

// Tenta backup USB
function tentaBackupUSB() {
    global $baseDir;
    
    // Includi funzioni USB
    require_once $baseDir . '/api/usb_backup.php';
    
    $usbPath = findUSB();
    if (!$usbPath) {
        return ['success' => false, 'error' => 'Nessuna USB trovata'];
    }
    
    try {
        $pdo = getDB();
        $vendite = $pdo->query("SELECT * FROM vendite ORDER BY timestamp ASC")->fetchAll(PDO::FETCH_ASSOC);
        $spese = $pdo->query("SELECT * FROM spese ORDER BY timestamp ASC")->fetchAll(PDO::FETCH_ASSOC);
        $prodotti = $pdo->query("SELECT * FROM prodotti ORDER BY categoria, nome")->fetchAll(PDO::FETCH_ASSOC);
        
        $excelContent = generateExcelXML($vendite, $spese, $prodotti);
        
        $date = date('d-m-Y');
        $time = date('H-i');
        $filename = "StoricoBarProloco_backup_auto_{$date}_{$time}.xlsx";
        $filepath = $usbPath . '/' . $filename;
        
        if (file_put_contents($filepath, $excelContent) !== false) {
            @exec('sync');
            return ['success' => true, 'file' => $filename, 'path' => $filepath];
        } else {
            return ['success' => false, 'error' => 'Impossibile scrivere su USB'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Salva backup in cartella locale (come fallback WiFi)
function tentaBackupLocale() {
    global $baseDir;
    
    $backupDir = $baseDir . '/backups';
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    
    try {
        $pdo = getDB();
        $vendite = $pdo->query("SELECT * FROM vendite ORDER BY timestamp ASC")->fetchAll(PDO::FETCH_ASSOC);
        $spese = $pdo->query("SELECT * FROM spese ORDER BY timestamp ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // Trova date
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
        
        $totV = 0; $totS = 0;
        foreach ($vendite as $v) $totV += floatval($v['prezzo']);
        foreach ($spese as $s) $totS += floatval($s['importo']);
        
        // Crea XML Excel
        $xml = '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#8B4513" ss:Pattern="Solid"/></Style><Style ss:ID="Money"><NumberFormat ss:Format="#,##0.00"/></Style></Styles>';
        $xml .= '<Worksheet ss:Name="VENDITE"><Table><Row><Cell ss:StyleID="Header"><Data ss:Type="String">Data</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Ora</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Prodotto</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Importo</Data></Cell></Row>';
        foreach ($vendite as $v) {
            $dt = new DateTime($v['timestamp']);
            $xml .= '<Row><Cell><Data ss:Type="String">'.$dt->format('d/m/Y').'</Data></Cell><Cell><Data ss:Type="String">'.$dt->format('H:i:s').'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($v['nome_prodotto']).'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($v['categoria']??'').'</Data></Cell><Cell ss:StyleID="Money"><Data ss:Type="Number">'.$v['prezzo'].'</Data></Cell></Row>';
        }
        $xml .= '</Table></Worksheet>';
        $xml .= '<Worksheet ss:Name="SPESE"><Table><Row><Cell ss:StyleID="Header"><Data ss:Type="String">Data</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Ora</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Categoria</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Note</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Importo</Data></Cell></Row>';
        foreach ($spese as $s) {
            $dt = new DateTime($s['timestamp']);
            $xml .= '<Row><Cell><Data ss:Type="String">'.$dt->format('d/m/Y').'</Data></Cell><Cell><Data ss:Type="String">'.$dt->format('H:i:s').'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($s['categoria_spesa']).'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($s['note']??'').'</Data></Cell><Cell ss:StyleID="Money"><Data ss:Type="Number">'.$s['importo'].'</Data></Cell></Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';
        
        $filename = "StoricoBarProloco-dal-".date('d-m-Y', $dateMin)."-a-".date('d-m-Y', $dateMax)."_auto.xls";
        $filepath = $backupDir . '/' . $filename;
        
        if (file_put_contents($filepath, $xml) !== false) {
            return ['success' => true, 'file' => $filename, 'path' => $filepath];
        } else {
            return ['success' => false, 'error' => 'Impossibile scrivere backup locale'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Segna che serve backup manuale
function segnalaBackupNecessario() {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO configurazione (chiave, valore) VALUES ('backup_necessario', ?) ON DUPLICATE KEY UPDATE valore = ?");
    $val = date('Y-m-d H:i:s');
    $stmt->execute([$val, $val]);
}

// Resetta flag backup necessario
function resetBackupNecessario() {
    $pdo = getDB();
    $pdo->exec("DELETE FROM configurazione WHERE chiave = 'backup_necessario'");
}

// ===== MAIN =====

// Verifica se è il momento del backup
if (!isBackupDay()) {
    exit; // Non è un giorno programmato
}

if (!isBackupTime()) {
    exit; // Non è l'ora giusta
}

logBackup("=== INIZIO BACKUP AUTOMATICO ===");

// 1. Tenta backup USB
logBackup("Tentativo backup USB...");
$risultatoUSB = tentaBackupUSB();

if ($risultatoUSB['success']) {
    logBackup("✓ Backup USB riuscito: " . $risultatoUSB['file']);
    resetBackupNecessario();
    exit;
}

logBackup("✗ Backup USB fallito: " . $risultatoUSB['error']);

// 2. Tenta backup locale (scaricabile via WiFi)
logBackup("Tentativo backup locale (WiFi)...");
$risultatoLocale = tentaBackupLocale();

if ($risultatoLocale['success']) {
    logBackup("✓ Backup locale riuscito: " . $risultatoLocale['file']);
    logBackup("  File disponibile in /backups/ per download WiFi");
    resetBackupNecessario();
    exit;
}

logBackup("✗ Backup locale fallito: " . $risultatoLocale['error']);

// 3. Segna che serve backup manuale
logBackup("⚠ Backup automatico fallito - segnalato per intervento manuale");
segnalaBackupNecessario();

logBackup("=== FINE BACKUP AUTOMATICO ===");
