<?php
// ========================================
// API IMPOSTAZIONI BACKUP
// ========================================
require_once '../includes/config.php';
jsonHeaders();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Ottieni impostazioni backup
        $pdo = getDB();
        
        // Giorni (default: 0 = domenica)
        $stmt = $pdo->query("SELECT valore FROM configurazione WHERE chiave = 'backup_giorni'");
        $row = $stmt->fetch();
        $giorni = $row ? $row['valore'] : '0';
        
        // Ora (default: 23:59)
        $stmt = $pdo->query("SELECT valore FROM configurazione WHERE chiave = 'backup_ora'");
        $row = $stmt->fetch();
        $ora = $row ? $row['valore'] : '23:59';
        
        // Backup necessario?
        $stmt = $pdo->query("SELECT valore FROM configurazione WHERE chiave = 'backup_necessario'");
        $row = $stmt->fetch();
        $backupNecessario = $row ? $row['valore'] : null;
        
        // Ultimo backup riuscito
        $stmt = $pdo->query("SELECT valore FROM configurazione WHERE chiave = 'ultimo_backup'");
        $row = $stmt->fetch();
        $ultimoBackup = $row ? $row['valore'] : null;
        
        // Lista backup disponibili per download
        $backupDir = dirname(__DIR__) . '/backups';
        $backupFiles = [];
        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/*.xls*');
            foreach ($files as $f) {
                $backupFiles[] = [
                    'name' => basename($f),
                    'size' => filesize($f),
                    'date' => date('d/m/Y H:i', filemtime($f))
                ];
            }
            // Ordina per data decrescente
            usort($backupFiles, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
        }
        
        jsonResponse([
            'giorni' => $giorni,
            'ora' => $ora,
            'backup_necessario' => $backupNecessario,
            'ultimo_backup' => $ultimoBackup,
            'backup_disponibili' => $backupFiles
        ]);
        break;
        
    case 'POST':
        // Salva impostazioni backup
        $input = json_decode(file_get_contents('php://input'), true);
        
        $pdo = getDB();
        
        if (isset($input['giorni'])) {
            $giorni = is_array($input['giorni']) ? implode(',', $input['giorni']) : $input['giorni'];
            $stmt = $pdo->prepare("INSERT INTO configurazione (chiave, valore) VALUES ('backup_giorni', ?) ON DUPLICATE KEY UPDATE valore = ?");
            $stmt->execute([$giorni, $giorni]);
        }
        
        if (isset($input['ora'])) {
            $stmt = $pdo->prepare("INSERT INTO configurazione (chiave, valore) VALUES ('backup_ora', ?) ON DUPLICATE KEY UPDATE valore = ?");
            $stmt->execute([$input['ora'], $input['ora']]);
        }
        
        // Reset flag backup necessario se richiesto
        if (isset($input['reset_avviso']) && $input['reset_avviso']) {
            $pdo->exec("DELETE FROM configurazione WHERE chiave = 'backup_necessario'");
        }
        
        jsonResponse(['success' => true]);
        break;
        
    default:
        jsonResponse(['error' => 'Metodo non supportato'], 405);
}
