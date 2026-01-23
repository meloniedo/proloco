#!/bin/bash
# ========================================
# RESET DATABASE - PROLOCO BAR
# ========================================
# Cancella TUTTI i record dal database (vendite e spese)
# Uso: ./reset_database.sh
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

echo -e "${RED}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║              ⚠️  RESET DATABASE - PROLOCO BAR                 ║"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║  ATTENZIONE! Questa operazione cancellerà:                   ║"
echo "║  - Tutte le VENDITE                                          ║"
echo "║  - Tutte le SPESE                                            ║"
echo "║                                                              ║"
echo "║  I PRODOTTI nel listino NON verranno toccati.                ║"
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
read -p "Sei SICURO di voler cancellare tutti i dati? (scrivi 'SI' per confermare): " CONFERMA

if [ "$CONFERMA" != "SI" ]; then
    echo -e "${BLUE}❌ Operazione annullata.${NC}"
    exit 0
fi

echo ""
echo -e "${YELLOW}🗑️  Cancellazione in corso...${NC}"

# Esegui reset
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
    DELETE FROM vendite;
    DELETE FROM spese;
    ALTER TABLE vendite AUTO_INCREMENT = 1;
    ALTER TABLE spese AUTO_INCREMENT = 1;
" 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}"
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                    RESET COMPLETATO!                         ║"
    echo "╠══════════════════════════════════════════════════════════════╣"
    echo "║  ✅ Tutte le vendite sono state cancellate                   ║"
    echo "║  ✅ Tutte le spese sono state cancellate                     ║"
    echo "║  ✅ Contatori ID resettati a 1                               ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    
    # Aggiorna STORICO.txt
    echo -e "${BLUE}🔄 Aggiornamento STORICO.txt...${NC}"
    cd /home/pi/proloco/raspberry_pi
    php -r "require_once 'includes/config.php'; require_once 'includes/storico_txt.php'; aggiornaStoricoTxt();" 2>/dev/null
    echo -e "${GREEN}✅ STORICO.txt aggiornato${NC}"
    
    echo ""
    echo -e "${YELLOW}💡 Ora puoi importare nuovi dati con:${NC}"
    echo "   php import_xlsx.php /percorso/al/file.xlsx"
else
    echo -e "${RED}"
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                    ERRORE RESET!                             ║"
    echo "║  Verifica le credenziali del database                        ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    exit 1
fi
