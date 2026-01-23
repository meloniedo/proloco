#!/bin/bash
# ══════════════════════════════════════════════════════════════════════════════
#                    GESTIONE DATABASE - PROLOCO BAR MANAGER
# ══════════════════════════════════════════════════════════════════════════════
# Uso: ./gestione_database.sh
# Menu interattivo per backup, ripristino e gestione del database
# Gestisce sia backup SQL (.sql.gz) che backup Excel (.xlsx)
# ══════════════════════════════════════════════════════════════════════════════

# Colori
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
MAGENTA='\033[0;35m'
NC='\033[0m'

# Configurazione
DB_NAME="proloco_bar"
DB_USER="edo"
DB_PASS="5054"
WEB_DIR="/home/pi/proloco/raspberry_pi"
REPO_DIR="/home/pi/proloco"

# CARTELLE BACKUP (organizzate)
BACKUP_SQL_DIR="/home/pi/proloco/backup"              # Backup database SQL
BACKUP_XLSX_DIR="/home/pi/proloco/BACKUP_GIORNALIERI" # Backup Excel giornalieri
BACKUP_APP_DIR="${WEB_DIR}/backups"                   # Backup da app web
RESOCONTI_DIR="/home/pi/proloco/RESOCONTI_SETTIMANALI"

# Crea cartelle se non esistono
mkdir -p ${BACKUP_SQL_DIR} 2>/dev/null
mkdir -p ${BACKUP_XLSX_DIR} 2>/dev/null
mkdir -p ${BACKUP_APP_DIR} 2>/dev/null
mkdir -p ${RESOCONTI_DIR} 2>/dev/null

# ══════════════════════════════════════════════════════════════════════════════
# FUNZIONI UTILITÀ
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
    echo "║                    Menu Principale                           ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

show_submenu_header() {
    local title="$1"
    echo -e "${MAGENTA}"
    echo "┌──────────────────────────────────────────────────────────────┐"
    echo "│  $title"
    echo "└──────────────────────────────────────────────────────────────┘"
    echo -e "${NC}"
}

# ══════════════════════════════════════════════════════════════════════════════
# DASHBOARD PRINCIPALE
# ══════════════════════════════════════════════════════════════════════════════

