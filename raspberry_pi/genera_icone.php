<?php
// Genera icone PNG per PWA
// Esegui: php genera_icone.php

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$iconDir = __DIR__ . '/icons';

if (!is_dir($iconDir)) {
    mkdir($iconDir, 0755, true);
}

foreach ($sizes as $size) {
    // Crea immagine con sfondo verde biliardo
    $img = imagecreatetruecolor($size, $size);
    
    // Colori
    $verde = imagecolorallocate($img, 26, 77, 46); // #1a4d2e
    $marrone = imagecolorallocate($img, 139, 69, 19); // #8B4513
    $bianco = imagecolorallocate($img, 254, 243, 199); // #fef3c7
    
    // Sfondo verde
    imagefill($img, 0, 0, $verde);
    
    // Bordo marrone
    $border = max(2, intval($size / 30));
    imagefilledrectangle($img, 0, 0, $size-1, $border, $marrone);
    imagefilledrectangle($img, 0, $size-$border-1, $size-1, $size-1, $marrone);
    imagefilledrectangle($img, 0, 0, $border, $size-1, $marrone);
    imagefilledrectangle($img, $size-$border-1, 0, $size-1, $size-1, $marrone);
    
    // Testo "PSB" al centro
    $fontSize = intval($size / 3);
    $text = "PSB";
    
    // Calcola posizione centrata (approssimativa)
    $textWidth = imagefontwidth(5) * strlen($text) * ($fontSize / 11);
    $textHeight = imagefontheight(5) * ($fontSize / 11);
    $x = ($size - $textWidth) / 2;
    $y = ($size - $textHeight) / 2;
    
    // Usa font built-in (semplice ma funziona ovunque)
    $scale = $size / 100;
    for ($i = 0; $i < strlen($text); $i++) {
        $charX = intval($size/4 + $i * $size/4);
        $charY = intval($size/3);
        imagestring($img, 5, $charX, $charY, $text[$i], $bianco);
    }
    
    // Disegna un cerchio/bicchiere stilizzato
    $cx = intval($size / 2);
    $cy = intval($size / 2);
    $r = intval($size / 3);
    imageellipse($img, $cx, $cy, $r, $r, $bianco);
    
    // Salva PNG
    $filename = $iconDir . "/icon-{$size}.png";
    imagepng($img, $filename);
    imagedestroy($img);
    
    echo "Creata: icon-{$size}.png\n";
}

echo "\n✅ Icone create in /icons/\n";
