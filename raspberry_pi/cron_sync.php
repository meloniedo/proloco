#!/usr/bin/php
<?php
// ========================================
// CRON SYNC - Sincronizza STORICO.txt con DB
// Eseguito automaticamente ogni minuto
// ========================================

// Percorso assoluto
$baseDir = '/var/www/html/proloco';

require_once $baseDir . '/includes/config.php';
require_once $baseDir . '/includes/storico_txt.php';

// Log
$logFile = $baseDir . '/logs/sync.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMsg($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
}

// Esegui sincronizzazione
$result = sincronizzaStoricoConDB();

if ($result['success']) {
    if ($result['vendite_cancellate'] > 0 || $result['spese_cancellate'] > 0) {
        logMsg("SYNC: Rimossi {$result['vendite_cancellate']} vendite, {$result['spese_cancellate']} spese");
    }
    // Non logga se non ci sono modifiche (per non riempire il log)
} else {
    logMsg("ERRORE: " . $result['error']);
}
