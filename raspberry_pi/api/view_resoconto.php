<?php
// ========================================
// VISUALIZZA RESOCONTO TOTALE
// ========================================
require_once '../includes/config.php';

// Include le funzioni del resoconto
$baseDir = '/home/pi/proloco';
$resocontiDir = '/home/pi/proloco/RESOCONTI_SETTIMANALI';

// Funzione per aggiornare il resoconto totale inline
function aggiornaResocontoTotaleInline() {
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
        
        // Organizza per mese e settimana
        $mesiDati = [];
        
        foreach ($vendite as $v) {
            $totVendite += floatval($v['prezzo']);
            $nome = $v['nome_prodotto'];
            $cat = $v['categoria'] ?? 'ALTRO';
            $timestamp = strtotime($v['timestamp']);
            
            // Calcola mese e settimana
            $mese = date('Y-m', $timestamp);
            $meseNome = getMeseItaliano(date('n', $timestamp)) . ' ' . date('Y', $timestamp);
            $settimana = ceil(date('j', $timestamp) / 7);
            if ($settimana > 4) $settimana = 4;
            
            if (!isset($mesiDati[$mese])) {
                $mesiDati[$mese] = [
                    'nome' => $meseNome,
                    'settimane' => [1 => ['entrate' => 0, 'spese' => 0], 2 => ['entrate' => 0, 'spese' => 0], 3 => ['entrate' => 0, 'spese' => 0], 4 => ['entrate' => 0, 'spese' => 0]]
                ];
            }
            $mesiDati[$mese]['settimane'][$settimana]['entrate'] += floatval($v['prezzo']);
            
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
            $timestamp = strtotime($s['timestamp']);
            
            // Calcola mese e settimana per le spese
            $mese = date('Y-m', $timestamp);
            $meseNome = getMeseItaliano(date('n', $timestamp)) . ' ' . date('Y', $timestamp);
            $settimana = ceil(date('j', $timestamp) / 7);
            if ($settimana > 4) $settimana = 4;
            
            if (!isset($mesiDati[$mese])) {
                $mesiDati[$mese] = [
                    'nome' => $meseNome,
                    'settimane' => [1 => ['entrate' => 0, 'spese' => 0], 2 => ['entrate' => 0, 'spese' => 0], 3 => ['entrate' => 0, 'spese' => 0], 4 => ['entrate' => 0, 'spese' => 0]]
                ];
            }
            $mesiDati[$mese]['settimane'][$settimana]['spese'] += floatval($s['importo']);
        }
        
        // Ordina mesi dal più recente
        krsort($mesiDati);
        
        // Top 5 prodotti
        uasort($prodottiCount, function($a, $b) { return $b['count'] - $a['count']; });
        $top5 = array_slice($prodottiCount, 0, 5, true);
        
        // Genera contenuto
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
        
        // SEZIONE RESOCONTI MENSILI DIVISI PER SETTIMANE
        $content .= "\n\n";
        $content .= "╔══════════════════════════════════════════════════════════════╗\n";
        $content .= "║              📅 RESOCONTI MENSILI PER SETTIMANA              ║\n";
        $content .= "╚══════════════════════════════════════════════════════════════╝\n";
        
        foreach ($mesiDati as $mese => $datiMese) {
            $content .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $content .= "                    " . strtoupper($datiMese['nome']) . "\n";
            $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            
            $totaleMeseEntrate = 0;
            $totaleMeseSpese = 0;
            
            for ($sett = 1; $sett <= 4; $sett++) {
                $entrate = $datiMese['settimane'][$sett]['entrate'];
                $speseSett = $datiMese['settimane'][$sett]['spese'];
                $netto = $entrate - $speseSett;
                
                $totaleMeseEntrate += $entrate;
                $totaleMeseSpese += $speseSett;
                
                $content .= "  📆 SETTIMANA $sett:\n";
                $content .= sprintf("     Entrate:   €%8.2f\n", $entrate);
                $content .= sprintf("     Spese:     €%8.2f\n", $speseSett);
                $content .= sprintf("     NETTO:     €%8.2f\n\n", $netto);
            }
            
            $content .= "  ────────────────────────────────────────\n";
            $content .= sprintf("  TOTALE %s:\n", strtoupper($datiMese['nome']));
            $content .= sprintf("     Entrate:   €%8.2f\n", $totaleMeseEntrate);
            $content .= sprintf("     Spese:     €%8.2f\n", $totaleMeseSpese);
            $content .= sprintf("     NETTO:     €%8.2f\n", $totaleMeseEntrate - $totaleMeseSpese);
        }
        
        $content .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $content .= "                    Generato da Bar Manager\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        // Salva nelle cartelle se esistono
        if (is_dir($resocontiDir)) {
            @file_put_contents($resocontiDir . '/RESOCONTO_TOTALE.txt', $content);
        }
        @file_put_contents(dirname(__DIR__) . '/RESOCONTO_TOTALE.txt', $content);
        
        return $content;
    } catch (Exception $e) {
        return "Errore generazione resoconto: " . $e->getMessage();
    }
}

// Funzione per ottenere il nome del mese in italiano
function getMeseItaliano($mese) {
    $mesi = [
        1 => 'GENNAIO', 2 => 'FEBBRAIO', 3 => 'MARZO', 4 => 'APRILE',
        5 => 'MAGGIO', 6 => 'GIUGNO', 7 => 'LUGLIO', 8 => 'AGOSTO',
        9 => 'SETTEMBRE', 10 => 'OTTOBRE', 11 => 'NOVEMBRE', 12 => 'DICEMBRE'
    ];
    return $mesi[$mese] ?? '';
}

header('Content-Type: text/plain; charset=utf-8');

// Genera e mostra il resoconto aggiornato
echo aggiornaResocontoTotaleInline();
