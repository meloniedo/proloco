#!/bin/bash
# ========================================
# SCRIPT AVVIO PROLOCO
# Eseguito automaticamente all'accensione
# ========================================

# Sistema i permessi per git
chown -R edo:edo /home/pi/proloco/.git 2>/dev/null
chown -R edo:edo /home/pi/proloco 2>/dev/null

# Sistema i permessi per Apache
chown -R www-data:www-data /home/pi/proloco/raspberry_pi 2>/dev/null
chmod -R 755 /home/pi/proloco/raspberry_pi 2>/dev/null

# Crea cartelle se non esistono
mkdir -p /home/pi/proloco/BACKUP_GIORNALIERI 2>/dev/null
mkdir -p /home/pi/proloco/RESOCONTI_SETTIMANALI 2>/dev/null
chown -R edo:edo /home/pi/proloco/BACKUP_GIORNALIERI 2>/dev/null
chown -R edo:edo /home/pi/proloco/RESOCONTI_SETTIMANALI 2>/dev/null

exit 0
