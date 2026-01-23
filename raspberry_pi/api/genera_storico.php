<?php
// ========================================
// GENERA/AGGIORNA FILE STORICO.TXT
// Apri: http://192.168.4.1/api/genera_storico.php
// ========================================
require_once '../includes/config.php';
require_once '../includes/storico_txt.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Genera Storico</title>
    <style>
        body { font-family: Georgia, serif; background: #1a4d2e; color: #fef3c7; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: rgba(0,0,0,0.3); padding: 20px; border-radius: 16px; border: 2px solid #92400e; }
        h1 { text-align: center; color: #fcd34d; }
        .success { background: #166534; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .error { background: #991b1b; padding: 15px; border-radius: 8px; margin: 10px 0; }
        pre { background: #000; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 12px; }
        a { color: #fcd34d; }
        .btn { display: inline-block; background: #92400e; color: #fef3c7; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin-top: 15px; margin-right: 10px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📄 Genera STORICO.txt</h1>";

$result = aggiornaStoricoTxt();

if ($result) {
    $filePath = dirname(__DIR__) . '/STORICO.txt';
    $content = file_get_contents($filePath);
    
    echo "<div class='success'>
        <h3>✅ File STORICO.txt generato/aggiornato!</h3>
        <p><strong>Percorso:</strong> /home/edo/proloco/STORICO.txt</p>
    </div>";
    
    echo "<h3>Anteprima contenuto:</h3>";
    echo "<pre>" . htmlspecialchars($content) . "</pre>";
} else {
    echo "<div class='error'>
        <h3>❌ Errore nella generazione del file</h3>
    </div>";
}

echo "<a href='/' class='btn'>← Torna all'app</a>
<a href='/api/sync_storico.php' class='btn'>🔄 Sincronizza DB</a>
</div>
</body>
</html>";
