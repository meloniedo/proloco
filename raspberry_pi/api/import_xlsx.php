<?php
/**
 * ========================================
 * API IMPORTAZIONE XLSX
 * ========================================
 * Endpoint per importare vendite e spese da file Excel
 * 
 * STRUTTURA FILE: UN SOLO FOGLIO con:
 * - Sezione VENDITE (in alto)
 * - TOTALE VENDITE
 * - Riga "SPESE"
 * - Header spese
 * - Sezione SPESE (in basso)
 * - TOTALE SPESE
 */

require_once '../includes/config.php';

header('Content-Type: application/json');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

// Verifica file caricato
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errori = [
        UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite server)',
        UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form)',
        UPLOAD_ERR_PARTIAL => 'Upload incompleto',
        UPLOAD_ERR_NO_FILE => 'Nessun file caricato',
        UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea mancante',
        UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere su disco',
    ];
    $errore = $errori[$_FILES['file']['error']] ?? 'Errore sconosciuto';
    echo json_encode(['success' => false, 'error' => $errore]);
    exit;
}

$filePath = $_FILES['file']['tmp_name'];
$fileName = $_FILES['file']['name'];

// Verifica estensione
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if ($ext !== 'xlsx' && $ext !== 'xls') {
    echo json_encode(['success' => false, 'error' => 'Formato non supportato. Usa file .xlsx']);
    exit;
}

// Funzioni helper
function colToIndex($col) {
    $col = strtoupper($col);
    $index = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

function readSheet($zip, $sheetNum, $sharedStrings) {
    $sheetXml = $zip->getFromName("xl/worksheets/sheet{$sheetNum}.xml");
    if (!$sheetXml) return [];
    
    $sheet = simplexml_load_string($sheetXml);
    $rows = [];
    
    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        $maxCol = 0;
        
        foreach ($row->c as $cell) {
            $cellRef = (string)$cell['r'];
            preg_match('/([A-Z]+)(\d+)/', $cellRef, $matches);
            $colIndex = colToIndex($matches[1]);
            $maxCol = max($maxCol, $colIndex);
            
            $value = '';
            if (isset($cell->v)) {
                $value = (string)$cell->v;
                if (isset($cell['t']) && (string)$cell['t'] === 's') {
                    $value = $sharedStrings[(int)$value] ?? $value;
                }
            }
            $rowData[$colIndex] = $value;
        }
        
        for ($i = 0; $i <= $maxCol; $i++) {
            if (!isset($rowData[$i])) $rowData[$i] = '';
        }
        ksort($rowData);
        $rows[] = array_values($rowData);
    }
    
    return $rows;
}

function excelDateToMysql($excelDate, $time = '') {
    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $excelDate, $m)) {
        $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        return $time ? $date . ' ' . $time : $date . ' 12:00:00';
    }
    
    if (is_numeric($excelDate)) {
        $unixDate = ($excelDate - 25569) * 86400;
        $date = date('Y-m-d', $unixDate);
        return $time ? $date . ' ' . $time : $date . ' 12:00:00';
    }
    
    return date('Y-m-d H:i:s');
}

