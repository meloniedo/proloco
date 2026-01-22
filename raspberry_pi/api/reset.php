<?php
// ========================================
// API RESET PERIODO
// ========================================
require_once '../includes/config.php';
jsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metodo non supportato'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$periodo = $input['periodo'] ?? 'oggi';
$password = $input['password'] ?? '';

// Verifica password
$passwordCorretta = getConfig('password_reset') ?? '5054';
if ($password !== $passwordCorretta) {
    jsonResponse(['error' => 'Password errata'], 403);
}

$pdo = getDB();

// Calcola data inizio periodo
switch ($periodo) {
    case 'oggi':
        $dataInizio = date('Y-m-d 00:00:00');
        break;
    case 'settimana':
        $dataInizio = date('Y-m-d H:i:s', strtotime('-7 days'));
        break;
    case 'mese':
        $dataInizio = date('Y-m-d H:i:s', strtotime('-30 days'));
        break;
    default:
        $dataInizio = date('Y-m-d 00:00:00');
}

// Elimina vendite nel periodo
$stmtVendite = $pdo->prepare("DELETE FROM vendite WHERE timestamp >= ?");
$stmtVendite->execute([$dataInizio]);
$venditeEliminate = $stmtVendite->rowCount();

// Elimina spese nel periodo
$stmtSpese = $pdo->prepare("DELETE FROM spese WHERE timestamp >= ?");
$stmtSpese->execute([$dataInizio]);
$speseEliminate = $stmtSpese->rowCount();

jsonResponse([
    'success' => true,
    'vendite_eliminate' => $venditeEliminate,
    'spese_eliminate' => $speseEliminate
]);
