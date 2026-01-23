#!/bin/bash
# ========================================
# BACKUP DATABASE - PROLOCO BAR
# ========================================
# Esegue un backup completo del database MySQL
# Uso: ./backup_database.sh
# ========================================

# Colori
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configurazione database
DB_NAME="proloco_bar"
DB_USER="edo"
DB_PASS="5054"

# Directory backup
BACKUP_DIR="/home/pi/proloco/backup"
mkdir -p "$BACKUP_DIR"

# Nome file con timestamp
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/backup_${TIMESTAMP}.sql"

echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║              BACKUP DATABASE - PROLOCO BAR                   ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "${YELLOW}📦 Esecuzione backup...${NC}"

# Esegui backup
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null

if [ $? -eq 0 ]; then
    # Comprimi il backup
    gzip "$BACKUP_FILE"
    BACKUP_FILE="${BACKUP_FILE}.gz"
    
    # Calcola dimensione
    SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    
    echo -e "${GREEN}"
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                    BACKUP COMPLETATO!                        ║"
    echo "╠══════════════════════════════════════════════════════════════╣"
    echo "║  File: $BACKUP_FILE"
    echo "║  Dimensione: $SIZE"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    
    # Mostra ultimi 5 backup
    echo -e "${YELLOW}📋 Ultimi backup disponibili:${NC}"
    ls -lt "$BACKUP_DIR"/*.gz 2>/dev/null | head -5 | while read line; do
        echo "   $line"
    done
    
    # Pulisci backup vecchi (mantieni ultimi 10)
    cd "$BACKUP_DIR"
    ls -t *.gz 2>/dev/null | tail -n +11 | xargs -r rm
    echo -e "\n${BLUE}ℹ️  Backup più vecchi di 10 sono stati eliminati automaticamente${NC}"
else
    echo -e "${RED}"
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                    ERRORE BACKUP!                            ║"
    echo "║  Verifica le credenziali del database                        ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    exit 1
fi
