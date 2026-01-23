#!/bin/bash
# ========================================
# SCRIPT AGGIORNAMENTO PROLOCO BAR MANAGER
# ========================================
# Uso: ./aggiorna.sh
# Aggiorna l'app da GitHub SENZA toccare il database
# Fa backup automatico prima di ogni aggiornamento

# Colori
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

REPO_DIR="/home/pi/proloco"
WEB_DIR="/home/pi/proloco/raspberry_pi"
BACKUP_DIR="/home/pi/proloco/backup"
DB_NAME="proloco_bar"
DB_USER="edo"
DB_PASS="5054"

echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║           AGGIORNAMENTO PROLOCO BAR MANAGER                  ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

cd ${REPO_DIR}

# ========================================
# FASE 1: BACKUP AUTOMATICO DATABASE
# ========================================
echo -e "${YELLOW}📦 Fase 1: Backup automatico database...${NC}"

mkdir -p ${BACKUP_DIR}
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/backup_pre_aggiornamento_${TIMESTAMP}.sql"

# Conta record attuali
VENDITE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM vendite" 2>/dev/null)
SPESE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM spese" 2>/dev/null)

echo "   Vendite nel DB: ${VENDITE:-0}"
echo "   Spese nel DB:   ${SPESE:-0}"

# Esegui backup
mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} > ${BACKUP_FILE} 2>/dev/null

if [ $? -eq 0 ]; then
    gzip ${BACKUP_FILE}
    echo -e "${GREEN}   ✅ Backup salvato: ${BACKUP_FILE}.gz${NC}"
else
    echo -e "${YELLOW}   ⚠️ Backup non riuscito (database vuoto?)${NC}"
fi

# Pulisci backup vecchi (mantieni ultimi 20)
cd ${BACKUP_DIR}
ls -t *.gz 2>/dev/null | tail -n +21 | xargs -r rm
cd ${REPO_DIR}

# ========================================
# FASE 2: AGGIORNAMENTO DA GITHUB
# ========================================
echo ""
echo -e "${YELLOW}🔄 Fase 2: Download aggiornamenti da GitHub...${NC}"

# Corregge i permessi prima del pull
sudo chown -R edo:edo .git 2>/dev/null
sudo chown -R edo:edo . 2>/dev/null

# Scarica aggiornamenti
git fetch --all

# Resetta SOLO i file di codice, NON il database
git reset --hard origin/main

echo -e "${GREEN}   ✅ File aggiornati da GitHub${NC}"

# ========================================
# FASE 3: RIPRISTINO PERMESSI
# ========================================
echo ""
echo -e "${YELLOW}🔧 Fase 3: Ripristino permessi...${NC}"

# Permessi per Apache
sudo chown -R www-data:www-data ${WEB_DIR}
sudo chmod -R 775 ${WEB_DIR}

# Permessi speciali per file di testo
sudo chmod 666 ${WEB_DIR}/STORICO.txt 2>/dev/null
sudo chmod 666 ${WEB_DIR}/LISTINO.txt 2>/dev/null

# Script eseguibili
sudo chmod +x ${WEB_DIR}/*.sh 2>/dev/null
sudo chmod +x ${WEB_DIR}/*.php 2>/dev/null
sudo chmod +x ${REPO_DIR}/*.sh 2>/dev/null

# Permessi git per prossimo aggiornamento
sudo chown -R edo:edo .git

echo -e "${GREEN}   ✅ Permessi sistemati${NC}"

# ========================================
# FASE 4: VERIFICA DATABASE (NON MODIFICARE!)
# ========================================
echo ""
echo -e "${YELLOW}🔍 Fase 4: Verifica database...${NC}"

# Verifica che i record siano ancora presenti
VENDITE_DOPO=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM vendite" 2>/dev/null)
SPESE_DOPO=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM spese" 2>/dev/null)

echo "   Vendite nel DB: ${VENDITE_DOPO:-0}"
echo "   Spese nel DB:   ${SPESE_DOPO:-0}"

if [ "${VENDITE_DOPO:-0}" -lt "${VENDITE:-0}" ] 2>/dev/null; then
    echo -e "${RED}   ⚠️ ATTENZIONE: Alcuni record potrebbero essere stati persi!${NC}"
    echo -e "${YELLOW}   💡 Puoi ripristinare il backup con:${NC}"
    echo "      gunzip -k ${BACKUP_FILE}.gz"
    echo "      mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} < ${BACKUP_FILE}"
else
    echo -e "${GREEN}   ✅ Database intatto!${NC}"
fi

# ========================================
# COMPLETATO
# ========================================
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗"
echo "║              AGGIORNAMENTO COMPLETATO!                       ║"
echo "╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}💡 Note:${NC}"
echo "   - Il database NON è stato modificato"
echo "   - Backup salvato in: ${BACKUP_DIR}/"
echo "   - Se qualcosa non funziona, riavvia: sudo reboot"
echo ""
