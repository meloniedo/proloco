<?php
// ========================================
// API IMPORT BACKUP - Legge file Excel XML/XLS
// ========================================
require_once '../includes/config.php';

// Abilita error reporting per debug
error_reporting(E_ALL);
ini_set('display_errors', 0);

function parseExcelXML($content) {
    $vendite = [];
    $spese = [];
    
    // Rimuovi BOM se presente
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    
    // Controlla se è XML Excel
    if (strpos($content, 'urn:schemas-microsoft-com:office:spreadsheet') === false) {
        return ['error' => 'Formato file non supportato. Usa un backup generato da questa app.'];
    }
    
    // Parse XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    
    if ($xml === false) {
        $errors = libxml_get_errors();
        return ['error' => 'Errore parsing XML: ' . ($errors[0]->message ?? 'sconosciuto')];
    }
    
    // Registra namespace
    $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
    
    // Trova fogli
    $worksheets = $xml->xpath('//ss:Worksheet');
    
    foreach ($worksheets as $worksheet) {
        $sheetName = (string)$worksheet->attributes('ss', true)->Name;
        $rows = $worksheet->xpath('.//ss:Row');
        
        // Foglio Vendite
        if (stripos($sheetName, 'Vendite') !== false || stripos($sheetName, 'VENDITE') !== false) {
            $isHeader = true;
            foreach ($rows as $row) {
                $cells = $row->xpath('.//ss:Cell/ss:Data');
                if (count($cells) < 3) continue;
                
                // Salta header
                if ($isHeader) {
                    $isHeader = false;
                    continue;
                }
                
                // Salta riga TOTALE
                $firstCell = (string)$cells[0];
                if (empty($firstCell) || stripos($firstCell, 'TOTALE') !== false || stripos($firstCell, 'Totale') !== false) {
                    continue;
                }
                
                // Formato: ID, Data, Prodotto, Categoria, Importo
                // oppure: Data, Ora, Prodotto, Categoria, Importo
                $hasId = is_numeric($firstCell);
                
                if ($hasId && count($cells) >= 5) {
                    // Formato con ID
                    $dataOra = (string)$cells[1];
                    $prodotto = (string)$cells[2];
                    $categoria = (string)$cells[3];
                    $importo = (string)$cells[4];
                } else if (count($cells) >= 5) {
                    // Formato Data, Ora, Prodotto, Categoria, Importo
                    $data = (string)$cells[0];
                    $ora = (string)$cells[1];
                    $dataOra = $data . ' ' . $ora;
                    $prodotto = (string)$cells[2];
                    $categoria = (string)$cells[3];
                    $importo = (string)$cells[4];
                } else if (count($cells) >= 4) {
                    // Formato Data+Ora, Prodotto, Categoria, Importo
                    $dataOra = (string)$cells[0];
                    $prodotto = (string)$cells[1];
                    $categoria = (string)$cells[2];
                    $importo = (string)$cells[3];
                } else {
                    continue;
                }
                
                if (empty($prodotto)) continue;
                
                // Converti data
                $timestamp = parseDateTime($dataOra);
                
                $vendite[] = [
                    'nome_prodotto' => trim($prodotto),
                    'categoria' => trim($categoria),
                    'prezzo' => floatval(str_replace(',', '.', $importo)),
                    'timestamp' => $timestamp
                ];
            }
        }
        
        // Foglio Spese
        if (stripos($sheetName, 'Spese') !== false || stripos($sheetName, 'SPESE') !== false) {
            $isHeader = true;
            foreach ($rows as $row) {
                $cells = $row->xpath('.//ss:Cell/ss:Data');
                if (count($cells) < 3) continue;
                
                if ($isHeader) {
                    $isHeader = false;
                    continue;
                }
                
                $firstCell = (string)$cells[0];
                if (empty($firstCell) || stripos($firstCell, 'TOTALE') !== false || stripos($firstCell, 'Totale') !== false) {
                    continue;
                }
                
                // Formato: ID, Data, Categoria, Note, Importo
                // oppure: Data, Ora, Categoria, Spesa, Note, Importo
                $hasId = is_numeric($firstCell);
                
                if ($hasId && count($cells) >= 5) {
                    $dataOra = (string)$cells[1];
                    $categoriaSpesa = (string)$cells[2];
                    $note = count($cells) > 3 ? (string)$cells[3] : '';
                    $importo = (string)$cells[4];
                } else if (count($cells) >= 6) {
                    // Data, Ora, Categoria, Spesa, Note, Importo
                    $data = (string)$cells[0];
                    $ora = (string)$cells[1];
                    $dataOra = $data . ' ' . $ora;
                    $categoriaSpesa = (string)$cells[2];
                    $note = (string)$cells[4]; // Note è colonna 5
                    $importo = (string)$cells[5];
                } else if (count($cells) >= 4) {
                    $dataOra = (string)$cells[0];
                    $categoriaSpesa = (string)$cells[1];
                    $note = count($cells) > 2 ? (string)$cells[2] : '';
                    $importo = (string)$cells[count($cells) - 1];
                } else {
                    continue;
                }
                
                if (empty($categoriaSpesa)) continue;
                
                $timestamp = parseDateTime($dataOra);
                
                $spese[] = [
                    'categoria_spesa' => trim($categoriaSpesa),
                    'note' => trim($note),
                    'importo' => floatval(str_replace(',', '.', $importo)),
                    'timestamp' => $timestamp
                ];
            }
        }
    }
    
    return ['vendite' => $vendite, 'spese' => $spese];
}

