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

// Genera file XLSX vero con ZipArchive - UNICO FOGLIO
function generateExcelXLSX($vendite, $spese, $filepath) {
    $totaleVendite = 0;
    $totaleSpese = 0;
    
    foreach ($vendite as $v) $totaleVendite += floatval($v['prezzo']);
    foreach ($spese as $s) $totaleSpese += floatval($s['importo']);
    
    if (!class_exists('ZipArchive')) {
        return false;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }
    
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
    $rows[] = '<row r="' . $rowNum . '"><c r="D' . $rowNum . '" t="s"><v>' . $addStr('TOTALE VENDITE:') . '</v></c><c r="E' . $rowNum . '"><v>' . $totaleVendite . '</v></c></row>';
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
    $rows[] = '<row r="' . $rowNum . '"><c r="D' . $rowNum . '" t="s"><v>' . $addStr('TOTALE SPESE:') . '</v></c><c r="E' . $rowNum . '"><v>' . $totaleSpese . '</v></c></row>';
    
    // Sheet XML
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . implode("\n", $rows) . '</sheetData></worksheet>');
    
    // Shared strings XML
    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
    foreach ($strings as $str) $ssXml .= '<si><t>' . $str . '</t></si>';
    $ssXml .= '</sst>';
    $zip->addFromString('xl/sharedStrings.xml', $ssXml);
    
    $zip->close();
    return true;
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
    
    // Nome file con data nel formato storico_DD-MM-YYYY_HH-MM.xlsx
    $date = date('d-m-Y');
    $time = date('H-i');
    
    // Controlla backup esistenti oggi
    $existingBackups = glob($backupDir . "/storico_{$date}*.xlsx");
    $backupNum = count($existingBackups) + 1;
    
    $filename = "storico_{$date}_{$time}";
    if ($backupNum > 1) {
        $filename .= "_n{$backupNum}";
    }
    $filename .= ".xlsx";
    
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
    
    $files = glob($backupDir . '/backup_*.xlsx');
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
