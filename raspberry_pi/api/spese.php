<?php
// ========================================
// API SPESE
// ========================================
require_once '../includes/config.php';
require_once '../includes/storico_txt.php';
jsonHeaders();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Lista spese (ultime 100)
        $pdo = getDB();
        $stmt = $pdo->query("SELECT * FROM spese ORDER BY timestamp DESC LIMIT 100");
        $spese = $stmt->fetchAll();
        jsonResponse($spese);
        break;
        
    case 'POST':
        // Nuova spesa
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['categoria_spesa']) || !isset($input['importo'])) {
            jsonResponse(['error' => 'Dati mancanti'], 400);
        }
        
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO spese (categoria_spesa, importo, note) VALUES (?, ?, ?)");
        $result = $stmt->execute([
            $input['categoria_spesa'],
            $input['importo'],
            $input['note'] ?? ''
        ]);
        
        if ($result) {
            // Aggiorna STORICO.txt
            aggiornaStoricoTxt();
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            jsonResponse(['error' => 'Errore salvataggio'], 500);
        }
        break;
        
    case 'DELETE':
        // Elimina spesa
        $id = $_GET['id'] ?? null;
        if (!$id) {
            jsonResponse(['error' => 'ID mancante'], 400);
        }
        
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM spese WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        // Aggiorna STORICO.txt
        aggiornaStoricoTxt();
        
        jsonResponse(['success' => $result]);
        break;
        
    default:
        jsonResponse(['error' => 'Metodo non supportato'], 405);
}
