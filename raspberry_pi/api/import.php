<?php
// ========================================
// API IMPORT BACKUP
// ========================================
require_once '../includes/config.php';
jsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metodo non supportato'], 405);
}

if (!isset($_FILES['file'])) {
    jsonResponse(['error' => 'File non ricevuto'], 400);
}

$file = $_FILES['file'];
$tmpPath = $file['tmp_name'];

// Leggi il file CSV
$handle = fopen($tmpPath, 'r');
if (!$handle) {
    jsonResponse(['error' => 'Impossibile aprire il file'], 500);
}

$pdo = getDB();
$venditeImportate = 0;
$speseImportate = 0;
$sezione = '';

// Salta BOM se presente
$bom = fread($handle, 3);
if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
    rewind($handle);
}

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (empty($row) || empty($row[0])) continue;
    
    // Identifica sezione
    if (strpos($row[0], '=== VENDITE ===') !== false) {
        $sezione = 'vendite';
        fgetcsv($handle, 0, ';'); // Salta header
        continue;
    }
    if (strpos($row[0], '=== SPESE ===') !== false) {
        $sezione = 'spese';
        fgetcsv($handle, 0, ';'); // Salta header
        continue;
    }
    if (strpos($row[0], '=== RIEPILOGO ===') !== false) {
        break;
    }
    if (strpos($row[0], 'TOTALE') !== false) {
        continue;
    }
    
    // Importa dati
    if ($sezione === 'vendite' && count($row) >= 6 && is_numeric($row[0])) {
        try {
            // Parsa data e ora
            $dateParts = explode('/', $row[1]);
            $timeParts = explode(':', $row[2]);
            $timestamp = sprintf('%04d-%02d-%02d %02d:%02d:%02d',
                $dateParts[2], $dateParts[1], $dateParts[0],
                $timeParts[0], $timeParts[1], $timeParts[2] ?? 0
            );
            
            $prezzo = str_replace(',', '.', $row[5]);
            
            $stmt = $pdo->prepare("INSERT INTO vendite (nome_prodotto, categoria, prezzo, timestamp) VALUES (?, ?, ?, ?)");
            $stmt->execute([$row[3], $row[4], $prezzo, $timestamp]);
            $venditeImportate++;
        } catch (Exception $e) {
            // Continua con la prossima riga
        }
    }
    
    if ($sezione === 'spese' && count($row) >= 6 && is_numeric($row[0])) {
        try {
            $dateParts = explode('/', $row[1]);
            $timeParts = explode(':', $row[2]);
            $timestamp = sprintf('%04d-%02d-%02d %02d:%02d:%02d',
                $dateParts[2], $dateParts[1], $dateParts[0],
                $timeParts[0], $timeParts[1], $timeParts[2] ?? 0
            );
            
            $importo = str_replace(',', '.', $row[5]);
            
            $stmt = $pdo->prepare("INSERT INTO spese (categoria_spesa, note, importo, timestamp) VALUES (?, ?, ?, ?)");
            $stmt->execute([$row[3], $row[4], $importo, $timestamp]);
            $speseImportate++;
        } catch (Exception $e) {
            // Continua con la prossima riga
        }
    }
}

fclose($handle);

jsonResponse([
    'success' => true,
    'vendite_importate' => $venditeImportate,
    'spese_importate' => $speseImportate
]);
