#!/bin/bash
# ========================================
# SCRIPT DI INSTALLAZIONE COMPLETA
# Proloco Santa Bianca - Bar Manager
# Per Raspberry Pi 3A+ come Hotspot WiFi
# ========================================

set -e

echo "================================================"
echo "  INSTALLAZIONE BAR MANAGER - PROLOCO"
echo "  Raspberry Pi 3A+ come Hotspot WiFi"
echo "================================================"
echo ""

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# ==================== CONFIGURAZIONE ====================
# Modifica questi valori se necessario

WIFI_SSID="ProlocoBar"           # Nome della rete WiFi
WIFI_PASSWORD="proloco2024"       # Password WiFi (minimo 8 caratteri)
WIFI_CHANNEL="7"                  # Canale WiFi
IP_ADDRESS="192.168.4.1"          # IP del Raspberry
NETMASK="255.255.255.0"
DHCP_RANGE_START="192.168.4.10"
DHCP_RANGE_END="192.168.4.50"

DB_USER="edo"
DB_PASS="5054"
DB_NAME="proloco_bar"
WEB_DIR="/var/www/html/proloco"

# ==================== VERIFICA ROOT ====================
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Errore: Esegui questo script come root (sudo)${NC}"
    exit 1
fi

# ==================== 1. AGGIORNAMENTO SISTEMA ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 1: Aggiornamento Sistema${NC}"
echo -e "${CYAN}========================================${NC}"
apt update && apt upgrade -y

# ==================== 2. INSTALLAZIONE PACCHETTI ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 2: Installazione Pacchetti${NC}"
echo -e "${CYAN}========================================${NC}"

echo -e "${YELLOW}Installazione Apache, PHP, MySQL...${NC}"
apt install -y apache2 php php-mysql php-json mariadb-server mariadb-client

echo -e "${YELLOW}Installazione software Hotspot...${NC}"
apt install -y hostapd dnsmasq iptables-persistent

# Ferma i servizi per la configurazione
systemctl stop hostapd 2>/dev/null || true
systemctl stop dnsmasq 2>/dev/null || true

# ==================== 3. CONFIGURAZIONE IP STATICO ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 3: Configurazione IP Statico${NC}"
echo -e "${CYAN}========================================${NC}"

# Backup e configura dhcpcd
cp /etc/dhcpcd.conf /etc/dhcpcd.conf.backup 2>/dev/null || true

# Rimuovi configurazioni wlan0 esistenti
sed -i '/interface wlan0/,/^$/d' /etc/dhcpcd.conf

# Aggiungi configurazione IP statico per wlan0
cat >> /etc/dhcpcd.conf << EOF

# Configurazione Hotspot Proloco
interface wlan0
    static ip_address=${IP_ADDRESS}/24
    nohook wpa_supplicant
EOF

echo -e "${GREEN}✓ IP statico configurato: ${IP_ADDRESS}${NC}"

# ==================== 4. CONFIGURAZIONE HOSTAPD ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 4: Configurazione Hotspot WiFi${NC}"
echo -e "${CYAN}========================================${NC}"

# Crea configurazione hostapd
cat > /etc/hostapd/hostapd.conf << EOF
# Configurazione Hotspot Proloco Santa Bianca
interface=wlan0
driver=nl80211
ssid=${WIFI_SSID}
hw_mode=g
channel=${WIFI_CHANNEL}
wmm_enabled=0
macaddr_acl=0
auth_algs=1
ignore_broadcast_ssid=0
wpa=2
wpa_passphrase=${WIFI_PASSWORD}
wpa_key_mgmt=WPA-PSK
wpa_pairwise=TKIP
rsn_pairwise=CCMP
EOF

# Imposta il file di configurazione di default
sed -i 's|#DAEMON_CONF=""|DAEMON_CONF="/etc/hostapd/hostapd.conf"|' /etc/default/hostapd

echo -e "${GREEN}✓ Hotspot configurato: ${WIFI_SSID}${NC}"

# ==================== 5. CONFIGURAZIONE DNSMASQ ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 5: Configurazione DHCP Server${NC}"
echo -e "${CYAN}========================================${NC}"

# Backup configurazione originale
mv /etc/dnsmasq.conf /etc/dnsmasq.conf.backup 2>/dev/null || true

# Crea nuova configurazione
cat > /etc/dnsmasq.conf << EOF
# Configurazione DHCP per Hotspot Proloco
interface=wlan0
dhcp-range=${DHCP_RANGE_START},${DHCP_RANGE_END},${NETMASK},24h
domain=local
address=/proloco.local/${IP_ADDRESS}
address=/bar.local/${IP_ADDRESS}

# Redirect tutte le richieste DNS al Raspberry (Captive Portal)
address=/#/${IP_ADDRESS}
EOF

echo -e "${GREEN}✓ DHCP Server configurato${NC}"

# ==================== 6. SBLOCCO WIFI ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 6: Sblocco Interfaccia WiFi${NC}"
echo -e "${CYAN}========================================${NC}"

# Sblocca rfkill se bloccato
rfkill unblock wlan 2>/dev/null || true

echo -e "${GREEN}✓ Interfaccia WiFi sbloccata${NC}"

# ==================== 7. CONFIGURAZIONE MYSQL ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 7: Configurazione Database${NC}"
echo -e "${CYAN}========================================${NC}"

