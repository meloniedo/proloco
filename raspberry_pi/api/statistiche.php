<?php
// ========================================
// API STATISTICHE
// ========================================
require_once '../includes/config.php';
jsonHeaders();

$periodo = $_GET['periodo'] ?? 'oggi';
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

// Totale vendite e incasso
$stmtVendite = $pdo->prepare("SELECT COUNT(*) as totale, COALESCE(SUM(prezzo), 0) as incasso FROM vendite WHERE timestamp >= ?");
$stmtVendite->execute([$dataInizio]);
$venditeData = $stmtVendite->fetch();

// Totale spese
$stmtSpese = $pdo->prepare("SELECT COALESCE(SUM(importo), 0) as totale FROM spese WHERE timestamp >= ?");
$stmtSpese->execute([$dataInizio]);
$speseData = $stmtSpese->fetch();

// Per categoria
$stmtCategorie = $pdo->prepare("SELECT categoria, COUNT(*) as vendite, SUM(prezzo) as incasso FROM vendite WHERE timestamp >= ? GROUP BY categoria");
$stmtCategorie->execute([$dataInizio]);
$categorieData = $stmtCategorie->fetchAll();
$perCategoria = [];
foreach ($categorieData as $cat) {
    $perCategoria[$cat['categoria']] = [
        'vendite' => (int)$cat['vendite'],
        'incasso' => (float)$cat['incasso']
    ];
}

// Top 5 prodotti
$stmtTop = $pdo->prepare("SELECT nome_prodotto, COUNT(*) as quantita, SUM(prezzo) as incasso FROM vendite WHERE timestamp >= ? GROUP BY nome_prodotto ORDER BY quantita DESC LIMIT 5");
$stmtTop->execute([$dataInizio]);
$topProdotti = $stmtTop->fetchAll();

$response = [
    'totaleVendite' => (int)$venditeData['totale'],
    'totaleIncasso' => (float)$venditeData['incasso'],
    'totaleSpese' => (float)$speseData['totale'],
    'profittoNetto' => (float)$venditeData['incasso'] - (float)$speseData['totale'],
    'perCategoria' => $perCategoria,
    'prodottiPiuVenduti' => array_map(function($p) {
        return [
            'nome' => $p['nome_prodotto'],
            'quantita' => (int)$p['quantita'],
            'incasso' => (float)$p['incasso']
        ];
    }, $topProdotti),
    'periodo' => $periodo
];

jsonResponse($response);
