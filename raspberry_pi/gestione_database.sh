#!/bin/bash
# ══════════════════════════════════════════════════════════════════════════════
#                    GESTIONE DATABASE - PROLOCO BAR MANAGER
# ══════════════════════════════════════════════════════════════════════════════
# Uso: ./gestione_database.sh
# Menu interattivo per backup, ripristino e gestione del database
# ══════════════════════════════════════════════════════════════════════════════

# Colori
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m'

# Configurazione
DB_NAME="proloco_bar"
DB_USER="edo"
DB_PASS="5054"
BACKUP_DIR="/home/pi/proloco/backup"
WEB_DIR="/home/pi/proloco/raspberry_pi"

# Crea cartella backup se non esiste
mkdir -p ${BACKUP_DIR}

# ══════════════════════════════════════════════════════════════════════════════
# FUNZIONI
# ══════════════════════════════════════════════════════════════════════════════

clear_screen() {
    clear
}

press_enter() {
    echo ""
    echo -e "${CYAN}Premi INVIO per continuare...${NC}"
    read
}

show_header() {
    echo -e "${BLUE}"
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║          🗄️  GESTIONE DATABASE - PROLOCO BAR                 ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

show_db_status() {
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}📊 STATO ATTUALE DATABASE${NC}"
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    VENDITE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM vendite" 2>/dev/null || echo "?")
    SPESE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM spese" 2>/dev/null || echo "?")
    PRODOTTI=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM prodotti" 2>/dev/null || echo "?")
    
    TOT_VENDITE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COALESCE(SUM(prezzo), 0) FROM vendite" 2>/dev/null || echo "?")
    TOT_SPESE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COALESCE(SUM(importo), 0) FROM spese" 2>/dev/null || echo "?")
    
    echo ""
    echo -e "   📦 Prodotti:    ${GREEN}${PRODOTTI}${NC}"
    echo -e "   🛒 Vendite:     ${GREEN}${VENDITE}${NC} record  (€${TOT_VENDITE})"
    echo -e "   💸 Spese:       ${GREEN}${SPESE}${NC} record  (€${TOT_SPESE})"
    echo ""
    
    # === FILE TXT IMPORTANTI ===
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}📄 FILE DI TESTO${NC}"
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    # STORICO.txt
    if [ -f "${WEB_DIR}/STORICO.txt" ]; then
        STORICO_SIZE=$(du -h "${WEB_DIR}/STORICO.txt" | cut -f1)
        STORICO_DATE=$(stat -c %y "${WEB_DIR}/STORICO.txt" 2>/dev/null | cut -d'.' -f1)
        STORICO_LINES=$(wc -l < "${WEB_DIR}/STORICO.txt" 2>/dev/null)
        echo -e "   📜 STORICO.txt"
        echo -e "      Dimensione: ${GREEN}${STORICO_SIZE}${NC} (${STORICO_LINES} righe)"
        echo -e "      Aggiornato: ${YELLOW}${STORICO_DATE}${NC}"
    else
        echo -e "   📜 STORICO.txt: ${RED}Non trovato${NC}"
    fi
    
    # LISTINO.txt
    if [ -f "${WEB_DIR}/LISTINO.txt" ]; then
        LISTINO_SIZE=$(du -h "${WEB_DIR}/LISTINO.txt" | cut -f1)
        LISTINO_DATE=$(stat -c %y "${WEB_DIR}/LISTINO.txt" 2>/dev/null | cut -d'.' -f1)
        LISTINO_LINES=$(wc -l < "${WEB_DIR}/LISTINO.txt" 2>/dev/null)
        echo -e "   📋 LISTINO.txt"
        echo -e "      Dimensione: ${GREEN}${LISTINO_SIZE}${NC} (${LISTINO_LINES} righe)"
        echo -e "      Aggiornato: ${YELLOW}${LISTINO_DATE}${NC}"
    else
        echo -e "   📋 LISTINO.txt: ${RED}Non trovato${NC}"
    fi
    echo ""
    
    # === BACKUP ===
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}💾 BACKUP DATABASE${NC}"
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    BACKUP_COUNT=$(ls -1 ${BACKUP_DIR}/*.gz 2>/dev/null | wc -l)
    echo -e "   Backup totali: ${YELLOW}${BACKUP_COUNT}${NC}"
    
    if [ "${BACKUP_COUNT}" -gt 0 ]; then
        # Mostra il backup più recente
        LATEST_BACKUP=$(ls -t ${BACKUP_DIR}/*.gz 2>/dev/null | head -1)
        LATEST_NAME=$(basename ${LATEST_BACKUP})
        LATEST_SIZE=$(du -h ${LATEST_BACKUP} | cut -f1)
        LATEST_DATE=$(stat -c %y ${LATEST_BACKUP} 2>/dev/null | cut -d'.' -f1)
        
        echo ""
        echo -e "   ${GREEN}★ PIÙ RECENTE:${NC}"
        echo -e "      📁 ${CYAN}${LATEST_NAME}${NC}"
        echo -e "      📏 Dimensione: ${LATEST_SIZE}"
        echo -e "      📅 Data: ${YELLOW}${LATEST_DATE}${NC}"
        
        # Mostra anche il più vecchio se ci sono più backup
        if [ "${BACKUP_COUNT}" -gt 1 ]; then
            OLDEST_BACKUP=$(ls -t ${BACKUP_DIR}/*.gz 2>/dev/null | tail -1)
            OLDEST_NAME=$(basename ${OLDEST_BACKUP})
            OLDEST_DATE=$(stat -c %y ${OLDEST_BACKUP} 2>/dev/null | cut -d'.' -f1)
            echo ""
            echo -e "   ${RED}○ Più vecchio:${NC}"
            echo -e "      📁 ${OLDEST_NAME}"
            echo -e "      📅 Data: ${OLDEST_DATE}"
        fi
    fi
    echo ""
}

# ══════════════════════════════════════════════════════════════════════════════
# 1. CREA BACKUP
# ══════════════════════════════════════════════════════════════════════════════
do_backup() {
    clear_screen
    show_header
    echo -e "${YELLOW}📦 CREAZIONE NUOVO BACKUP${NC}"
    echo ""
    
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    BACKUP_FILE="${BACKUP_DIR}/backup_${TIMESTAMP}.sql"
    
    echo -e "   Creazione backup in corso..."
    
    mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} > ${BACKUP_FILE} 2>/dev/null
    
    if [ $? -eq 0 ]; then
        # Comprimi
        gzip ${BACKUP_FILE}
        SIZE=$(du -h "${BACKUP_FILE}.gz" | cut -f1)
        
        echo ""
        echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗"
        echo "║                    ✅ BACKUP COMPLETATO!                      ║"
        echo "╚══════════════════════════════════════════════════════════════╝${NC}"
        echo ""
        echo -e "   📁 File: ${CYAN}backup_${TIMESTAMP}.sql.gz${NC}"
        echo -e "   📏 Dimensione: ${SIZE}"
        echo -e "   📂 Cartella: ${BACKUP_DIR}/"
    else
        echo -e "${RED}❌ Errore durante il backup!${NC}"
    fi
    
    press_enter
}

# ══════════════════════════════════════════════════════════════════════════════
# 2. LISTA BACKUP
# ══════════════════════════════════════════════════════════════════════════════
list_backups() {
    clear_screen
    show_header
    echo -e "${YELLOW}📋 LISTA BACKUP DISPONIBILI${NC}"
    echo ""
    
    if [ ! "$(ls -A ${BACKUP_DIR}/*.gz 2>/dev/null)" ]; then
        echo -e "${RED}   Nessun backup trovato.${NC}"
        press_enter
        return
    fi
    
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    printf "   ${CYAN}%-4s %-35s %-10s %-20s${NC}\n" "N°" "NOME FILE" "DIMENSIONE" "DATA CREAZIONE"
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    i=1
    for file in $(ls -t ${BACKUP_DIR}/*.gz 2>/dev/null); do
        filename=$(basename $file)
        size=$(du -h $file | cut -f1)
        date=$(stat -c %y $file | cut -d'.' -f1)
        printf "   %-4s %-35s %-10s %-20s\n" "$i." "$filename" "$size" "$date"
        i=$((i+1))
    done
    
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "   ${BLUE}Totale: $((i-1)) backup${NC}"
    
    press_enter
}

# ══════════════════════════════════════════════════════════════════════════════
# 3. RIPRISTINA BACKUP
# ══════════════════════════════════════════════════════════════════════════════
restore_backup() {
    clear_screen
    show_header
    echo -e "${YELLOW}🔄 RIPRISTINO BACKUP${NC}"
    echo ""
    
    if [ ! "$(ls -A ${BACKUP_DIR}/*.gz 2>/dev/null)" ]; then
        echo -e "${RED}   Nessun backup trovato.${NC}"
        press_enter
        return
    fi
    
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo "   Seleziona il backup da ripristinare:"
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    # Crea array di file
    files=($(ls -t ${BACKUP_DIR}/*.gz 2>/dev/null))
    
    i=1
    for file in "${files[@]}"; do
        filename=$(basename $file)
        size=$(du -h $file | cut -f1)
        echo "   $i) $filename ($size)"
        i=$((i+1))
    done
    
    echo ""
    echo "   0) ❌ Annulla"
    echo ""
    
    read -p "   Inserisci il numero del backup: " choice
    
    if [ "$choice" = "0" ] || [ -z "$choice" ]; then
        echo -e "${BLUE}   Operazione annullata.${NC}"
        press_enter
        return
    fi
    
    # Verifica scelta valida
    if [ "$choice" -lt 1 ] || [ "$choice" -gt "${#files[@]}" ] 2>/dev/null; then
        echo -e "${RED}   Scelta non valida!${NC}"
        press_enter
        return
    fi
    
    selected_file="${files[$((choice-1))]}"
    selected_name=$(basename $selected_file)
    
    echo ""
    echo -e "${RED}╔══════════════════════════════════════════════════════════════╗"
    echo "║                      ⚠️  ATTENZIONE!                          ║"
    echo "╠══════════════════════════════════════════════════════════════╣"
    echo "║  Il ripristino SOSTITUIRÀ tutti i dati attuali!              ║"
    echo "║  Verrà creato un backup di sicurezza prima del ripristino.   ║"
    echo "╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    read -p "   Scrivi 'SI' per confermare: " confirm
    
    if [ "$confirm" != "SI" ]; then
        echo -e "${BLUE}   Operazione annullata.${NC}"
        press_enter
        return
    fi
    
    echo ""
    echo -e "${YELLOW}   1/3 Creazione backup di sicurezza...${NC}"
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} 2>/dev/null | gzip > "${BACKUP_DIR}/backup_pre_ripristino_${TIMESTAMP}.sql.gz"
    
    echo -e "${YELLOW}   2/3 Decompressione backup selezionato...${NC}"
    gunzip -k -f ${selected_file}
    sql_file="${selected_file%.gz}"
    
    echo -e "${YELLOW}   3/3 Ripristino database...${NC}"
    mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} < ${sql_file} 2>/dev/null
    
    if [ $? -eq 0 ]; then
        rm -f ${sql_file}
        echo ""
        echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗"
        echo "║                  ✅ RIPRISTINO COMPLETATO!                    ║"
        echo "╚══════════════════════════════════════════════════════════════╝${NC}"
        echo ""
        show_db_status
    else
        echo -e "${RED}   ❌ Errore durante il ripristino!${NC}"
    fi
    
    press_enter
}

# ══════════════════════════════════════════════════════════════════════════════
# 4. ELIMINA BACKUP
# ══════════════════════════════════════════════════════════════════════════════
delete_backup() {
    clear_screen
    show_header
    echo -e "${YELLOW}🗑️  ELIMINA BACKUP${NC}"
    echo ""
    
    if [ ! "$(ls -A ${BACKUP_DIR}/*.gz 2>/dev/null)" ]; then
        echo -e "${RED}   Nessun backup trovato.${NC}"
        press_enter
        return
    fi
    
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo "   Seleziona il backup da eliminare:"
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    files=($(ls -t ${BACKUP_DIR}/*.gz 2>/dev/null))
    
    i=1
    for file in "${files[@]}"; do
        filename=$(basename $file)
        size=$(du -h $file | cut -f1)
        echo "   $i) $filename ($size)"
        i=$((i+1))
    done
    
    echo ""
    echo "   A) 🗑️  Elimina TUTTI i backup"
    echo "   0) ❌ Annulla"
    echo ""
    
    read -p "   Inserisci il numero (o 'A' per tutti): " choice
    
    if [ "$choice" = "0" ] || [ -z "$choice" ]; then
        echo -e "${BLUE}   Operazione annullata.${NC}"
        press_enter
        return
    fi
    
    if [ "$choice" = "A" ] || [ "$choice" = "a" ]; then
        echo ""
        read -p "   Sei sicuro di voler eliminare TUTTI i backup? (SI/NO): " confirm
        if [ "$confirm" = "SI" ]; then
            rm -f ${BACKUP_DIR}/*.gz
            echo -e "${GREEN}   ✅ Tutti i backup sono stati eliminati.${NC}"
        else
            echo -e "${BLUE}   Operazione annullata.${NC}"
        fi
        press_enter
        return
    fi
    
    if [ "$choice" -lt 1 ] || [ "$choice" -gt "${#files[@]}" ] 2>/dev/null; then
        echo -e "${RED}   Scelta non valida!${NC}"
        press_enter
        return
    fi
    
    selected_file="${files[$((choice-1))]}"
    selected_name=$(basename $selected_file)
    
    echo ""
    read -p "   Confermi l'eliminazione di '$selected_name'? (SI/NO): " confirm
    
    if [ "$confirm" = "SI" ]; then
        rm -f ${selected_file}
        echo -e "${GREEN}   ✅ Backup eliminato.${NC}"
    else
        echo -e "${BLUE}   Operazione annullata.${NC}"
    fi
    
    press_enter
}

# ══════════════════════════════════════════════════════════════════════════════
# 5. RESET DATABASE
# ══════════════════════════════════════════════════════════════════════════════
reset_database() {
    clear_screen
    show_header
    echo -e "${RED}⚠️  RESET DATABASE${NC}"
    echo ""
    
    show_db_status
    
    echo -e "${RED}╔══════════════════════════════════════════════════════════════╗"
    echo "║                      ⚠️  ATTENZIONE!                          ║"
    echo "╠══════════════════════════════════════════════════════════════╣"
    echo "║  Questa operazione CANCELLERÀ:                               ║"
    echo "║  - Tutte le VENDITE                                          ║"
    echo "║  - Tutte le SPESE                                            ║"
    echo "║                                                              ║"
    echo "║  I PRODOTTI NON verranno cancellati.                         ║"
    echo "║  Verrà creato un backup automatico prima del reset.          ║"
    echo "╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    read -p "   Scrivi 'RESET' per confermare: " confirm
    
    if [ "$confirm" != "RESET" ]; then
        echo -e "${BLUE}   Operazione annullata.${NC}"
        press_enter
        return
    fi
    
    echo ""
    echo -e "${YELLOW}   1/2 Creazione backup di sicurezza...${NC}"
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} 2>/dev/null | gzip > "${BACKUP_DIR}/backup_pre_reset_${TIMESTAMP}.sql.gz"
    echo -e "${GREEN}       ✅ Backup salvato${NC}"
    
    echo -e "${YELLOW}   2/2 Reset database...${NC}"
    mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -e "
        DELETE FROM vendite;
        DELETE FROM spese;
        ALTER TABLE vendite AUTO_INCREMENT = 1;
        ALTER TABLE spese AUTO_INCREMENT = 1;
    " 2>/dev/null
    
    if [ $? -eq 0 ]; then
        # Aggiorna STORICO.txt
        php ${WEB_DIR}/cron_sync.php 2>/dev/null
        
        echo ""
        echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗"
        echo "║                    ✅ RESET COMPLETATO!                       ║"
        echo "╚══════════════════════════════════════════════════════════════╝${NC}"
        echo ""
        echo -e "   💾 Backup salvato: backup_pre_reset_${TIMESTAMP}.sql.gz"
        echo ""
    else
        echo -e "${RED}   ❌ Errore durante il reset!${NC}"
    fi
    
    press_enter
}

# ══════════════════════════════════════════════════════════════════════════════
# 6. ESPORTA IN FORMATO LEGGIBILE
# ══════════════════════════════════════════════════════════════════════════════
export_readable() {
    clear_screen
    show_header
    echo -e "${YELLOW}📄 ESPORTA DATI IN FORMATO LEGGIBILE${NC}"
    echo ""
    
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    EXPORT_FILE="${BACKUP_DIR}/export_${TIMESTAMP}.txt"
    
    echo -e "${CYAN}Generazione export...${NC}"
    echo ""
    
    {
        echo "══════════════════════════════════════════════════════════════"
        echo "        EXPORT DATABASE PROLOCO BAR - $(date '+%d/%m/%Y %H:%M')"
        echo "══════════════════════════════════════════════════════════════"
        echo ""
        
        echo "────────────────────────────────────────────────────────────────"
        echo "                          RIEPILOGO"
        echo "────────────────────────────────────────────────────────────────"
        VENDITE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM vendite" 2>/dev/null)
        SPESE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM spese" 2>/dev/null)
        TOT_V=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COALESCE(SUM(prezzo), 0) FROM vendite" 2>/dev/null)
        TOT_S=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COALESCE(SUM(importo), 0) FROM spese" 2>/dev/null)
        echo "Totale Vendite: ${VENDITE} transazioni - €${TOT_V}"
        echo "Totale Spese: ${SPESE} transazioni - €${TOT_S}"
        echo ""
        
        echo "────────────────────────────────────────────────────────────────"
        echo "                          VENDITE"
        echo "────────────────────────────────────────────────────────────────"
        mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -e "
            SELECT DATE_FORMAT(timestamp, '%d/%m/%Y %H:%i') as Data, 
                   nome_prodotto as Prodotto, 
                   categoria as Categoria,
                   CONCAT('€', FORMAT(prezzo, 2)) as Importo 
            FROM vendite 
            ORDER BY timestamp DESC" 2>/dev/null
        echo ""
        
        echo "────────────────────────────────────────────────────────────────"
        echo "                           SPESE"
        echo "────────────────────────────────────────────────────────────────"
        mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -e "
            SELECT DATE_FORMAT(timestamp, '%d/%m/%Y %H:%i') as Data, 
                   categoria_spesa as Categoria, 
                   CONCAT('€', FORMAT(importo, 2)) as Importo,
                   COALESCE(note, '') as Note
            FROM spese 
            ORDER BY timestamp DESC" 2>/dev/null
        echo ""
        
        echo "────────────────────────────────────────────────────────────────"
        echo "                         PRODOTTI"
        echo "────────────────────────────────────────────────────────────────"
        mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -e "
            SELECT nome as Prodotto, 
                   CONCAT('€', FORMAT(prezzo, 2)) as Prezzo, 
                   categoria as Categoria,
                   icona as Icona
            FROM prodotti 
            ORDER BY categoria, nome" 2>/dev/null
        
    } > ${EXPORT_FILE}
    
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗"
    echo "║                   ✅ EXPORT COMPLETATO!                       ║"
    echo "╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "   📄 File: ${CYAN}${EXPORT_FILE}${NC}"
    echo ""
    echo -e "${YELLOW}   Puoi aprirlo con: cat ${EXPORT_FILE}${NC}"
    echo -e "${YELLOW}   Oppure copiarlo su USB e aprirlo su PC${NC}"
    
    press_enter
}

# ══════════════════════════════════════════════════════════════════════════════
# MENU PRINCIPALE
# ══════════════════════════════════════════════════════════════════════════════
main_menu() {
    while true; do
        clear_screen
        show_header
        show_db_status
        
        echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo -e "${CYAN}                        MENU PRINCIPALE${NC}"
        echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo ""
        echo -e "   ${GREEN}1)${NC} 📦 Crea nuovo backup"
        echo -e "   ${GREEN}2)${NC} 📋 Lista backup disponibili"
        echo -e "   ${GREEN}3)${NC} 🔄 Ripristina un backup"
        echo -e "   ${GREEN}4)${NC} 🗑️  Elimina backup"
        echo -e "   ${GREEN}5)${NC} ⚠️  Reset database (cancella vendite/spese)"
        echo -e "   ${GREEN}6)${NC} 📄 Esporta in formato leggibile (.txt)"
        echo ""
        echo -e "   ${RED}0)${NC} 🚪 Esci"
        echo ""
        echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo ""
        
        read -p "   Scegli un'opzione (0-6): " choice
        
        case $choice in
            1) do_backup ;;
            2) list_backups ;;
            3) restore_backup ;;
            4) delete_backup ;;
            5) reset_database ;;
            6) export_readable ;;
            0) 
                clear_screen
                echo -e "${GREEN}👋 Arrivederci!${NC}"
                echo ""
                exit 0
                ;;
            *)
                echo -e "${RED}   Opzione non valida!${NC}"
                sleep 1
                ;;
        esac
    done
}

# ══════════════════════════════════════════════════════════════════════════════
# AVVIO
# ══════════════════════════════════════════════════════════════════════════════
main_menu