# Avvia MariaDB
systemctl start mariadb
systemctl enable mariadb

# Crea database e utente
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo -e "${GREEN}✓ Database creato: ${DB_NAME}${NC}"

# ==================== 8. INSTALLAZIONE APP WEB ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 8: Installazione App Web${NC}"
echo -e "${CYAN}========================================${NC}"

# Crea directory
mkdir -p ${WEB_DIR}
mkdir -p ${WEB_DIR}/api
mkdir -p ${WEB_DIR}/css
mkdir -p ${WEB_DIR}/includes
mkdir -p ${WEB_DIR}/icons

# Copia file dalla directory corrente
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cp -r ${SCRIPT_DIR}/* ${WEB_DIR}/ 2>/dev/null || true
rm -f ${WEB_DIR}/install.sh

# Importa schema database
mysql ${DB_NAME} < ${WEB_DIR}/database.sql

# Permessi
chown -R www-data:www-data ${WEB_DIR}
chmod -R 755 ${WEB_DIR}

echo -e "${GREEN}✓ App web installata${NC}"

# ==================== 9. CONFIGURAZIONE APACHE ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 9: Configurazione Web Server${NC}"
echo -e "${CYAN}========================================${NC}"

# Configura Apache per rispondere su porta 80
cat > /etc/apache2/sites-available/proloco.conf << EOF
<VirtualHost *:80>
    ServerName proloco.local
    ServerAlias bar.local ${IP_ADDRESS}
    DocumentRoot ${WEB_DIR}
    
    <Directory ${WEB_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Redirect root to /proloco
    RedirectMatch ^/$ /index.html
    
    ErrorLog \${APACHE_LOG_DIR}/proloco_error.log
    CustomLog \${APACHE_LOG_DIR}/proloco_access.log combined
</VirtualHost>
EOF

# Disabilita sito default, abilita proloco
a2dissite 000-default.conf 2>/dev/null || true
a2ensite proloco.conf
a2enmod rewrite

# Avvia Apache
systemctl restart apache2
systemctl enable apache2

echo -e "${GREEN}✓ Web server configurato${NC}"

# ==================== 10. AVVIO AUTOMATICO SERVIZI ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 10: Configurazione Avvio Automatico${NC}"
echo -e "${CYAN}========================================${NC}"

# Unmasking hostapd (necessario su alcuni sistemi)
systemctl unmask hostapd

# Abilita servizi all'avvio
systemctl enable hostapd
systemctl enable dnsmasq
systemctl enable apache2
systemctl enable mariadb

# Crea script di avvio
cat > /usr/local/bin/start-proloco.sh << 'EOF'
#!/bin/bash
# Script avvio Proloco Bar Manager
sleep 5
rfkill unblock wlan
systemctl restart dhcpcd
sleep 3
systemctl restart hostapd
systemctl restart dnsmasq
systemctl restart apache2
EOF

chmod +x /usr/local/bin/start-proloco.sh

# Aggiungi a rc.local per avvio al boot
cat > /etc/rc.local << 'EOF'
#!/bin/bash
/usr/local/bin/start-proloco.sh &
exit 0
EOF

chmod +x /etc/rc.local

# Crea anche servizio systemd come backup
cat > /etc/systemd/system/proloco-hotspot.service << EOF
[Unit]
Description=Proloco Bar Manager Hotspot
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/start-proloco.sh
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

systemctl enable proloco-hotspot.service

echo -e "${GREEN}✓ Avvio automatico configurato${NC}"

# ==================== 11. AVVIO SERVIZI ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 11: Avvio Servizi${NC}"
echo -e "${CYAN}========================================${NC}"

# Riavvia dhcpcd per applicare IP statico
systemctl restart dhcpcd
sleep 2

# Avvia hostapd e dnsmasq
systemctl start hostapd
systemctl start dnsmasq

echo -e "${GREEN}✓ Servizi avviati${NC}"

# ==================== COMPLETATO ====================
echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}  INSTALLAZIONE COMPLETATA!${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo -e "${CYAN}RETE WIFI:${NC}"
echo -e "  Nome (SSID): ${GREEN}${WIFI_SSID}${NC}"
echo -e "  Password:    ${GREEN}${WIFI_PASSWORD}${NC}"
echo ""
echo -e "${CYAN}INDIRIZZO APP:${NC}"
echo -e "  ${GREEN}http://${IP_ADDRESS}/${NC}"
echo -e "  ${GREEN}http://proloco.local/${NC}"
echo ""
echo -e "${CYAN}CREDENZIALI DATABASE:${NC}"
echo -e "  User:     ${DB_USER}"
echo -e "  Password: ${DB_PASS}"
echo -e "  Database: ${DB_NAME}"
echo ""
echo -e "${CYAN}PASSWORD RESET APP:${NC} ${YELLOW}5054${NC}"
echo ""
echo -e "${CYAN}COME USARE:${NC}"
echo -e "  1. Connettiti alla rete WiFi '${WIFI_SSID}'"
echo -e "  2. Apri il browser e vai su ${GREEN}http://${IP_ADDRESS}/${NC}"
echo -e "  3. Su iPhone/Android: 'Aggiungi a schermata Home'"
echo ""
echo -e "${YELLOW}Riavvia il Raspberry Pi per completare:${NC}"
echo -e "  ${GREEN}sudo reboot${NC}"
echo ""
