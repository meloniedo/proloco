#!/usr/bin/php
<?php
/**
 * TEST DATABASE - Verifica connessione e operazioni
 */

echo "\n=== TEST DATABASE PROLOCO BAR ===\n\n";

require_once __DIR__ . '/includes/config.php';

try {
    $pdo = getDB();
    echo "✅ Connessione OK\n";
    echo "   Host: " . DB_HOST . "\n";
    echo "   Database: " . DB_NAME . "\n";
    echo "   Utente: " . DB_USER . "\n\n";
} catch (Exception $e) {
    echo "❌ Errore connessione: " . $e->getMessage() . "\n";
    exit(1);
}

// Conta record
echo "=== STATO ATTUALE ===\n";
$vendite = $pdo->query("SELECT COUNT(*) as n FROM vendite")->fetch()['n'];
$spese = $pdo->query("SELECT COUNT(*) as n FROM spese")->fetch()['n'];
$prodotti = $pdo->query("SELECT COUNT(*) as n FROM prodotti")->fetch()['n'];

echo "Vendite:  $vendite\n";
echo "Spese:    $spese\n";
echo "Prodotti: $prodotti\n\n";

// Mostra ultime 5 vendite
echo "=== ULTIME 5 VENDITE ===\n";
$rows = $pdo->query("SELECT id, nome_prodotto, prezzo, categoria, timestamp FROM vendite ORDER BY id DESC LIMIT 5")->fetchAll();
if (count($rows) == 0) {
    echo "(nessuna vendita)\n";
} else {
    foreach ($rows as $r) {
        echo "ID:{$r['id']} | {$r['nome_prodotto']} | €{$r['prezzo']} | {$r['categoria']} | {$r['timestamp']}\n";
    }
}
echo "\n";

// Mostra ultime 5 spese
echo "=== ULTIME 5 SPESE ===\n";
$rows = $pdo->query("SELECT id, categoria_spesa, importo, timestamp FROM spese ORDER BY id DESC LIMIT 5")->fetchAll();
if (count($rows) == 0) {
    echo "(nessuna spesa)\n";
} else {
    foreach ($rows as $r) {
        echo "ID:{$r['id']} | {$r['categoria_spesa']} | €{$r['importo']} | {$r['timestamp']}\n";
    }
}
echo "\n";

// Test INSERT
echo "=== TEST INSERT ===\n";
try {
    $stmt = $pdo->prepare("INSERT INTO vendite (nome_prodotto, prezzo, categoria, timestamp) VALUES (?, ?, ?, ?)");
    $stmt->execute(['TEST_PRODOTTO', 1.00, 'CAFFETTERIA', date('Y-m-d H:i:s')]);
    $lastId = $pdo->lastInsertId();
    echo "✅ INSERT riuscito (ID: $lastId)\n";
    
    // Elimina il record di test
    $pdo->exec("DELETE FROM vendite WHERE id = $lastId");
    echo "✅ DELETE riuscito (record di test rimosso)\n";
} catch (Exception $e) {
    echo "❌ Errore INSERT: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETATO ===\n\n";
