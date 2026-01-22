<?php
// ========================================
// API SINCRONIZZA STORICO.TXT CON DATABASE
// Cancella dal DB i record rimossi dal file txt
// ========================================
require_once '../includes/config.php';
require_once '../includes/storico_txt.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Sincronizzazione Storico</title>
    <style>
        body { font-family: Georgia, serif; background: #1a4d2e; color: #fef3c7; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: rgba(0,0,0,0.3); padding: 20px; border-radius: 16px; border: 2px solid #92400e; }
        h1 { text-align: center; color: #fcd34d; }
        .success { background: #166534; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .error { background: #991b1b; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .info { background: #1e40af; padding: 15px; border-radius: 8px; margin: 10px 0; }
        a { color: #fcd34d; }
        .btn { display: inline-block; background: #92400e; color: #fef3c7; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin-top: 15px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔄 Sincronizzazione Storico</h1>";

$result = sincronizzaStoricoConDB();

if ($result['success']) {
    echo "<div class='success'>
        <h3>✅ Sincronizzazione completata!</h3>
        <p><strong>Vendite cancellate:</strong> {$result['vendite_cancellate']}</p>
        <p><strong>Spese cancellate:</strong> {$result['spese_cancellate']}</p>
    </div>";
    
    if ($result['vendite_cancellate'] == 0 && $result['spese_cancellate'] == 0) {
        echo "<div class='info'>
            <p>ℹ️ Nessun record da cancellare. Il database è già sincronizzato con il file STORICO.txt</p>
        </div>";
    }
} else {
    echo "<div class='error'>
        <h3>❌ Errore</h3>
        <p>{$result['error']}</p>
    </div>";
}

echo "<a href='/' class='btn'>← Torna all'app</a>
</div>
</body>
</html>";
