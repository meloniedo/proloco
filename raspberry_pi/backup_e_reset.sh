#!/bin/bash
# ========================================
# BACKUP E RESET DATABASE - PROLOCO BAR
# ========================================
# Esegue PRIMA un backup completo, POI resetta il database
# Uso: ./backup_e_reset.sh
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

echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║          BACKUP E RESET DATABASE - PROLOCO BAR               ║"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║  Questo script esegue:                                       ║"
echo "║  1. Backup completo del database                             ║"
echo "║  2. Reset di vendite e spese                                 ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Mostra conteggio attuale
echo -e "${YELLOW}📊 Stato attuale del database:${NC}"
VENDITE=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM vendite" 2>/dev/null)
SPESE=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM spese" 2>/dev/null)
echo "   Vendite: $VENDITE record"
echo "   Spese:   $SPESE record"
echo ""

# Chiedi conferma
read -p "Vuoi procedere con BACKUP + RESET? (scrivi 'SI' per confermare): " CONFERMA

if [ "$CONFERMA" != "SI" ]; then
    echo -e "${BLUE}❌ Operazione annullata.${NC}"
    exit 0
fi

# ============ FASE 1: BACKUP ============
echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}📦 FASE 1: Esecuzione backup...${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/backup_pre_reset_${TIMESTAMP}.sql"

mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ ERRORE: Backup fallito! Reset annullato.${NC}"
    exit 1
fi

gzip "$BACKUP_FILE"
BACKUP_FILE="${BACKUP_FILE}.gz"
SIZE=$(du -h "$BACKUP_FILE" | cut -f1)

echo -e "${GREEN}✅ Backup completato: $BACKUP_FILE ($SIZE)${NC}"

# ============ FASE 2: RESET ============
echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}🗑️  FASE 2: Reset database...${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
    DELETE FROM vendite;
    DELETE FROM spese;
    ALTER TABLE vendite AUTO_INCREMENT = 1;
    ALTER TABLE spese AUTO_INCREMENT = 1;
" 2>/dev/null

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ ERRORE: Reset fallito!${NC}"
    echo -e "${YELLOW}💡 Il backup è stato comunque salvato in: $BACKUP_FILE${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Database resettato${NC}"

# Aggiorna STORICO.txt
echo -e "${BLUE}🔄 Aggiornamento STORICO.txt...${NC}"
cd /home/pi/proloco/raspberry_pi
php -r "require_once 'includes/config.php'; require_once 'includes/storico_txt.php'; aggiornaStoricoTxt();" 2>/dev/null

# ============ RIEPILOGO FINALE ============
echo -e "${GREEN}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║              BACKUP E RESET COMPLETATI!                      ║"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║  ✅ Backup salvato in:                                       ║"
echo "║     $BACKUP_FILE"
echo "║                                                              ║"
echo "║  ✅ Database resettato:                                      ║"
echo "║     - Vendite: 0 record                                      ║"
echo "║     - Spese: 0 record                                        ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "${YELLOW}💡 Ora puoi importare nuovi dati con:${NC}"
echo "   php import_xlsx.php /percorso/al/file.xlsx"
echo ""
echo -e "${BLUE}💡 Per ripristinare il backup:${NC}"
echo "   gunzip -k $BACKUP_FILE"
echo "   mysql -u $DB_USER -p$DB_PASS $DB_NAME < ${BACKUP_FILE%.gz}"
