<?php
// ========================================
// API PRODOTTI
// ========================================
require_once '../includes/config.php';
jsonHeaders();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Lista prodotti
        $pdo = getDB();
        $stmt = $pdo->query("SELECT * FROM prodotti WHERE attivo = 1 ORDER BY categoria, nome");
        $prodotti = $stmt->fetchAll();
        jsonResponse($prodotti);
        break;
        
    default:
        jsonResponse(['error' => 'Metodo non supportato'], 405);
}
