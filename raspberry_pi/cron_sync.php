#!/usr/bin/php
<?php
// ========================================
// CRON SYNC - Sincronizza STORICO.txt con DB
// Eseguito automaticamente ogni minuto
// 1. Cancella dal DB i record rimossi dal txt
// 2. Aggiorna il txt con tutti i record del DB
// ========================================

// Percorso assoluto
$baseDir = '/home/pi/proloco';

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

// PASSO 1: Sincronizza cancellazioni (txt -> db)
$result = sincronizzaStoricoConDB();

if ($result['success']) {
    if ($result['vendite_cancellate'] > 0 || $result['spese_cancellate'] > 0) {
        logMsg("SYNC: Rimossi {$result['vendite_cancellate']} vendite, {$result['spese_cancellate']} spese");
    }
} else {
    logMsg("ERRORE SYNC: " . $result['error']);
}

// PASSO 2: Aggiorna il file txt con i dati del DB (db -> txt)
$updated = aggiornaStoricoTxt();
if (!$updated) {
    logMsg("ERRORE: Impossibile aggiornare STORICO.txt");
}
