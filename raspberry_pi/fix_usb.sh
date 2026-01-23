#!/bin/bash
# ========================================
# FIX PERMESSI USB per Backup
# Esegui con: sudo bash fix_usb.sh
# ========================================

echo "🔧 Configurazione permessi USB..."

# Aggiungi www-data ai gruppi necessari
usermod -a -G plugdev www-data 2>/dev/null
usermod -a -G disk www-data 2>/dev/null
echo "✓ Utente www-data aggiunto ai gruppi"

# Permessi per /media
chmod 777 /media 2>/dev/null
echo "✓ Directory /media configurata"

# Se c'è già una chiavetta montata, sistema i permessi
for dir in /media/*; do
    if [ -d "$dir" ]; then
        chmod 777 "$dir" 2>/dev/null
        echo "✓ Permessi aggiornati per: $dir"
    fi
done

# Riavvia Apache per applicare i nuovi gruppi
systemctl restart apache2
echo "✓ Apache riavviato"

echo ""
echo "✅ FATTO! Ora prova il backup USB dall'app."
echo ""
echo "Se la chiavetta non viene rilevata:"
echo "1. Rimuovi la chiavetta USB"
echo "2. Reinseriscila"
echo "3. Premi 'Controlla USB' nell'app"
