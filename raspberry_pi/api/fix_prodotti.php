<?php
// ========================================
// SCRIPT FIX PRODOTTI - Rimuove duplicati e aggiunge Sguazzone
// Esegui una volta sola: http://192.168.4.1/api/fix_prodotti.php
// ========================================
require_once '../includes/config.php';

try {
    $pdo = getDB();
    
    echo "<h2>🔧 Fix Prodotti Database</h2>";
    
    // 1. Trova e rimuovi duplicati (tieni solo il primo di ogni nome)
    echo "<h3>1. Rimozione duplicati...</h3>";
    
    $duplicati = $pdo->query("
        SELECT nome, COUNT(*) as cnt, GROUP_CONCAT(id) as ids 
        FROM prodotti 
        GROUP BY nome 
        HAVING cnt > 1
    ")->fetchAll();
    
    $rimossi = 0;
    foreach ($duplicati as $dup) {
        $ids = explode(',', $dup['ids']);
        $idDaMantenere = array_shift($ids); // Mantieni il primo
        
        if (count($ids) > 0) {
            $idsToDelete = implode(',', $ids);
            $pdo->exec("DELETE FROM prodotti WHERE id IN ($idsToDelete)");
            $rimossi += count($ids);
            echo "<p>✅ '{$dup['nome']}': rimossi " . count($ids) . " duplicati (mantenuto ID $idDaMantenere)</p>";
        }
    }
    
    if ($rimossi == 0) {
        echo "<p>✅ Nessun duplicato trovato</p>";
    } else {
        echo "<p><strong>Totale rimossi: $rimossi</strong></p>";
    }
    
    // 2. Aggiungi Sguazzone se non esiste
    echo "<h3>2. Aggiunta Sguazzone...</h3>";
    
    $esisteSguazzone = $pdo->query("SELECT id FROM prodotti WHERE nome = 'Sguazzone'")->fetch();
    
    if (!$esisteSguazzone) {
        $pdo->exec("INSERT INTO prodotti (nome, prezzo, categoria, icona, attivo) VALUES ('Sguazzone', 1.00, 'BEVANDE', '🍷', 1)");
        echo "<p>✅ Sguazzone aggiunto (€1.00, BEVANDE, 🍷)</p>";
    } else {
        echo "<p>✅ Sguazzone già presente (ID: {$esisteSguazzone['id']})</p>";
    }
    
    // 3. Mostra listino attuale
    echo "<h3>3. Listino Prodotti Attuale:</h3>";
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
    echo "<tr style='background:#8B4513;color:white;'><th>ID</th><th>Nome</th><th>Prezzo</th><th>Categoria</th><th>Icona</th></tr>";
    
    $prodotti = $pdo->query("SELECT * FROM prodotti WHERE attivo = 1 ORDER BY categoria, nome")->fetchAll();
    foreach ($prodotti as $p) {
        echo "<tr>";
        echo "<td>{$p['id']}</td>";
        echo "<td>{$p['nome']}</td>";
        echo "<td>€" . number_format($p['prezzo'], 2) . "</td>";
        echo "<td>{$p['categoria']}</td>";
        echo "<td>{$p['icona']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>✅ FATTO!</h3>";
    echo "<p><a href='/'>← Torna all'app</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Errore</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
