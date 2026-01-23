#!/bin/bash
# ========================================
# SCRIPT AGGIORNAMENTO PROLOCO BAR MANAGER
# ========================================
# Uso: ./aggiorna.sh
# Questo script aggiorna l'app da GitHub senza problemi di permessi

cd /home/edo/proloco

# Corregge i permessi prima del pull
sudo chown -R edo:edo .git
sudo chown -R edo:edo .

# Esegue il pull
git pull

echo ""
echo "✅ Aggiornamento completato!"
echo ""