show_dashboard() {
    # === DATABASE ===
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}📊 DATABASE${NC}"
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    VENDITE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM vendite" 2>/dev/null || echo "?")
    SPESE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM spese" 2>/dev/null || echo "?")
    PRODOTTI=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM prodotti" 2>/dev/null || echo "?")
    TOT_VENDITE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COALESCE(SUM(prezzo), 0) FROM vendite" 2>/dev/null || echo "?")
    TOT_SPESE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COALESCE(SUM(importo), 0) FROM spese" 2>/dev/null || echo "?")
    
    echo -e "   📦 Prodotti: ${GREEN}${PRODOTTI}${NC}    🛒 Vendite: ${GREEN}${VENDITE}${NC} (€${TOT_VENDITE})    💸 Spese: ${GREEN}${SPESE}${NC} (€${TOT_SPESE})"
    echo ""
    
    # === FILE TXT ===
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}📄 FILE DI SINCRONIZZAZIONE${NC}"
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    # STORICO.txt
    if [ -f "${WEB_DIR}/STORICO.txt" ]; then
        STORICO_SIZE=$(du -h "${WEB_DIR}/STORICO.txt" | cut -f1)
        STORICO_DATE=$(stat -c %y "${WEB_DIR}/STORICO.txt" 2>/dev/null | cut -d'.' -f1)
        echo -e "   📜 STORICO.txt  │ ${GREEN}${STORICO_SIZE}${NC} │ Aggiornato: ${YELLOW}${STORICO_DATE}${NC}"
    else
        echo -e "   📜 STORICO.txt  │ ${RED}Non trovato${NC}"
    fi
    
    # LISTINO.txt
    if [ -f "${WEB_DIR}/LISTINO.txt" ]; then
        LISTINO_SIZE=$(du -h "${WEB_DIR}/LISTINO.txt" | cut -f1)
        LISTINO_DATE=$(stat -c %y "${WEB_DIR}/LISTINO.txt" 2>/dev/null | cut -d'.' -f1)
        echo -e "   📋 LISTINO.txt  │ ${GREEN}${LISTINO_SIZE}${NC} │ Aggiornato: ${YELLOW}${LISTINO_DATE}${NC}"
    else
        echo -e "   📋 LISTINO.txt  │ ${RED}Non trovato${NC}"
    fi
    echo ""
    
    # === BACKUP SQL ===
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}💾 BACKUP DATABASE (SQL)${NC}  │  Cartella: ${BLUE}${BACKUP_SQL_DIR}${NC}"
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    SQL_COUNT=$(ls -1 ${BACKUP_SQL_DIR}/*.gz 2>/dev/null | wc -l)
    echo -e "   Totale: ${YELLOW}${SQL_COUNT}${NC} backup"
    
    if [ "${SQL_COUNT}" -gt 0 ]; then
        LATEST_SQL=$(ls -t ${BACKUP_SQL_DIR}/*.gz 2>/dev/null | head -1)
        LATEST_SQL_NAME=$(basename ${LATEST_SQL})
        LATEST_SQL_SIZE=$(du -h ${LATEST_SQL} | cut -f1)
        LATEST_SQL_DATE=$(stat -c %y ${LATEST_SQL} 2>/dev/null | cut -d'.' -f1)
        echo -e "   ${GREEN}★ Più recente:${NC} ${CYAN}${LATEST_SQL_NAME}${NC} (${LATEST_SQL_SIZE}) - ${YELLOW}${LATEST_SQL_DATE}${NC}"
    fi
    echo ""
    
    # === BACKUP XLSX ===
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}📊 BACKUP EXCEL (XLSX)${NC}  │  Cartella: ${BLUE}${BACKUP_XLSX_DIR}${NC}"
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    XLSX_COUNT=$(ls -1 ${BACKUP_XLSX_DIR}/*.xlsx ${BACKUP_XLSX_DIR}/*.xls ${BACKUP_APP_DIR}/*.xlsx ${BACKUP_APP_DIR}/*.xls 2>/dev/null | wc -l)
    echo -e "   Totale: ${YELLOW}${XLSX_COUNT}${NC} file Excel"
    
    # Trova il più recente tra tutte le cartelle
    LATEST_XLSX=$(ls -t ${BACKUP_XLSX_DIR}/*.xlsx ${BACKUP_XLSX_DIR}/*.xls ${BACKUP_APP_DIR}/*.xlsx ${BACKUP_APP_DIR}/*.xls 2>/dev/null | head -1)
    if [ -n "${LATEST_XLSX}" ] && [ -f "${LATEST_XLSX}" ]; then
        LATEST_XLSX_NAME=$(basename ${LATEST_XLSX})
        LATEST_XLSX_SIZE=$(du -h ${LATEST_XLSX} | cut -f1)
        LATEST_XLSX_DATE=$(stat -c %y ${LATEST_XLSX} 2>/dev/null | cut -d'.' -f1)
        echo -e "   ${GREEN}★ Più recente:${NC} ${CYAN}${LATEST_XLSX_NAME}${NC} (${LATEST_XLSX_SIZE}) - ${YELLOW}${LATEST_XLSX_DATE}${NC}"
    fi
    echo ""
}

# ══════════════════════════════════════════════════════════════════════════════
# MENU BACKUP SQL
# ══════════════════════════════════════════════════════════════════════════════

menu_backup_sql() {
    while true; do
        clear_screen
        show_header
        show_submenu_header "💾 GESTIONE BACKUP DATABASE (SQL)"
        
        SQL_COUNT=$(ls -1 ${BACKUP_SQL_DIR}/*.gz 2>/dev/null | wc -l)
        SQL_SIZE=$(du -sh ${BACKUP_SQL_DIR} 2>/dev/null | cut -f1)
        echo -e "   Backup disponibili: ${YELLOW}${SQL_COUNT}${NC}  │  Spazio occupato: ${SQL_SIZE}"
        echo ""
        
        echo -e "   ${GREEN}1)${NC} 📦 Crea nuovo backup SQL"
        echo -e "   ${GREEN}2)${NC} 📋 Lista tutti i backup SQL"
        echo -e "   ${GREEN}3)${NC} 🔄 Ripristina un backup SQL"
        echo -e "   ${GREEN}4)${NC} 🗑️  Elimina backup SQL"
        echo ""
        echo -e "   ${RED}0)${NC} ← Torna al menu principale"
        echo ""
        
        read -p "   Scegli (0-4): " choice
        
        case $choice in
            1) do_backup_sql ;;
            2) list_backup_sql ;;
            3) restore_backup_sql ;;
            4) delete_backup_sql ;;
            0) return ;;
            *) echo -e "${RED}   Opzione non valida!${NC}"; sleep 1 ;;
        esac
    done
}

do_backup_sql() {
    clear_screen
    show_submenu_header "📦 CREAZIONE BACKUP SQL"
    
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    BACKUP_FILE="${BACKUP_SQL_DIR}/backup_${TIMESTAMP}.sql"
    
    echo -e "   Creazione backup in corso..."
    
    mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} > ${BACKUP_FILE} 2>/dev/null
    
    if [ $? -eq 0 ]; then
        gzip ${BACKUP_FILE}
        SIZE=$(du -h "${BACKUP_FILE}.gz" | cut -f1)
        
        echo ""
        echo -e "${GREEN}   ✅ BACKUP COMPLETATO!${NC}"
        echo -e "   📁 File: ${CYAN}backup_${TIMESTAMP}.sql.gz${NC}"
        echo -e "   📏 Dimensione: ${SIZE}"
    else
        echo -e "${RED}   ❌ Errore durante il backup!${NC}"
    fi
    
    press_enter
}

list_backup_sql() {
    clear_screen
    show_submenu_header "📋 LISTA BACKUP SQL"
    
    if [ ! "$(ls -A ${BACKUP_SQL_DIR}/*.gz 2>/dev/null)" ]; then
        echo -e "${RED}   Nessun backup SQL trovato.${NC}"
        press_enter
        return
    fi
    
    echo ""
    printf "   ${CYAN}%-4s %-45s %-8s %-20s${NC}\n" "N°" "NOME FILE" "DIM." "DATA"
    echo -e "   ${WHITE}────────────────────────────────────────────────────────────────────────${NC}"
    
    i=1
    for file in $(ls -t ${BACKUP_SQL_DIR}/*.gz 2>/dev/null); do
        filename=$(basename $file)
        size=$(du -h $file | cut -f1)
        date=$(stat -c %y $file | cut -d'.' -f1 | cut -d' ' -f1,2 | cut -c1-16)
        
        if [ $i -eq 1 ]; then
            printf "   ${GREEN}%-4s %-45s %-8s %-20s ★${NC}\n" "$i." "$filename" "$size" "$date"
        else
            printf "   %-4s %-45s %-8s %-20s\n" "$i." "$filename" "$size" "$date"
        fi
        i=$((i+1))
    done
    
    echo ""
    TOTAL_SIZE=$(du -sh ${BACKUP_SQL_DIR} 2>/dev/null | cut -f1)
    echo -e "   ${BLUE}Totale: $((i-1)) backup │ Spazio: ${TOTAL_SIZE}${NC}"
    
    press_enter
}

restore_backup_sql() {
    clear_screen
    show_submenu_header "🔄 RIPRISTINO BACKUP SQL"
    
    if [ ! "$(ls -A ${BACKUP_SQL_DIR}/*.gz 2>/dev/null)" ]; then
        echo -e "${RED}   Nessun backup trovato.${NC}"
        press_enter
        return
    fi
    
    echo "   Seleziona il backup da ripristinare:"
    echo ""
    
    files=($(ls -t ${BACKUP_SQL_DIR}/*.gz 2>/dev/null))
    
    i=1
    for file in "${files[@]}"; do
        filename=$(basename $file)
        size=$(du -h $file | cut -f1)
        date=$(stat -c %y $file | cut -d'.' -f1 | cut -c1-16)
        if [ $i -eq 1 ]; then
            echo -e "   ${GREEN}$i) $filename ($size) - $date ★ PIÙ RECENTE${NC}"
        else
            echo "   $i) $filename ($size) - $date"
        fi
        i=$((i+1))
    done
    
    echo ""
    echo "   0) ❌ Annulla"
    echo ""
    
    read -p "   Numero backup: " choice
    
    if [ "$choice" = "0" ] || [ -z "$choice" ]; then
        return
    fi
    
    if [ "$choice" -lt 1 ] || [ "$choice" -gt "${#files[@]}" ] 2>/dev/null; then
        echo -e "${RED}   Scelta non valida!${NC}"
        press_enter
        return
    fi
    
    selected_file="${files[$((choice-1))]}"
    
    echo ""
    echo -e "${RED}   ⚠️  ATTENZIONE: Questo sovrascriverà tutti i dati attuali!${NC}"
    read -p "   Scrivi 'SI' per confermare: " confirm
    
    if [ "$confirm" != "SI" ]; then
        echo -e "${BLUE}   Operazione annullata.${NC}"
        press_enter
        return
    fi
    
    echo ""
    echo -e "${YELLOW}   Backup di sicurezza...${NC}"
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} 2>/dev/null | gzip > "${BACKUP_SQL_DIR}/backup_pre_ripristino_${TIMESTAMP}.sql.gz"
    
    echo -e "${YELLOW}   Ripristino in corso...${NC}"
    gunzip -k -f ${selected_file}
    sql_file="${selected_file%.gz}"
    mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} < ${sql_file} 2>/dev/null
    rm -f ${sql_file}
    
    # Aggiorna file TXT
    php ${WEB_DIR}/cron_sync.php 2>/dev/null
    
    echo ""
    echo -e "${GREEN}   ✅ RIPRISTINO COMPLETATO!${NC}"
    
    press_enter
}

delete_backup_sql() {
    clear_screen
    show_submenu_header "🗑️ ELIMINA BACKUP SQL"
    
    if [ ! "$(ls -A ${BACKUP_SQL_DIR}/*.gz 2>/dev/null)" ]; then
        echo -e "${RED}   Nessun backup trovato.${NC}"
        press_enter
        return
    fi
    
    files=($(ls -t ${BACKUP_SQL_DIR}/*.gz 2>/dev/null))
    
    echo "   Seleziona backup da eliminare:"
    echo ""
    
    i=1
    for file in "${files[@]}"; do
        filename=$(basename $file)
        size=$(du -h $file | cut -f1)
        echo "   $i) $filename ($size)"
        i=$((i+1))
    done
    
    echo ""
    echo "   A) 🗑️  Elimina TUTTI"
    echo "   V) 🧹 Elimina vecchi (mantieni ultimi 5)"
    echo "   0) ❌ Annulla"
    echo ""
    
    read -p "   Scelta: " choice
    
    case $choice in
        0|"") return ;;
        A|a)
            read -p "   Eliminare TUTTI i backup? (SI/NO): " confirm
            if [ "$confirm" = "SI" ]; then
                rm -f ${BACKUP_SQL_DIR}/*.gz
                echo -e "${GREEN}   ✅ Tutti i backup eliminati.${NC}"
            fi
            ;;
        V|v)
            echo -e "${YELLOW}   Eliminazione backup vecchi...${NC}"
            cd ${BACKUP_SQL_DIR}
            ls -t *.gz 2>/dev/null | tail -n +6 | xargs -r rm
            echo -e "${GREEN}   ✅ Mantenuti solo gli ultimi 5 backup.${NC}"
            ;;
        *)
            if [ "$choice" -ge 1 ] && [ "$choice" -le "${#files[@]}" ] 2>/dev/null; then
                selected_file="${files[$((choice-1))]}"
                rm -f ${selected_file}
                echo -e "${GREEN}   ✅ Backup eliminato.${NC}"
            else
                echo -e "${RED}   Scelta non valida!${NC}"
            fi
            ;;
    esac
    
    press_enter
}

# ══════════════════════════════════════════════════════════════════════════════
# MENU BACKUP XLSX
# ══════════════════════════════════════════════════════════════════════════════

menu_backup_xlsx() {
    while true; do
        clear_screen
        show_header
        show_submenu_header "📊 GESTIONE BACKUP EXCEL (XLSX)"
        
        # Conta file in entrambe le cartelle
        XLSX_GIORNALIERI=$(ls -1 ${BACKUP_XLSX_DIR}/*.xlsx ${BACKUP_XLSX_DIR}/*.xls 2>/dev/null | wc -l)
        XLSX_APP=$(ls -1 ${BACKUP_APP_DIR}/*.xlsx ${BACKUP_APP_DIR}/*.xls 2>/dev/null | wc -l)
        
        echo ""
        echo -e "   ${CYAN}Cartelle backup Excel:${NC}"
        echo -e "   📁 Giornalieri: ${BLUE}${BACKUP_XLSX_DIR}${NC} (${YELLOW}${XLSX_GIORNALIERI}${NC} file)"
        echo -e "   📁 Da app web:  ${BLUE}${BACKUP_APP_DIR}${NC} (${YELLOW}${XLSX_APP}${NC} file)"
        echo ""
        
        echo -e "   ${GREEN}1)${NC} 📋 Lista tutti i file Excel"
        echo -e "   ${GREEN}2)${NC} 📥 Importa file Excel nel database"
        echo -e "   ${GREEN}3)${NC} 🔍 Testa importazione (senza modificare)"
        echo -e "   ${GREEN}4)${NC} 🗑️  Elimina file Excel"
        echo -e "   ${GREEN}5)${NC} 📂 Apri cartella backup (mostra percorso)"
        echo ""
        echo -e "   ${RED}0)${NC} ← Torna al menu principale"
        echo ""
        
        read -p "   Scegli (0-5): " choice
        
        case $choice in
            1) list_backup_xlsx ;;
            2) import_backup_xlsx ;;
            3) test_import_xlsx ;;
            4) delete_backup_xlsx ;;
            5) show_xlsx_folders ;;
            0) return ;;
            *) echo -e "${RED}   Opzione non valida!${NC}"; sleep 1 ;;
        esac
    done
}

list_backup_xlsx() {
    clear_screen
    show_submenu_header "📋 LISTA FILE EXCEL"
    
    # Raccogli tutti i file xlsx/xls
    all_files=()
    
    for file in ${BACKUP_XLSX_DIR}/*.xlsx ${BACKUP_XLSX_DIR}/*.xls ${BACKUP_APP_DIR}/*.xlsx ${BACKUP_APP_DIR}/*.xls 2>/dev/null; do
        if [ -f "$file" ]; then
            all_files+=("$file")
        fi
    done
    
    if [ ${#all_files[@]} -eq 0 ]; then
        echo -e "${RED}   Nessun file Excel trovato.${NC}"
        press_enter
        return
    fi
    
    # Ordina per data (più recente prima)
    IFS=$'\n' sorted_files=($(ls -t "${all_files[@]}" 2>/dev/null))
    unset IFS
    
    echo ""
    printf "   ${CYAN}%-4s %-40s %-8s %-20s${NC}\n" "N°" "NOME FILE" "DIM." "DATA"
    echo -e "   ${WHITE}────────────────────────────────────────────────────────────────────────${NC}"
    
    i=1
    for file in "${sorted_files[@]}"; do
        filename=$(basename "$file")
        size=$(du -h "$file" | cut -f1)
        date=$(stat -c %y "$file" | cut -d'.' -f1 | cut -c1-16)
        
        # Indica la cartella di provenienza
        if [[ "$file" == *"BACKUP_GIORNALIERI"* ]]; then
            folder="[Giorn]"
        else
            folder="[App]"
        fi
        
        if [ $i -eq 1 ]; then
            printf "   ${GREEN}%-4s %-40s %-8s %-20s ★${NC}\n" "$i." "${filename:0:35} $folder" "$size" "$date"
        else
            printf "   %-4s %-40s %-8s %-20s\n" "$i." "${filename:0:35} $folder" "$size" "$date"
        fi
        i=$((i+1))
    done
    
    echo ""
    echo -e "   ${BLUE}Totale: $((i-1)) file Excel${NC}"
    
    press_enter
}

import_backup_xlsx() {
    clear_screen
    show_submenu_header "📥 IMPORTA FILE EXCEL"
    
    # Raccogli tutti i file
    all_files=()
    for file in ${BACKUP_XLSX_DIR}/*.xlsx ${BACKUP_XLSX_DIR}/*.xls ${BACKUP_APP_DIR}/*.xlsx ${BACKUP_APP_DIR}/*.xls 2>/dev/null; do
        if [ -f "$file" ]; then
            all_files+=("$file")
        fi
    done
    
    if [ ${#all_files[@]} -eq 0 ]; then
        echo -e "${RED}   Nessun file Excel trovato.${NC}"
        echo ""
        echo -e "${YELLOW}   Puoi copiare un file .xlsx in:${NC}"
        echo -e "   ${BLUE}${BACKUP_XLSX_DIR}/${NC}"
        press_enter
        return
    fi
    
    IFS=$'\n' sorted_files=($(ls -t "${all_files[@]}" 2>/dev/null))
    unset IFS
    
    echo "   Seleziona file da importare:"
    echo ""
    
    i=1
    for file in "${sorted_files[@]}"; do
        filename=$(basename "$file")
        size=$(du -h "$file" | cut -f1)
        if [ $i -eq 1 ]; then
            echo -e "   ${GREEN}$i) $filename ($size) ★ PIÙ RECENTE${NC}"
        else
            echo "   $i) $filename ($size)"
        fi
        i=$((i+1))
    done
    
    echo ""
    echo "   P) 📂 Inserisci percorso manuale"
    echo "   0) ❌ Annulla"
    echo ""
    
    read -p "   Scelta: " choice
    
    if [ "$choice" = "0" ] || [ -z "$choice" ]; then
        return
    fi
    
    if [ "$choice" = "P" ] || [ "$choice" = "p" ]; then
        read -p "   Percorso completo del file: " manual_path
        if [ ! -f "$manual_path" ]; then
            echo -e "${RED}   File non trovato!${NC}"
            press_enter
            return
        fi
        selected_file="$manual_path"
    elif [ "$choice" -ge 1 ] && [ "$choice" -le "${#sorted_files[@]}" ] 2>/dev/null; then
        selected_file="${sorted_files[$((choice-1))]}"
    else
        echo -e "${RED}   Scelta non valida!${NC}"
        press_enter
        return
    fi
    
    echo ""
    echo -e "${RED}╔══════════════════════════════════════════════════════════════╗"
    echo "║  ⚠️  ATTENZIONE: L'importazione aggiungerà dati al database! ║"
    echo "║  Consiglio: esegui prima un RESET se vuoi partire da zero.   ║"
    echo "╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "   ${YELLOW}Vuoi fare backup + reset prima dell'importazione?${NC}"
    echo "   1) Sì, backup + reset + importa"
    echo "   2) No, importa senza reset (aggiungi ai dati esistenti)"
    echo "   0) Annulla"
    echo ""
    
    read -p "   Scelta: " reset_choice
    
    case $reset_choice in
        1)
            echo ""
            echo -e "${YELLOW}   Backup database...${NC}"
            TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
            mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} 2>/dev/null | gzip > "${BACKUP_SQL_DIR}/backup_pre_import_${TIMESTAMP}.sql.gz"
            
            echo -e "${YELLOW}   Reset database...${NC}"
            mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -e "DELETE FROM vendite; DELETE FROM spese; ALTER TABLE vendite AUTO_INCREMENT = 1; ALTER TABLE spese AUTO_INCREMENT = 1;" 2>/dev/null
            ;;
        2)
            echo ""
            ;;
        *)
            echo -e "${BLUE}   Operazione annullata.${NC}"
            press_enter
            return
            ;;
    esac
    
    echo -e "${YELLOW}   Importazione in corso...${NC}"
    echo ""
    
    php ${WEB_DIR}/import_xlsx.php "$selected_file"
    
    press_enter
}

test_import_xlsx() {
    clear_screen
    show_submenu_header "🔍 TEST IMPORTAZIONE (senza modificare)"
    
    # Raccogli tutti i file
    all_files=()
    for file in ${BACKUP_XLSX_DIR}/*.xlsx ${BACKUP_XLSX_DIR}/*.xls ${BACKUP_APP_DIR}/*.xlsx ${BACKUP_APP_DIR}/*.xls 2>/dev/null; do
        if [ -f "$file" ]; then
            all_files+=("$file")
        fi
    done
    
    if [ ${#all_files[@]} -eq 0 ]; then
        echo -e "${RED}   Nessun file Excel trovato.${NC}"
        press_enter
        return
    fi
    
    IFS=$'\n' sorted_files=($(ls -t "${all_files[@]}" 2>/dev/null))
    unset IFS
    
    echo "   Seleziona file da testare:"
    echo ""
    
    i=1
    for file in "${sorted_files[@]}"; do
        filename=$(basename "$file")
        echo "   $i) $filename"
        i=$((i+1))
    done
    
    echo ""
    echo "   P) 📂 Inserisci percorso manuale"
    echo "   0) ❌ Annulla"
    echo ""
    
    read -p "   Scelta: " choice
    
    if [ "$choice" = "0" ] || [ -z "$choice" ]; then
        return
    fi
    
    if [ "$choice" = "P" ] || [ "$choice" = "p" ]; then
        read -p "   Percorso completo del file: " manual_path
        selected_file="$manual_path"
    elif [ "$choice" -ge 1 ] && [ "$choice" -le "${#sorted_files[@]}" ] 2>/dev/null; then
        selected_file="${sorted_files[$((choice-1))]}"
    else
        echo -e "${RED}   Scelta non valida!${NC}"
        press_enter
        return
    fi
    
    echo ""
    php ${WEB_DIR}/test_import_xlsx.php "$selected_file"
    
    press_enter
}

delete_backup_xlsx() {
    clear_screen
    show_submenu_header "🗑️ ELIMINA FILE EXCEL"
    
    all_files=()
    for file in ${BACKUP_XLSX_DIR}/*.xlsx ${BACKUP_XLSX_DIR}/*.xls ${BACKUP_APP_DIR}/*.xlsx ${BACKUP_APP_DIR}/*.xls 2>/dev/null; do
        if [ -f "$file" ]; then
            all_files+=("$file")
        fi
    done
    
    if [ ${#all_files[@]} -eq 0 ]; then
        echo -e "${RED}   Nessun file Excel trovato.${NC}"
        press_enter
        return
    fi
    
    IFS=$'\n' sorted_files=($(ls -t "${all_files[@]}" 2>/dev/null))
    unset IFS
    
    echo "   Seleziona file da eliminare:"
    echo ""
    
    i=1
    for file in "${sorted_files[@]}"; do
        filename=$(basename "$file")
        size=$(du -h "$file" | cut -f1)
        echo "   $i) $filename ($size)"
        i=$((i+1))
    done
    
    echo ""
    echo "   A) 🗑️  Elimina TUTTI"
    echo "   0) ❌ Annulla"
    echo ""
    
    read -p "   Scelta: " choice
    
    case $choice in
        0|"") return ;;
        A|a)
            read -p "   Eliminare TUTTI i file Excel? (SI/NO): " confirm
            if [ "$confirm" = "SI" ]; then
                rm -f ${BACKUP_XLSX_DIR}/*.xlsx ${BACKUP_XLSX_DIR}/*.xls 2>/dev/null
                rm -f ${BACKUP_APP_DIR}/*.xlsx ${BACKUP_APP_DIR}/*.xls 2>/dev/null
                echo -e "${GREEN}   ✅ Tutti i file Excel eliminati.${NC}"
            fi
            ;;
        *)
            if [ "$choice" -ge 1 ] && [ "$choice" -le "${#sorted_files[@]}" ] 2>/dev/null; then
                selected_file="${sorted_files[$((choice-1))]}"
                rm -f "$selected_file"
                echo -e "${GREEN}   ✅ File eliminato.${NC}"
            else
                echo -e "${RED}   Scelta non valida!${NC}"
            fi
            ;;
    esac
    
    press_enter
}

show_xlsx_folders() {
    clear_screen
    show_submenu_header "📂 CARTELLE BACKUP EXCEL"
    
    echo ""
    echo -e "   ${CYAN}Le cartelle dove puoi mettere i file .xlsx sono:${NC}"
    echo ""
    echo -e "   ${GREEN}1. Backup Giornalieri (automatici da app):${NC}"
    echo -e "      ${BLUE}${BACKUP_XLSX_DIR}${NC}"
    echo ""
    echo -e "   ${GREEN}2. Backup da App Web:${NC}"
    echo -e "      ${BLUE}${BACKUP_APP_DIR}${NC}"
    echo ""
    echo -e "   ${YELLOW}Per copiare un file da chiavetta USB:${NC}"
    echo ""
    echo -e "   1. Inserisci la chiavetta USB"
    echo -e "   2. Trova dove è montata: ${CYAN}ls /media/${NC}"
    echo -e "   3. Copia il file:"
    echo -e "      ${CYAN}cp /media/usb_sda1/file.xlsx ${BACKUP_XLSX_DIR}/${NC}"
    echo ""
    
    press_enter
}

# ══════════════════════════════════════════════════════════════════════════════
# ALTRE FUNZIONI
# ══════════════════════════════════════════════════════════════════════════════

reset_database() {
    clear_screen
    show_submenu_header "⚠️ RESET DATABASE"
    
    VENDITE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM vendite" 2>/dev/null || echo "0")
    SPESE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM spese" 2>/dev/null || echo "0")
    
    echo ""
    echo -e "   Dati attuali: ${YELLOW}${VENDITE}${NC} vendite, ${YELLOW}${SPESE}${NC} spese"
    echo ""
    echo -e "${RED}   ⚠️  Questa operazione CANCELLERÀ tutte le vendite e spese!${NC}"
    echo -e "${GREEN}   I prodotti NON verranno cancellati.${NC}"
    echo -e "${BLUE}   Verrà creato un backup automatico prima del reset.${NC}"
    echo ""
    
    read -p "   Scrivi 'RESET' per confermare: " confirm
    
    if [ "$confirm" != "RESET" ]; then
        echo -e "${BLUE}   Operazione annullata.${NC}"
        press_enter
        return
    fi
    
    echo ""
    echo -e "${YELLOW}   Backup di sicurezza...${NC}"
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} 2>/dev/null | gzip > "${BACKUP_SQL_DIR}/backup_pre_reset_${TIMESTAMP}.sql.gz"
    
    echo -e "${YELLOW}   Reset in corso...${NC}"
    mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -e "
        DELETE FROM vendite;
        DELETE FROM spese;
        ALTER TABLE vendite AUTO_INCREMENT = 1;
        ALTER TABLE spese AUTO_INCREMENT = 1;
    " 2>/dev/null
    
    # Aggiorna STORICO.txt
    php ${WEB_DIR}/cron_sync.php 2>/dev/null
    
    echo ""
    echo -e "${GREEN}   ✅ RESET COMPLETATO!${NC}"
    echo -e "   💾 Backup salvato: backup_pre_reset_${TIMESTAMP}.sql.gz"
    
    press_enter
}

export_txt() {
    clear_screen
    show_submenu_header "📄 ESPORTA IN FORMATO LEGGIBILE"
    
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    EXPORT_FILE="${BACKUP_SQL_DIR}/export_${TIMESTAMP}.txt"
    
    echo -e "   Generazione export..."
    
    {
        echo "══════════════════════════════════════════════════════════════"
        echo "        EXPORT PROLOCO BAR - $(date '+%d/%m/%Y %H:%M')"
        echo "══════════════════════════════════════════════════════════════"
        echo ""
        
        TOT_V=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COALESCE(SUM(prezzo), 0) FROM vendite" 2>/dev/null)
        TOT_S=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COALESCE(SUM(importo), 0) FROM spese" 2>/dev/null)
        
        echo "RIEPILOGO"
        echo "─────────────────────────────────────────"
        echo "Totale Vendite: €${TOT_V}"
        echo "Totale Spese: €${TOT_S}"
        echo ""
        
        echo "VENDITE"
        echo "─────────────────────────────────────────"
        mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -e "
            SELECT DATE_FORMAT(timestamp, '%d/%m/%Y %H:%i') as Data, 
                   nome_prodotto as Prodotto, 
                   CONCAT('€', FORMAT(prezzo, 2)) as Importo 
            FROM vendite ORDER BY timestamp DESC" 2>/dev/null
        echo ""
        
        echo "SPESE"
        echo "─────────────────────────────────────────"
        mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -e "
            SELECT DATE_FORMAT(timestamp, '%d/%m/%Y %H:%i') as Data, 
                   categoria_spesa as Categoria, 
                   CONCAT('€', FORMAT(importo, 2)) as Importo
            FROM spese ORDER BY timestamp DESC" 2>/dev/null
        
    } > ${EXPORT_FILE}
    
    echo ""
    echo -e "${GREEN}   ✅ EXPORT COMPLETATO!${NC}"
    echo -e "   📄 File: ${CYAN}${EXPORT_FILE}${NC}"
    
    press_enter
}

# ══════════════════════════════════════════════════════════════════════════════
# MENU PRINCIPALE
# ══════════════════════════════════════════════════════════════════════════════

main_menu() {
    while true; do
        clear_screen
        show_header
        show_dashboard
        
        echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo -e "${CYAN}                        MENU PRINCIPALE${NC}"
        echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo ""
        echo -e "   ${GREEN}1)${NC} 💾 Gestione Backup SQL (database)"
        echo -e "   ${GREEN}2)${NC} 📊 Gestione Backup Excel (xlsx)"
        echo -e "   ${GREEN}3)${NC} ⚠️  Reset database (cancella vendite/spese)"
        echo -e "   ${GREEN}4)${NC} 📄 Esporta in formato leggibile (.txt)"
        echo ""
        echo -e "   ${RED}0)${NC} 🚪 Esci"
        echo ""
        
        read -p "   Scegli (0-4): " choice
        
        case $choice in
            1) menu_backup_sql ;;
            2) menu_backup_xlsx ;;
            3) reset_database ;;
            4) export_txt ;;
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
