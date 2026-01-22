<?php
// ========================================
// CONFIGURAZIONE DATABASE
// Proloco Santa Bianca - Bar Manager
// ========================================

define('DB_HOST', 'localhost');
define('DB_USER', 'edo');
define('DB_PASS', '5054');
define('DB_NAME', 'proloco_bar');

// Connessione al database
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Errore connessione database: ' . $e->getMessage()]));
        }
    }
    
    return $pdo;
}

// Categorie spese
$CATEGORIE_SPESE = [
    ['nome' => 'Cialde caffè', 'icona' => '☕', 'colore' => 'bg-amber-700'],
    ['nome' => 'Vino', 'icona' => '🍷', 'colore' => 'bg-red-800'],
    ['nome' => 'Articoli Pulizia', 'icona' => '🧹', 'colore' => 'bg-teal-700'],
    ['nome' => 'Articoli S. Mercato', 'icona' => '🛒', 'colore' => 'bg-green-700'],
    ['nome' => 'Rimborso Servizio', 'icona' => '💼', 'colore' => 'bg-stone-700'],
    ['nome' => 'Spesa Generica', 'icona' => '📋', 'colore' => 'bg-stone-600']
];

// Funzione per ottenere configurazione
function getConfig($chiave) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT valore FROM configurazione WHERE chiave = ?");
    $stmt->execute([$chiave]);
    $result = $stmt->fetch();
    return $result ? $result['valore'] : null;
}

// Funzione per salvare configurazione
function setConfig($chiave, $valore) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO configurazione (chiave, valore) VALUES (?, ?) ON DUPLICATE KEY UPDATE valore = ?");
    return $stmt->execute([$chiave, $valore, $valore]);
}

// Headers JSON
function jsonHeaders() {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE');
    header('Access-Control-Allow-Headers: Content-Type');
}

// Risposta JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
