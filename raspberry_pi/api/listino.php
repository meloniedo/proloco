<?php
// ========================================
// API GESTIONE LISTINO (Impostazioni)
// ========================================
require_once '../includes/config.php';
require_once '../includes/listino_txt.php';
jsonHeaders();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Lista tutti i prodotti (anche inattivi per gestione)
        $pdo = getDB();
        $stmt = $pdo->query("SELECT * FROM prodotti ORDER BY categoria, nome");
        $prodotti = $stmt->fetchAll();
        jsonResponse($prodotti);
        break;
        
    case 'POST':
        // Aggiungi nuovo prodotto
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['nome']) || !isset($input['categoria'])) {
            jsonResponse(['error' => 'Nome e categoria obbligatori'], 400);
        }
        
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO prodotti (nome, prezzo, categoria, icona, attivo) VALUES (?, ?, ?, ?, 1)");
        $result = $stmt->execute([
            $input['nome'],
            $input['prezzo'] ?? 0,
            $input['categoria'],
            $input['icona'] ?? '📦'
        ]);
        
        if ($result) {
            // Aggiorna LISTINO.txt
            aggiornaListinoDaDB();
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            jsonResponse(['error' => 'Errore inserimento'], 500);
        }
        break;
        
    case 'PUT':
        // Modifica prodotto esistente
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            jsonResponse(['error' => 'ID obbligatorio'], 400);
        }
        
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE prodotti SET nome = ?, prezzo = ?, categoria = ?, icona = ?, attivo = ? WHERE id = ?");
        $result = $stmt->execute([
            $input['nome'],
            $input['prezzo'] ?? 0,
            $input['categoria'],
            $input['icona'] ?? '📦',
            $input['attivo'] ?? 1,
            $input['id']
        ]);
        
        jsonResponse(['success' => $result]);
        break;
        
    case 'DELETE':
        // Elimina prodotto (soft delete - disattiva)
        $id = $_GET['id'] ?? null;
        if (!$id) {
            jsonResponse(['error' => 'ID mancante'], 400);
        }
        
        $pdo = getDB();
        
        // Opzione 1: Soft delete (disattiva)
        // $stmt = $pdo->prepare("UPDATE prodotti SET attivo = 0 WHERE id = ?");
        
        // Opzione 2: Hard delete (elimina completamente)
        $stmt = $pdo->prepare("DELETE FROM prodotti WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        jsonResponse(['success' => $result]);
        break;
        
    default:
        jsonResponse(['error' => 'Metodo non supportato'], 405);
}
