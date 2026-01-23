#!/bin/bash
# ========================================
# SCRIPT AVVIO PROLOCO
# Eseguito automaticamente all'accensione
# Gestisce TUTTI i permessi per evitare problemi
# ========================================

WEB_DIR="/home/pi/proloco/raspberry_pi"
REPO_DIR="/home/pi/proloco"
USER_NAME="edo"

echo "$(date): Avvio script permessi Proloco..." >> /var/log/proloco.log

# ========================================
# 1. PERMESSI CARTELLA PRINCIPALE
# ========================================
# L'utente edo è proprietario della cartella principale (per git)
chown -R ${USER_NAME}:${USER_NAME} ${REPO_DIR} 2>/dev/null

# ========================================
# 2. PERMESSI CARTELLA WEB (Apache)
# ========================================
# www-data deve poter leggere e scrivere nella cartella web
chown -R www-data:www-data ${WEB_DIR} 2>/dev/null
chmod -R 775 ${WEB_DIR} 2>/dev/null

# Aggiungi edo al gruppo www-data per poter modificare i file
usermod -a -G www-data ${USER_NAME} 2>/dev/null

# ========================================
# 3. PERMESSI FILE DI TESTO (666 = tutti possono leggere/scrivere)
# ========================================
# Questi file devono essere modificabili sia da terminale che da web
touch ${WEB_DIR}/STORICO.txt 2>/dev/null
touch ${WEB_DIR}/LISTINO.txt 2>/dev/null
chown www-data:www-data ${WEB_DIR}/STORICO.txt ${WEB_DIR}/LISTINO.txt 2>/dev/null
chmod 666 ${WEB_DIR}/STORICO.txt ${WEB_DIR}/LISTINO.txt 2>/dev/null

# ========================================
# 4. PERMESSI SCRIPT ESEGUIBILI (755 = tutti possono eseguire)
# ========================================
# Script PHP
chmod 755 ${WEB_DIR}/*.php 2>/dev/null
chmod 755 ${WEB_DIR}/api/*.php 2>/dev/null

# Script Bash
chmod 755 ${WEB_DIR}/*.sh 2>/dev/null
chmod 755 ${REPO_DIR}/*.sh 2>/dev/null

# ========================================
# 5. PERMESSI CARTELLE DATI
# ========================================
# Cartelle per backup e resoconti (accessibili a tutti)
mkdir -p ${REPO_DIR}/BACKUP_GIORNALIERI 2>/dev/null
mkdir -p ${REPO_DIR}/RESOCONTI_SETTIMANALI 2>/dev/null
mkdir -p ${REPO_DIR}/backup 2>/dev/null
mkdir -p ${WEB_DIR}/logs 2>/dev/null
mkdir -p ${WEB_DIR}/backups 2>/dev/null

chmod 777 ${REPO_DIR}/BACKUP_GIORNALIERI 2>/dev/null
chmod 777 ${REPO_DIR}/RESOCONTI_SETTIMANALI 2>/dev/null
chmod 777 ${REPO_DIR}/backup 2>/dev/null
chmod 777 ${WEB_DIR}/logs 2>/dev/null
chmod 777 ${WEB_DIR}/backups 2>/dev/null

chown ${USER_NAME}:${USER_NAME} ${REPO_DIR}/BACKUP_GIORNALIERI 2>/dev/null
chown ${USER_NAME}:${USER_NAME} ${REPO_DIR}/RESOCONTI_SETTIMANALI 2>/dev/null
chown ${USER_NAME}:${USER_NAME} ${REPO_DIR}/backup 2>/dev/null
chown www-data:www-data ${WEB_DIR}/logs 2>/dev/null
chown www-data:www-data ${WEB_DIR}/backups 2>/dev/null

# ========================================
# 6. PERMESSI GIT (per aggiornamenti)
# ========================================
chown -R ${USER_NAME}:${USER_NAME} ${REPO_DIR}/.git 2>/dev/null
chmod -R 755 ${REPO_DIR}/.git 2>/dev/null

# ========================================
# 7. PERMESSI CARTELLA INCLUDES
# ========================================
chown -R www-data:www-data ${WEB_DIR}/includes 2>/dev/null
chmod -R 755 ${WEB_DIR}/includes 2>/dev/null

echo "$(date): Permessi Proloco sistemati OK" >> /var/log/proloco.log

exit 0
