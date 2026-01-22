#!/bin/bash
# ========================================
# GENERA ICONE PWA
# Crea icone PNG semplici per la PWA
# ========================================

ICON_DIR="/var/www/html/proloco/icons"
mkdir -p $ICON_DIR

# Crea icone usando ImageMagick (se disponibile) o copia SVG
if command -v convert &> /dev/null; then
    echo "Generazione icone con ImageMagick..."
    for SIZE in 72 96 128 144 152 192 384 512; do
        convert -size ${SIZE}x${SIZE} xc:"#1a4d2e" \
            -fill "#fef3c7" -gravity center \
            -pointsize $((SIZE/3)) -annotate 0 "PSB" \
            -bordercolor "#8B4513" -border $((SIZE/30)) \
            "$ICON_DIR/icon-${SIZE}.png" 2>/dev/null
        echo "  ✓ icon-${SIZE}.png"
    done
else
    echo "ImageMagick non trovato, creo icone placeholder..."
    # Crea un semplice file PNG 1x1 e lo usa come placeholder
    # Il browser userà l'SVG come fallback
    for SIZE in 72 96 128 144 152 192 384 512; do
        # PNG minimo verde
        printf '\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xde\x00\x00\x00\x0cIDATx\x9cc\x1aM.\x00\x00\x00\x1e\x00\x01\xc4\xef\xf8\xc1\x00\x00\x00\x00IEND\xaeB`\x82' > "$ICON_DIR/icon-${SIZE}.png"
        echo "  ✓ icon-${SIZE}.png (placeholder)"
    done
fi

# Copia anche come apple-touch-icon
cp "$ICON_DIR/icon-192.png" "$ICON_DIR/apple-touch-icon.png" 2>/dev/null

echo ""
echo "✅ Icone create!"
