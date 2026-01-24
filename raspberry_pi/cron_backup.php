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
        
        $date = date('d-m-Y');
        $time = date('H-i');
        $filename = "StoricoBarProloco_backup_auto_{$date}_{$time}.xlsx";
        $filepath = $usbPath . '/' . $filename;
        
        // Usa la nuova funzione XLSX
        if (generateExcelXLSX($vendite, $spese, $filepath)) {
            @exec('sync');
            return ['success' => true, 'file' => $filename, 'path' => $filepath];
        } else {
            return ['success' => false, 'error' => 'Impossibile creare file XLSX'];
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
        
        $filename = "StoricoBarProloco-dal-".date('d-m-Y', $dateMin)."-a-".date('d-m-Y', $dateMax)."_auto.xlsx";
        $filepath = $backupDir . '/' . $filename;
        
        // Genera XLSX vero con ZipArchive - UNICO FOGLIO
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                
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

                // Shared strings
                $strings = [];
                $stringMap = [];
                
                $addStr = function($s) use (&$strings, &$stringMap) {
                    $s = (string)$s;
                    if (!isset($stringMap[$s])) {
                        $stringMap[$s] = count($strings);
                        $strings[] = htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    }
                    return $stringMap[$s];
                };
                
                $colLetter = function($col) {
                    $letter = '';
                    while ($col >= 0) {
                        $letter = chr(65 + ($col % 26)) . $letter;
                        $col = intval($col / 26) - 1;
                    }
                    return $letter;
                };
                
                // Costruisci righe - UNICO FOGLIO
                $rows = [];
                $rowNum = 1;
                
                // Titolo
                $rows[] = '<row r="' . $rowNum . '"><c r="A' . $rowNum . '" t="s"><v>' . $addStr('PROLOCO SANTA BIANCA - STORICO') . '</v></c></row>';
                $rowNum += 2;
                
                // Header vendite
                $headers = ['Data', 'Ora', 'Prodotto', 'Categoria', 'Importo €'];
                $row = '<row r="' . $rowNum . '">';
                foreach ($headers as $col => $header) {
                    $row .= '<c r="' . $colLetter($col) . $rowNum . '" t="s"><v>' . $addStr($header) . '</v></c>';
                }
                $row .= '</row>';
                $rows[] = $row;
                $rowNum++;
                
                // Dati vendite
                foreach ($vendite as $v) {
                    $dt = new DateTime($v['timestamp']);
                    $row = '<row r="' . $rowNum . '">';
                    $row .= '<c r="A' . $rowNum . '" t="s"><v>' . $addStr($dt->format('d/m/Y')) . '</v></c>';
                    $row .= '<c r="B' . $rowNum . '" t="s"><v>' . $addStr($dt->format('H:i:s')) . '</v></c>';
                    $row .= '<c r="C' . $rowNum . '" t="s"><v>' . $addStr($v['nome_prodotto']) . '</v></c>';
                    $row .= '<c r="D' . $rowNum . '" t="s"><v>' . $addStr($v['categoria'] ?? '') . '</v></c>';
                    $row .= '<c r="E' . $rowNum . '"><v>' . $v['prezzo'] . '</v></c>';
                    $row .= '</row>';
                    $rows[] = $row;
                    $rowNum++;
                }
                
                // Totale vendite
                $rows[] = '<row r="' . $rowNum . '"><c r="D' . $rowNum . '" t="s"><v>' . $addStr('TOTALE VENDITE:') . '</v></c><c r="E' . $rowNum . '"><v>' . $totV . '</v></c></row>';
                $rowNum += 2;
                
                // Sezione SPESE
                $rows[] = '<row r="' . $rowNum . '"><c r="A' . $rowNum . '" t="s"><v>' . $addStr('SPESE') . '</v></c></row>';
                $rowNum++;
                
                // Header spese
                $row = '<row r="' . $rowNum . '">';
                $row .= '<c r="A' . $rowNum . '" t="s"><v>' . $addStr('Data') . '</v></c>';
                $row .= '<c r="B' . $rowNum . '" t="s"><v>' . $addStr('Ora') . '</v></c>';
                $row .= '<c r="C' . $rowNum . '" t="s"><v>' . $addStr('Categoria') . '</v></c>';
                $row .= '<c r="E' . $rowNum . '" t="s"><v>' . $addStr('Importo €') . '</v></c>';
                $row .= '</row>';
                $rows[] = $row;
                $rowNum++;
                
                // Dati spese
                foreach ($spese as $s) {
                    $dt = new DateTime($s['timestamp']);
                    $row = '<row r="' . $rowNum . '">';
                    $row .= '<c r="A' . $rowNum . '" t="s"><v>' . $addStr($dt->format('d/m/Y')) . '</v></c>';
                    $row .= '<c r="B' . $rowNum . '" t="s"><v>' . $addStr($dt->format('H:i:s')) . '</v></c>';
                    $row .= '<c r="C' . $rowNum . '" t="s"><v>' . $addStr($s['categoria_spesa']) . '</v></c>';
                    $row .= '<c r="E' . $rowNum . '"><v>' . $s['importo'] . '</v></c>';
                    $row .= '</row>';
                    $rows[] = $row;
                    $rowNum++;
                }
                
                // Totale spese
                $rows[] = '<row r="' . $rowNum . '"><c r="D' . $rowNum . '" t="s"><v>' . $addStr('TOTALE SPESE:') . '</v></c><c r="E' . $rowNum . '"><v>' . $totS . '</v></c></row>';
                
                // Sheet XML
                $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . implode("\n", $rows) . '</sheetData></worksheet>');
                
                // Shared strings XML
                $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
                foreach ($strings as $str) $ssXml .= '<si><t>' . $str . '</t></si>';
                $ssXml .= '</sst>';
                $zip->addFromString('xl/sharedStrings.xml', $ssXml);
                
                $zip->close();
                return ['success' => true, 'file' => $filename, 'path' => $filepath];
            }
        }
        
        return ['success' => false, 'error' => 'ZipArchive non disponibile'];
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
