#!/bin/bash
# ========================================
# ATTIVA SINCRONIZZAZIONE AUTOMATICA
# Esegui con: sudo bash attiva_sync_auto.sh
# ========================================

echo "🔄 Attivazione sincronizzazione automatica STORICO.txt..."

WEB_DIR="/home/edo/proloco"

# Crea cartella logs
mkdir -p ${WEB_DIR}/logs
chown www-data:www-data ${WEB_DIR}/logs

# Rendi eseguibile lo script
chmod +x ${WEB_DIR}/cron_sync.php

# Configura CRON
echo "# Sincronizzazione STORICO.txt ogni minuto" > /etc/cron.d/proloco_sync
echo "* * * * * www-data /usr/bin/php ${WEB_DIR}/cron_sync.php > /dev/null 2>&1" >> /etc/cron.d/proloco_sync
chmod 644 /etc/cron.d/proloco_sync

# Riavvia cron
systemctl restart cron

echo ""
echo "✅ Sincronizzazione automatica attivata!"
echo ""
echo "Ogni minuto il sistema controllerà il file STORICO.txt"
echo "e cancellerà dal database i record rimossi dal file."
echo ""
echo "Per vedere i log: cat ${WEB_DIR}/logs/sync.log"
echo "Per disattivare: sudo rm /etc/cron.d/proloco_sync"
