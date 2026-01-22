<?php
// Genera icone PNG per PWA
// Esegui una volta: php generate_icons.php

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$iconDir = __DIR__ . '/icons';

if (!is_dir($iconDir)) {
    mkdir($iconDir, 0755, true);
}

foreach ($sizes as $size) {
    // Crea immagine
    $img = imagecreatetruecolor($size, $size);
    
    // Colori
    $wood = imagecolorallocate($img, 139, 69, 19); // Marrone legno
    $cream = imagecolorallocate($img, 254, 243, 199); // Crema
    $darkBrown = imagecolorallocate($img, 74, 44, 16); // Marrone scuro
    
    // Sfondo legno
    imagefilledrectangle($img, 0, 0, $size, $size, $wood);
    
    // Bordo
    $border = max(2, $size / 32);
    imagefilledrectangle($img, 0, 0, $size, $border, $darkBrown);
    imagefilledrectangle($img, 0, $size - $border, $size, $size, $darkBrown);
    imagefilledrectangle($img, 0, 0, $border, $size, $darkBrown);
    imagefilledrectangle($img, $size - $border, 0, $size, $size, $darkBrown);
    
    // Cerchio centrale (tazza caffè stilizzata)
    $cx = $size / 2;
    $cy = $size / 2;
    $radius = $size / 3;
    
    imagefilledellipse($img, $cx, $cy, $radius * 2, $radius * 2, $cream);
    imagefilledellipse($img, $cx, $cy, $radius * 1.5, $radius * 1.5, $darkBrown);
    
    // Salva
    $filename = $iconDir . "/icon-{$size}.png";
    imagepng($img, $filename);
    imagedestroy($img);
    
    echo "Creata: $filename\n";
}

echo "\nIcone create con successo!\n";
