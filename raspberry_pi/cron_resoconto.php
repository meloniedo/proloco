#!/usr/bin/php
<?php
// ========================================
// GENERATORE RESOCONTI SETTIMANALI
// Crea resoconto in /home/pi/proloco/RESOCONTI_SETTIMANALI/
// ========================================

$baseDir = '/home/pi/proloco';
$resocontiDir = '/home/pi/proloco/RESOCONTI_SETTIMANALI';
$backupDir = '/home/pi/proloco/BACKUP_GIORNALIERI';

require_once $baseDir . '/includes/config.php';

// Crea cartelle se non esistono
if (!is_dir($resocontiDir)) mkdir($resocontiDir, 0755, true);
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

function generaResocontoSettimanale() {
    global $resocontiDir, $baseDir;
    
    try {
        $pdo = getDB();
        
        // Calcola periodo (ultima settimana)
        $oggi = new DateTime();
        $inizioSettimana = clone $oggi;
        $inizioSettimana->modify('-7 days');
        
        $dataInizio = $inizioSettimana->format('Y-m-d 00:00:00');
        $dataFine = $oggi->format('Y-m-d 23:59:59');
        
        // Vendite della settimana
        $stmt = $pdo->prepare("SELECT * FROM vendite WHERE timestamp BETWEEN ? AND ? ORDER BY timestamp DESC");
        $stmt->execute([$dataInizio, $dataFine]);
        $vendite = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Spese della settimana
        $stmt = $pdo->prepare("SELECT * FROM spese WHERE timestamp BETWEEN ? AND ? ORDER BY timestamp DESC");
        $stmt->execute([$dataInizio, $dataFine]);
        $spese = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcoli
        $totVendite = 0;
        $totSpese = 0;
        $prodottiCount = [];
        
        foreach ($vendite as $v) {
            $totVendite += floatval($v['prezzo']);
            $nome = $v['nome_prodotto'];
            if (!isset($prodottiCount[$nome])) {
                $prodottiCount[$nome] = ['count' => 0, 'totale' => 0];
            }
            $prodottiCount[$nome]['count']++;
            $prodottiCount[$nome]['totale'] += floatval($v['prezzo']);
        }
        
        foreach ($spese as $s) {
            $totSpese += floatval($s['importo']);
        }
        
        // Top 3 prodotti
        uasort($prodottiCount, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        $top3 = array_slice($prodottiCount, 0, 3, true);
        
        // Formatta date italiane
        setlocale(LC_TIME, 'it_IT.UTF-8', 'it_IT', 'italian');
        $periodoStr = $inizioSettimana->format('d-m-Y') . ' al ' . $oggi->format('d-m-Y');
        $oraGenerazione = strftime('%A %d %B %Y alle ore %H:%M', time());
        
        // Genera contenuto
        $content = "RESOCONTO SETTIMANALE\n";
        $content .= "Proloco Santa Bianca\n\n";
        $content .= "📅 Periodo: {$periodoStr}\n";
        $content .= "📧 Generato: {$oraGenerazione}\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $content .= "📊 RIEPILOGO SETTIMANA\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $content .= "Vendite Totali: " . count($vendite) . "\n";
        $content .= sprintf("Incasso Totale: €%.2f\n", $totVendite);
        $content .= sprintf("Spese Totali: €%.2f\n", $totSpese);
        $content .= sprintf("PROFITTO NETTO: €%.2f\n\n", $totVendite - $totSpese);
        $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $content .= "🏆 TOP 3 PRODOTTI\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        $i = 1;
        foreach ($top3 as $nome => $data) {
            $content .= sprintf("%d. %s: %d vendite - €%.2f\n", $i, $nome, $data['count'], $data['totale']);
            $i++;
        }
        
        $content .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $content .= "Generato automaticamente da Bar Manager\n";
        $content .= "Prossimo report: Lunedì alle 08:00\n";
        
        // Salva file settimanale
        $filename = "RESOCONTO_" . $inizioSettimana->format('d-m-Y') . "_" . $oggi->format('d-m-Y') . ".txt";
        file_put_contents($resocontiDir . '/' . $filename, $content);
        
        // Aggiorna RESOCONTO_TOTALE.txt
        aggiornaResocontoTotale();
        
        return ['success' => true, 'file' => $filename];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function aggiornaResocontoTotale() {
    global $resocontiDir, $baseDir;
    
    try {
        $pdo = getDB();
        
        // Tutte le vendite
        $vendite = $pdo->query("SELECT * FROM vendite ORDER BY timestamp DESC")->fetchAll(PDO::FETCH_ASSOC);
        $spese = $pdo->query("SELECT * FROM spese ORDER BY timestamp DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcoli totali
        $totVendite = 0;
        $totSpese = 0;
        $prodottiCount = [];
        $categorieVendite = [];
        
        foreach ($vendite as $v) {
            $totVendite += floatval($v['prezzo']);
            $nome = $v['nome_prodotto'];
            $cat = $v['categoria'] ?? 'ALTRO';
            
            if (!isset($prodottiCount[$nome])) {
                $prodottiCount[$nome] = ['count' => 0, 'totale' => 0];
            }
            $prodottiCount[$nome]['count']++;
            $prodottiCount[$nome]['totale'] += floatval($v['prezzo']);
            
            if (!isset($categorieVendite[$cat])) {
                $categorieVendite[$cat] = ['count' => 0, 'totale' => 0];
            }
            $categorieVendite[$cat]['count']++;
            $categorieVendite[$cat]['totale'] += floatval($v['prezzo']);
        }
        
        foreach ($spese as $s) {
            $totSpese += floatval($s['importo']);
        }
        
        // Top 5 prodotti
        uasort($prodottiCount, function($a, $b) { return $b['count'] - $a['count']; });
        $top5 = array_slice($prodottiCount, 0, 5, true);
        
        // Genera contenuto
        setlocale(LC_TIME, 'it_IT.UTF-8', 'it_IT', 'italian');
        $content = "╔══════════════════════════════════════════════════════════════╗\n";
        $content .= "║           RESOCONTO TOTALE - PROLOCO SANTA BIANCA           ║\n";
        $content .= "║     Ultimo aggiornamento: " . date('d/m/Y H:i:s') . "                 ║\n";
        $content .= "╚══════════════════════════════════════════════════════════════╝\n\n";
        
        $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $content .= "                    📊 RIEPILOGO GENERALE\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= sprintf("  Vendite Totali:      %d transazioni\n", count($vendite));
        $content .= sprintf("  Incasso Totale:      €%.2f\n", $totVendite);
        $content .= sprintf("  Spese Totali:        €%.2f\n", $totSpese);
        $content .= sprintf("  PROFITTO NETTO:      €%.2f\n\n", $totVendite - $totSpese);
        
        $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $content .= "                    🏆 TOP 5 PRODOTTI\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        $i = 1;
        foreach ($top5 as $nome => $data) {
            $content .= sprintf("  %d. %-25s %4d vendite   €%8.2f\n", $i, $nome, $data['count'], $data['totale']);
            $i++;
        }
        
        $content .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $content .= "                    📁 VENDITE PER CATEGORIA\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        foreach ($categorieVendite as $cat => $data) {
            $content .= sprintf("  %-20s %4d vendite   €%8.2f\n", $cat, $data['count'], $data['totale']);
        }
        
        $content .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $content .= "                    Generato da Bar Manager\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        file_put_contents($resocontiDir . '/RESOCONTO_TOTALE.txt', $content);
        
        // Copia anche nella cartella web per accesso da browser
        file_put_contents($baseDir . '/RESOCONTO_TOTALE.txt', $content);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Se eseguito direttamente, genera resoconto
if (php_sapi_name() === 'cli' || !isset($_SERVER['REQUEST_METHOD'])) {
    $result = generaResocontoSettimanale();
    if ($result['success']) {
        echo "✅ Resoconto generato: " . $result['file'] . "\n";
    } else {
        echo "❌ Errore: " . $result['error'] . "\n";
    }
}
