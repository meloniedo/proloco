#!/usr/bin/php
<?php
/**
 * ========================================
 * CRON SYNC LISTINO
 * Sincronizza LISTINO.txt con il database
 * Eseguito ogni minuto via cron
 * ========================================
 */

$baseDir = '/home/pi/proloco/raspberry_pi';
$logFile = $baseDir . '/logs/listino_sync.log';

// Carica le funzioni
require_once $baseDir . '/includes/listino_txt.php';

// Log
function logMsg($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
}

try {
    $result = sincronizzaListino();
    
    if ($result['action'] === 'synced') {
        if ($result['aggiunti'] > 0 || $result['rimossi'] > 0 || $result['aggiornati'] > 0) {
            logMsg("Sync: +{$result['aggiunti']} -{$result['rimossi']} ~{$result['aggiornati']}");
        }
    } elseif ($result['action'] === 'created') {
        logMsg("File LISTINO.txt creato");
    } elseif ($result['action'] === 'error') {
        logMsg("ERRORE: " . $result['message']);
    }
    
} catch (Exception $e) {
    logMsg("ERRORE CRITICO: " . $e->getMessage());
}
