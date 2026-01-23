<?php
// ========================================
// API GESTIONE ORA SISTEMA
// ========================================
require_once '../includes/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Ritorna ora attuale e se serve sincronizzazione
    jsonHeaders();
    
    // Controlla se il sistema è appena riavviato (file flag)
    $flagFile = '/tmp/proloco_boot_flag';
    $needsSync = false;
    
    // Se l'ora è prima del 2024, probabilmente è sbagliata
    if (intval(date('Y')) < 2024) {
        $needsSync = true;
    }
    
    // Controlla flag di boot
    $bootTime = @file_get_contents('/proc/uptime');
    if ($bootTime) {
        $uptime = floatval(explode(' ', $bootTime)[0]);
        // Se uptime < 5 minuti e non esiste flag, mostra popup
        if ($uptime < 300 && !file_exists($flagFile)) {
            $needsSync = true;
        }
    }
    
    jsonResponse([
        'ora_attuale' => date('H:i'),
        'data_attuale' => date('Y-m-d'),
        'timestamp' => time(),
        'needs_sync' => $needsSync,
        'anno' => intval(date('Y'))
    ]);
    
} elseif ($method === 'POST') {
    // Imposta ora del sistema
    jsonHeaders();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['data']) || !isset($input['ora'])) {
        jsonResponse(['error' => 'Data e ora richieste'], 400);
        exit;
    }
    
    $data = $input['data']; // formato: YYYY-MM-DD
    $ora = $input['ora'];   // formato: HH:MM
    
    // Valida formato
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !preg_match('/^\d{2}:\d{2}$/', $ora)) {
        jsonResponse(['error' => 'Formato non valido'], 400);
        exit;
    }
    
    // Imposta ora del sistema (richiede sudo senza password per www-data)
    $datetime = "{$data} {$ora}:00";
    $cmd = "sudo date -s '$datetime' 2>&1";
    $output = shell_exec($cmd);
    
    // Sincronizza hardware clock se disponibile
    shell_exec("sudo hwclock -w 2>/dev/null");
    
    // Crea flag per non mostrare più il popup
    file_put_contents('/tmp/proloco_boot_flag', date('Y-m-d H:i:s'));
    
    jsonResponse([
        'success' => true,
        'ora_impostata' => date('Y-m-d H:i:s'),
        'message' => 'Ora aggiornata correttamente'
    ]);
    
} else {
    jsonHeaders();
    jsonResponse(['error' => 'Metodo non supportato'], 405);
}
