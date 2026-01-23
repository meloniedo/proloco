#!/bin/bash
# ========================================
# SCRIPT AVVIO PROLOCO
# Eseguito automaticamente all'accensione
# ========================================

WEB_DIR="/home/pi/proloco/raspberry_pi"
REPO_DIR="/home/pi/proloco"

# Sistema i permessi per git
chown -R edo:edo ${REPO_DIR}/.git 2>/dev/null
chown -R edo:edo ${REPO_DIR} 2>/dev/null

# Sistema i permessi per Apache
chown -R www-data:www-data ${WEB_DIR} 2>/dev/null
chmod -R 755 ${WEB_DIR} 2>/dev/null

# Permessi speciali per file di testo (scrittura da terminale E da web)
touch ${WEB_DIR}/STORICO.txt 2>/dev/null
touch ${WEB_DIR}/LISTINO.txt 2>/dev/null
chmod 666 ${WEB_DIR}/STORICO.txt 2>/dev/null
chmod 666 ${WEB_DIR}/LISTINO.txt 2>/dev/null
chown www-data:www-data ${WEB_DIR}/STORICO.txt 2>/dev/null
chown www-data:www-data ${WEB_DIR}/LISTINO.txt 2>/dev/null

# Permessi per script bash (eseguibili da tutti)
chmod +x ${WEB_DIR}/*.sh 2>/dev/null
chmod +x ${WEB_DIR}/*.php 2>/dev/null
chmod +x ${REPO_DIR}/*.sh 2>/dev/null

# Crea cartelle se non esistono
mkdir -p ${REPO_DIR}/BACKUP_GIORNALIERI 2>/dev/null
mkdir -p ${REPO_DIR}/RESOCONTI_SETTIMANALI 2>/dev/null
mkdir -p ${REPO_DIR}/backup 2>/dev/null
chown -R edo:edo ${REPO_DIR}/BACKUP_GIORNALIERI 2>/dev/null
chown -R edo:edo ${REPO_DIR}/RESOCONTI_SETTIMANALI 2>/dev/null
chown -R edo:edo ${REPO_DIR}/backup 2>/dev/null
chmod 777 ${REPO_DIR}/backup 2>/dev/null

exit 0