function parseDateTime($dataOra) {
    $dataOra = trim($dataOra);
    
    // Prova diversi formati
    $formats = [
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd/m/Y',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d'
    ];
    
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $dataOra);
        if ($dt !== false) {
            return $dt->format('Y-m-d H:i:s');
        }
    }
    
    // Prova parsing generico
    try {
        $dt = new DateTime($dataOra);
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return date('Y-m-d H:i:s');
    }
}

// Gestione upload
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonHeaders();
    jsonResponse(['error' => 'Metodo non consentito'], 405);
    exit;
}

// Verifica file
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'Nessun file caricato';
    if (isset($_FILES['file'])) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite server)',
            UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form)',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto',
            UPLOAD_ERR_NO_FILE => 'Nessun file selezionato',
            UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea mancante',
            UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere file',
        ];
        $errorMsg = $errors[$_FILES['file']['error']] ?? 'Errore upload sconosciuto';
    }
    jsonHeaders();
    jsonResponse(['success' => false, 'error' => $errorMsg]);
    exit;
}

$file = $_FILES['file'];
$filename = $file['name'];
$tmpPath = $file['tmp_name'];

// Verifica estensione
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, ['xls', 'xlsx', 'xml'])) {
    jsonHeaders();
    jsonResponse(['success' => false, 'error' => 'Formato file non supportato. Usa file .xls generato da questa app.']);
    exit;
}

// Leggi contenuto
$content = file_get_contents($tmpPath);

if (empty($content)) {
    jsonHeaders();
    jsonResponse(['success' => false, 'error' => 'File vuoto o non leggibile']);
    exit;
}

// Parse file
$result = parseExcelXML($content);

if (isset($result['error'])) {
    jsonHeaders();
    jsonResponse(['success' => false, 'error' => $result['error']]);
    exit;
}

$vendite = $result['vendite'];
$spese = $result['spese'];

// Inserisci nel database
try {
    $pdo = getDB();
    
    $venditeImportate = 0;
    $speseImportate = 0;
    
    // Importa vendite
    $stmtVendita = $pdo->prepare("INSERT INTO vendite (prodotto_id, nome_prodotto, prezzo, categoria, timestamp) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($vendite as $v) {
        // Trova prodotto_id dal nome
        $stmtProd = $pdo->prepare("SELECT id FROM prodotti WHERE nome = ? LIMIT 1");
        $stmtProd->execute([$v['nome_prodotto']]);
        $prodotto = $stmtProd->fetch();
        $prodottoId = $prodotto ? $prodotto['id'] : 0;
        
        $stmtVendita->execute([
            $prodottoId,
            $v['nome_prodotto'],
            $v['prezzo'],
            $v['categoria'],
            $v['timestamp']
        ]);
        $venditeImportate++;
    }
    
    // Importa spese
    $stmtSpesa = $pdo->prepare("INSERT INTO spese (categoria_spesa, importo, note, timestamp) VALUES (?, ?, ?, ?)");
    
    foreach ($spese as $s) {
        $stmtSpesa->execute([
            $s['categoria_spesa'],
            $s['importo'],
            $s['note'],
            $s['timestamp']
        ]);
        $speseImportate++;
    }
    
    jsonHeaders();
    jsonResponse([
        'success' => true,
        'vendite_importate' => $venditeImportate,
        'spese_importate' => $speseImportate,
        'message' => "Importazione completata: {$venditeImportate} vendite, {$speseImportate} spese"
    ]);
    
} catch (PDOException $e) {
    jsonHeaders();
    jsonResponse(['success' => false, 'error' => 'Errore database: ' . $e->getMessage()]);
}
