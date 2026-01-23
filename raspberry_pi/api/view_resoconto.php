<?php
// ========================================
// VISUALIZZA RESOCONTO TOTALE
// ========================================
require_once '../includes/config.php';
require_once 'cron_resoconto.php';

// Aggiorna il resoconto prima di mostrarlo
aggiornaResocontoTotale();

$file = dirname(__DIR__) . '/RESOCONTO_TOTALE.txt';

header('Content-Type: text/plain; charset=utf-8');

if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo "Resoconto non ancora generato.\n";
    echo "Effettua almeno una vendita per generare il resoconto.";
}