try {
    $pdo = getDB();
    
    // Apri xlsx
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception('Impossibile aprire il file xlsx');
    }
    
    // Leggi shared strings
    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml) {
        $ssXml = simplexml_load_string($sharedStringsXml);
        foreach ($ssXml->si as $si) {
            $sharedStrings[] = (string)$si->t;
        }
    }
    
    $risultato = [
        'vendite_importate' => 0,
        'vendite_errori' => 0,
        'spese_importate' => 0,
        'spese_errori' => 0
    ];
    
    // Leggi UNICO foglio (sheet1) - vendite E spese sono nello stesso foglio
    $allRows = readSheet($zip, 1, $sharedStrings);
    
    // PARSING A STATI
    $modalita = 'none';
    
    $stmtVendita = $pdo->prepare("INSERT INTO vendite (nome_prodotto, prezzo, categoria, timestamp) VALUES (?, ?, ?, ?)");
    $stmtSpesa = $pdo->prepare("INSERT INTO spese (categoria_spesa, importo, note, timestamp) VALUES (?, ?, ?, ?)");
    
    for ($i = 0; $i < count($allRows); $i++) {
        $row = $allRows[$i];
        $firstCell = trim($row[0] ?? '');
        $thirdCell = trim($row[2] ?? '');
        
        // Unisci tutte le celle per cercare parole chiave in qualsiasi colonna
        $rowText = implode(' ', array_map('trim', $row));
        
        // Rileva intestazione VENDITE: riga con "Data" e "Prodotto"
        if ($firstCell === 'Data' && $thirdCell === 'Prodotto') {
            $modalita = 'vendite';
            continue;
        }
        
        // Rileva "TOTALE VENDITE" in qualsiasi colonna
        if (stripos($rowText, 'TOTALE VENDITE') !== false) {
            $modalita = 'attesa_spese';
            continue;
        }
        
        // Rileva riga "SPESE" in qualsiasi colonna (ma NON "TOTALE SPESE")
        if ($modalita === 'attesa_spese' && stripos($rowText, 'SPESE') !== false && stripos($rowText, 'TOTALE') === false) {
            $modalita = 'attesa_header_spese';
            continue;
        }
        
        // Rileva intestazione SPESE: riga con "Data" nella prima colonna (dopo riga SPESE)
        if (($modalita === 'attesa_spese' || $modalita === 'attesa_header_spese') && $firstCell === 'Data') {
            $modalita = 'spese';
            continue;
        }
        
        // Rileva "TOTALE SPESE" o "RIEPILOGO" in qualsiasi colonna
        if (stripos($rowText, 'TOTALE SPESE') !== false || stripos($rowText, 'RIEPILOGO') !== false) {
            $modalita = 'none';
            continue;
        }
        
        // Salta righe vuote o non valide
        if (empty($firstCell) || !preg_match('/\d{1,2}\/\d{1,2}\/\d{4}/', $firstCell)) {
            continue;
        }
        
        // IMPORTA VENDITE
        if ($modalita === 'vendite') {
            if (count($row) < 5) continue;
            
            $prodotto = trim($row[2]);
            $categoria = trim($row[3]);
            $importo = floatval(str_replace(',', '.', $row[4]));
            
            if (empty($prodotto) || $importo <= 0) continue;
            
            $timestamp = excelDateToMysql($row[0], $row[1]);
            
            try {
                $stmtVendita->execute([$prodotto, $importo, $categoria, $timestamp]);
                $risultato['vendite_importate']++;
            } catch (Exception $e) {
                $risultato['vendite_errori']++;
            }
        }
        // IMPORTA SPESE
        elseif ($modalita === 'spese') {
            $categoria = trim($row[2]);
            
            // Cerca l'importo - potrebbe essere in colonna 4 o oltre
            $importo = 0;
            for ($col = 3; $col < count($row); $col++) {
                $val = str_replace(',', '.', trim($row[$col]));
                if (is_numeric($val) && floatval($val) > 0) {
                    $importo = floatval($val);
                    break;
                }
            }
            
            if (empty($categoria) || $importo <= 0) continue;
            
            $timestamp = excelDateToMysql($row[0], $row[1]);
            
            try {
                $stmtSpesa->execute([$categoria, $importo, '', $timestamp]);
                $risultato['spese_importate']++;
            } catch (Exception $e) {
                $risultato['spese_errori']++;
            }
        }
    }
    
    $zip->close();
    
    // Aggiorna STORICO.txt
    require_once '../includes/storico_txt.php';
    aggiornaStoricoTxt();
    
    echo json_encode([
        'success' => true,
        'message' => 'Importazione completata!',
        'dettagli' => $risultato
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
