#!/bin/bash
# ========================================
# SCRIPT AGGIORNAMENTO PROLOCO BAR MANAGER
# ========================================
# Uso: ./aggiorna.sh
# Questo script aggiorna l'app da GitHub senza problemi di permessi

cd /home/pi/proloco

# Corregge i permessi prima del pull
sudo chown -R pi:pi .git
sudo chown -R pi:pi .

# Esegue il pull
git pull

echo ""
echo "✅ Aggiornamento completato!"
echo ""
