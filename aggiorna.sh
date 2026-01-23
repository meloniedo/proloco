#!/bin/bash
# ========================================
# SCRIPT AGGIORNAMENTO PROLOCO BAR MANAGER
# ========================================
# Uso: ./aggiorna.sh
# Questo script aggiorna l'app da GitHub sovrascrivendo tutto

REPO_DIR="/home/pi/proloco"
WEB_DIR="/home/pi/proloco/raspberry_pi"

cd ${REPO_DIR}

# Corregge i permessi prima del pull
sudo chown -R edo:edo .git
sudo chown -R edo:edo .

# FORZA l'aggiornamento scartando TUTTE le modifiche locali
git fetch --all
git reset --hard origin/main

# Ripristina permessi per Apache
sudo chown -R www-data:www-data ${WEB_DIR}
sudo chmod -R 755 ${WEB_DIR}

echo ""
echo "✅ Aggiornamento completato!"
echo ""
