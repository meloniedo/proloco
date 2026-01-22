#!/bin/bash
# ========================================
# SCRIPT DI INSTALLAZIONE AUTOMATICA
# Proloco Santa Bianca - Bar Manager
# Per Raspberry Pi 3A+
# ========================================

set -e

echo "================================================"
echo "  INSTALLAZIONE BAR MANAGER - PROLOCO"
echo "  Per Raspberry Pi 3A+"
echo "================================================"
echo ""

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Variabili configurazione
DB_USER="edo"
DB_PASS="5054"
DB_NAME="proloco_bar"
WEB_DIR="/var/www/html/proloco"

# Verifica se è root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Errore: Esegui questo script come root (sudo)${NC}"
    exit 1
fi

echo -e "${YELLOW}1. Aggiornamento sistema...${NC}"
apt update && apt upgrade -y

echo -e "${YELLOW}2. Installazione Apache, PHP e MySQL...${NC}"
apt install -y apache2 php php-mysql php-json mariadb-server mariadb-client

echo -e "${YELLOW}3. Avvio servizi...${NC}"
systemctl enable apache2
systemctl enable mariadb
systemctl start apache2
systemctl start mariadb

echo -e "${YELLOW}4. Configurazione MySQL...${NC}"
# Crea utente e database
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo -e "${YELLOW}5. Creazione directory web...${NC}"
mkdir -p ${WEB_DIR}
mkdir -p ${WEB_DIR}/api
mkdir -p ${WEB_DIR}/css
mkdir -p ${WEB_DIR}/includes

echo -e "${YELLOW}6. Copia file applicazione...${NC}"
# Copia i file dalla directory corrente
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cp -r ${SCRIPT_DIR}/* ${WEB_DIR}/
rm -f ${WEB_DIR}/install.sh  # Rimuovi lo script di installazione dalla web dir

echo -e "${YELLOW}7. Impostazione permessi...${NC}"
chown -R www-data:www-data ${WEB_DIR}
chmod -R 755 ${WEB_DIR}

echo -e "${YELLOW}8. Creazione tabelle database...${NC}"
mysql ${DB_NAME} < ${WEB_DIR}/database.sql

echo -e "${YELLOW}9. Configurazione Apache VirtualHost...${NC}"
cat > /etc/apache2/sites-available/proloco.conf << EOF
<VirtualHost *:80>
    ServerName proloco.local
    DocumentRoot ${WEB_DIR}
    
    <Directory ${WEB_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/proloco_error.log
    CustomLog \${APACHE_LOG_DIR}/proloco_access.log combined
</VirtualHost>
EOF

# Abilita il sito
a2ensite proloco.conf
a2enmod rewrite

echo -e "${YELLOW}10. Riavvio Apache...${NC}"
systemctl restart apache2

# Ottieni l'indirizzo IP
IP_ADDRESS=$(hostname -I | awk '{print $1}')

echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}  INSTALLAZIONE COMPLETATA!${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo -e "Accedi all'app da:"
echo -e "  - ${GREEN}http://${IP_ADDRESS}/proloco/${NC}"
echo -e "  - ${GREEN}http://localhost/proloco/${NC}"
echo ""
echo -e "Credenziali Database:"
echo -e "  - User: ${DB_USER}"
echo -e "  - Password: ${DB_PASS}"
echo -e "  - Database: ${DB_NAME}"
echo ""
echo -e "Password Reset App: ${YELLOW}5054${NC}"
echo ""
echo -e "${YELLOW}Per gestire il Raspberry da remoto:${NC}"
echo -e "  ssh pi@${IP_ADDRESS}"
echo ""
