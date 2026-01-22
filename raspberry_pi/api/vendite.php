<?php
// ========================================
// API VENDITE
// ========================================
require_once '../includes/config.php';
jsonHeaders();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Lista vendite (ultime 100)
        $pdo = getDB();
        $stmt = $pdo->query("SELECT * FROM vendite ORDER BY timestamp DESC LIMIT 100");
        $vendite = $stmt->fetchAll();
        jsonResponse($vendite);
        break;
        
    case 'POST':
        // Nuova vendita
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['prodotto_id']) || !isset($input['prezzo'])) {
            jsonResponse(['error' => 'Dati mancanti'], 400);
        }
        
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO vendite (prodotto_id, nome_prodotto, prezzo, categoria) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([
            $input['prodotto_id'],
            $input['nome_prodotto'],
            $input['prezzo'],
            $input['categoria']
        ]);
        
        if ($result) {
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            jsonResponse(['error' => 'Errore salvataggio'], 500);
        }
        break;
        
    case 'DELETE':
        // Elimina vendita
        $id = $_GET['id'] ?? null;
        if (!$id) {
            jsonResponse(['error' => 'ID mancante'], 400);
        }
        
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM vendite WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        jsonResponse(['success' => $result]);
        break;
        
    default:
        jsonResponse(['error' => 'Metodo non supportato'], 405);
}
