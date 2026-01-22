<?php
// ========================================
// GESTIONE FILE STORICO.TXT
// Sincronizza automaticamente con il database
// ========================================

define('STORICO_FILE', __DIR__ . '/../STORICO.txt');

/**
 * Aggiorna il file STORICO.txt con tutti i dati dal database
 */
function aggiornaStoricoTxt() {
    try {
        $pdo = getDB();
        
        $vendite = $pdo->query("SELECT * FROM vendite ORDER BY timestamp DESC")->fetchAll(PDO::FETCH_ASSOC);
        $spese = $pdo->query("SELECT * FROM spese ORDER BY timestamp DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        $content = "╔══════════════════════════════════════════════════════════════╗\n";
        $content .= "║           STORICO PROLOCO BAR - SANTA BIANCA                ║\n";
        $content .= "║     Ultimo aggiornamento: " . date('d/m/Y H:i:s') . "                 ║\n";
        $content .= "╚══════════════════════════════════════════════════════════════╝\n\n";
        
        $content .= "ISTRUZIONI:\n";
        $content .= "- Per eliminare un record, cancella l'intera riga [V:xxx] o [S:xxx]\n";
        $content .= "- Poi apri: http://192.168.4.1/api/sync_storico.php\n";
        $content .= "- I record cancellati dal file verranno rimossi dal database\n\n";
        
        // Calcola totali
        $totVendite = 0;
        $totSpese = 0;
        foreach ($vendite as $v) $totVendite += floatval($v['prezzo']);
        foreach ($spese as $s) $totSpese += floatval($s['importo']);
        
        $content .= "════════════════════════════════════════════════════════════════\n";
        $content .= "                         RIEPILOGO\n";
        $content .= "════════════════════════════════════════════════════════════════\n";
        $content .= sprintf("  Totale Vendite:   €%10.2f\n", $totVendite);
        $content .= sprintf("  Totale Spese:     €%10.2f\n", $totSpese);
        $content .= sprintf("  PROFITTO NETTO:   €%10.2f\n", $totVendite - $totSpese);
        $content .= "════════════════════════════════════════════════════════════════\n\n";
        
        // VENDITE
        $content .= "┌──────────────────────────────────────────────────────────────┐\n";
        $content .= "│                         VENDITE                              │\n";
        $content .= "│                    (" . count($vendite) . " transazioni)                         │\n";
        $content .= "└──────────────────────────────────────────────────────────────┘\n\n";
        
        if (count($vendite) > 0) {
            foreach ($vendite as $v) {
                $dt = new DateTime($v['timestamp']);
                $content .= sprintf("[V:%d] %s %s | %-25s | %-15s | €%.2f\n",
                    $v['id'],
                    $dt->format('d/m/Y'),
                    $dt->format('H:i:s'),
                    mb_substr($v['nome_prodotto'], 0, 25),
                    mb_substr($v['categoria'] ?? '', 0, 15),
                    $v['prezzo']
                );
            }
        } else {
            $content .= "(Nessuna vendita registrata)\n";
        }
        
        $content .= "\n";
        
        // SPESE
        $content .= "┌──────────────────────────────────────────────────────────────┐\n";
        $content .= "│                          SPESE                               │\n";
        $content .= "│                    (" . count($spese) . " transazioni)                          │\n";
        $content .= "└──────────────────────────────────────────────────────────────┘\n\n";
        
        if (count($spese) > 0) {
            foreach ($spese as $s) {
                $dt = new DateTime($s['timestamp']);
                $note = $s['note'] ?? '';
                $content .= sprintf("[S:%d] %s %s | %-20s | %-15s | €%.2f\n",
                    $s['id'],
                    $dt->format('d/m/Y'),
                    $dt->format('H:i:s'),
                    mb_substr($s['categoria_spesa'], 0, 20),
                    mb_substr($note, 0, 15),
                    $s['importo']
                );
            }
        } else {
            $content .= "(Nessuna spesa registrata)\n";
        }
        
        $content .= "\n════════════════════════════════════════════════════════════════\n";
        $content .= "                    FINE STORICO\n";
        $content .= "════════════════════════════════════════════════════════════════\n";
        
        // Scrivi file
        file_put_contents(STORICO_FILE, $content);
        
        return true;
    } catch (Exception $e) {
        error_log("Errore aggiornamento STORICO.txt: " . $e->getMessage());
        return false;
    }
}

/**
 * Sincronizza il database con il file STORICO.txt
 * Cancella dal DB i record non presenti nel file
 */
function sincronizzaStoricoConDB() {
    if (!file_exists(STORICO_FILE)) {
        return ['success' => false, 'error' => 'File STORICO.txt non trovato'];
    }
    
    try {
        $pdo = getDB();
        $content = file_get_contents(STORICO_FILE);
        
        // Estrai tutti gli ID vendite dal file [V:xxx]
        preg_match_all('/\[V:(\d+)\]/', $content, $matchesVendite);
        $idVenditeNelFile = array_map('intval', $matchesVendite[1]);
        
        // Estrai tutti gli ID spese dal file [S:xxx]
        preg_match_all('/\[S:(\d+)\]/', $content, $matchesSpese);
        $idSpeseNelFile = array_map('intval', $matchesSpese[1]);
        
        // Ottieni tutti gli ID dal database
        $idVenditeDB = $pdo->query("SELECT id FROM vendite")->fetchAll(PDO::FETCH_COLUMN);
        $idSpeseDB = $pdo->query("SELECT id FROM spese")->fetchAll(PDO::FETCH_COLUMN);
        
        // Trova ID da cancellare (presenti nel DB ma non nel file)
        $venditeToDelete = array_diff($idVenditeDB, $idVenditeNelFile);
        $speseToDelete = array_diff($idSpeseDB, $idSpeseNelFile);
        
        $deletedVendite = 0;
        $deletedSpese = 0;
        
        // Cancella vendite
        if (count($venditeToDelete) > 0) {
            $placeholders = implode(',', array_fill(0, count($venditeToDelete), '?'));
            $stmt = $pdo->prepare("DELETE FROM vendite WHERE id IN ($placeholders)");
            $stmt->execute(array_values($venditeToDelete));
            $deletedVendite = $stmt->rowCount();
        }
        
        // Cancella spese
        if (count($speseToDelete) > 0) {
            $placeholders = implode(',', array_fill(0, count($speseToDelete), '?'));
            $stmt = $pdo->prepare("DELETE FROM spese WHERE id IN ($placeholders)");
            $stmt->execute(array_values($speseToDelete));
            $deletedSpese = $stmt->rowCount();
        }
        
        // Aggiorna il file STORICO.txt con i dati aggiornati
        aggiornaStoricoTxt();
        
        return [
            'success' => true,
            'vendite_cancellate' => $deletedVendite,
            'spese_cancellate' => $deletedSpese,
            'message' => "Sincronizzazione completata: $deletedVendite vendite e $deletedSpese spese rimosse"
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
