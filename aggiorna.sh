#!/bin/bash
# ========================================
# SCRIPT AGGIORNAMENTO PROLOCO BAR MANAGER
# ========================================
# Uso: ./aggiorna.sh
# Questo script aggiorna l'app da GitHub sovrascrivendo tutto

cd /home/pi/proloco

# Corregge i permessi prima del pull
sudo chown -R edo:edo .git
sudo chown -R edo:edo .

# FORZA l'aggiornamento scartando TUTTE le modifiche locali
git fetch --all
git reset --hard origin/main
git clean -fd

# Ripristina permessi per Apache
sudo chown -R www-data:www-data /home/pi/proloco
sudo chmod -R 755 /home/pi/proloco

echo ""
echo "✅ Aggiornamento completato!"
echo ""
