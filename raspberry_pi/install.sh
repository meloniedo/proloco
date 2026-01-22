#!/bin/bash
# ========================================
# SCRIPT DI INSTALLAZIONE COMPLETA
# Proloco Santa Bianca - Bar Manager
# Per Raspberry Pi 3A+ come Hotspot WiFi
# VERSIONE 3.0 - ORDINE CORRETTO
# ========================================

set -e

echo "================================================"
echo "  INSTALLAZIONE BAR MANAGER - PROLOCO"
echo "  Raspberry Pi 3A+ come Hotspot WiFi"
echo "  VERSIONE 3.0"
echo "================================================"
echo ""

# Colori
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# ==================== CONFIGURAZIONE ====================
WIFI_SSID="ProlocoBar"
WIFI_PASSWORD="proloco2024"
WIFI_CHANNEL="7"
IP_ADDRESS="192.168.4.1"
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

# ==================== FASE 1: AGGIORNAMENTO ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 1/8: Aggiornamento Sistema${NC}"
echo -e "${CYAN}========================================${NC}"
apt update
apt upgrade -y

# ==================== FASE 2: INSTALLAZIONE PACCHETTI ====================
# IMPORTANTE: Installare TUTTO prima di toccare la rete!
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 2/8: Installazione Pacchetti${NC}"
echo -e "${CYAN}  (Questo richiede qualche minuto...)${NC}"
echo -e "${CYAN}========================================${NC}"

echo -e "${YELLOW}Installazione web server e database...${NC}"
apt install -y apache2 php php-mysql php-json mariadb-server mariadb-client

echo -e "${YELLOW}Installazione software hotspot...${NC}"
apt install -y hostapd dnsmasq

echo -e "${YELLOW}Installazione utilità...${NC}"
apt install -y wireless-tools rfkill

echo -e "${GREEN}✓ Tutti i pacchetti installati${NC}"

# ==================== FASE 3: CONFIGURAZIONE DATABASE ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 3/8: Configurazione Database${NC}"
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

# ==================== FASE 4: INSTALLAZIONE APP WEB ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 4/8: Installazione App Web${NC}"
echo -e "${CYAN}========================================${NC}"

# Crea directory
mkdir -p ${WEB_DIR}/{api,css,includes,icons}

# Copia file
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cp -r ${SCRIPT_DIR}/* ${WEB_DIR}/ 2>/dev/null || true
rm -f ${WEB_DIR}/install.sh ${WEB_DIR}/GUIDA_INSTALLAZIONE.md

# Importa schema database
if [ -f "${WEB_DIR}/database.sql" ]; then
    mysql ${DB_NAME} < ${WEB_DIR}/database.sql 2>/dev/null || echo "Database già popolato"
fi

# Permessi
chown -R www-data:www-data ${WEB_DIR}
chmod -R 755 ${WEB_DIR}

echo -e "${GREEN}✓ App web installata in ${WEB_DIR}${NC}"

# ==================== FASE 5: CONFIGURAZIONE APACHE ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 5/8: Configurazione Web Server${NC}"
echo -e "${CYAN}========================================${NC}"

cat > /etc/apache2/sites-available/proloco.conf << EOF
<VirtualHost *:80>
    ServerName proloco.local
    ServerAlias bar.local ${IP_ADDRESS} *
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

a2dissite 000-default.conf 2>/dev/null || true
a2ensite proloco.conf
a2enmod rewrite
systemctl restart apache2
systemctl enable apache2

echo -e "${GREEN}✓ Apache configurato${NC}"

# ==================== FASE 6: FERMA SERVIZI RETE ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 6/8: Preparazione Hotspot${NC}"
echo -e "${CYAN}========================================${NC}"

echo -e "${YELLOW}Fermo servizi di rete...${NC}"

# Ferma hostapd e dnsmasq per configurarli
systemctl stop hostapd 2>/dev/null || true
systemctl stop dnsmasq 2>/dev/null || true

# Sblocca hostapd
systemctl unmask hostapd 2>/dev/null || true

# Ferma wpa_supplicant per wlan0
systemctl stop wpa_supplicant 2>/dev/null || true
killall wpa_supplicant 2>/dev/null || true

# Sblocca WiFi
rfkill unblock wlan 2>/dev/null || true

echo -e "${GREEN}✓ Servizi rete fermati${NC}"

# ==================== FASE 7: CONFIGURAZIONE HOTSPOT ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 7/8: Configurazione Hotspot${NC}"
echo -e "${CYAN}========================================${NC}"

# Backup dhcpcd.conf
cp /etc/dhcpcd.conf /etc/dhcpcd.conf.backup 2>/dev/null || true

# Rimuovi vecchie configurazioni wlan0
sed -i '/interface wlan0/,/nohook wpa_supplicant/d' /etc/dhcpcd.conf 2>/dev/null || true
sed -i '/# Configurazione Hotspot/d' /etc/dhcpcd.conf 2>/dev/null || true

# Aggiungi IP statico per wlan0
cat >> /etc/dhcpcd.conf << EOF

# Configurazione Hotspot Proloco
interface wlan0
    static ip_address=${IP_ADDRESS}/24
    nohook wpa_supplicant
EOF

echo -e "${GREEN}✓ IP statico: ${IP_ADDRESS}${NC}"

# Configura hostapd
cat > /etc/hostapd/hostapd.conf << EOF
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
country_code=IT
EOF

# Imposta file default hostapd
echo 'DAEMON_CONF="/etc/hostapd/hostapd.conf"' > /etc/default/hostapd

echo -e "${GREEN}✓ Hostapd configurato: ${WIFI_SSID}${NC}"

# Configura dnsmasq
mv /etc/dnsmasq.conf /etc/dnsmasq.conf.backup 2>/dev/null || true

cat > /etc/dnsmasq.conf << EOF
interface=wlan0
bind-interfaces
dhcp-range=${DHCP_RANGE_START},${DHCP_RANGE_END},${NETMASK},24h
domain=local
address=/proloco.local/${IP_ADDRESS}
address=/bar.local/${IP_ADDRESS}
EOF

echo -e "${GREEN}✓ DHCP configurato${NC}"

# ==================== FASE 8: DISABILITA NETWORK MANAGER ====================
# IMPORTANTE: Questo va fatto ALLA FINE!
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 8/8: Finalizzazione${NC}"
echo -e "${CYAN}========================================${NC}"

# Disabilita NetworkManager (se presente)
if systemctl is-active --quiet NetworkManager 2>/dev/null; then
    echo -e "${YELLOW}Disabilito NetworkManager...${NC}"
    systemctl stop NetworkManager
    systemctl disable NetworkManager
    systemctl mask NetworkManager
fi

# Disabilita wpa_supplicant per wlan0
systemctl disable wpa_supplicant 2>/dev/null || true

# Abilita servizi hotspot
systemctl enable hostapd
systemctl enable dnsmasq

echo -e "${GREEN}✓ NetworkManager disabilitato${NC}"

# ==================== SCRIPT AVVIO AUTOMATICO ====================
echo -e "${YELLOW}Configuro avvio automatico...${NC}"

# Script di avvio
cat > /usr/local/bin/proloco-startup.sh << 'EOFSTART'
#!/bin/bash
# Avvio Proloco Bar Manager
sleep 5
rfkill unblock wlan
ip link set wlan0 up
sleep 2
systemctl restart dhcpcd
sleep 3
systemctl restart hostapd
systemctl restart dnsmasq
systemctl restart apache2
EOFSTART

chmod +x /usr/local/bin/proloco-startup.sh

# Servizio systemd
cat > /etc/systemd/system/proloco.service << EOF
[Unit]
Description=Proloco Bar Manager
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/proloco-startup.sh
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable proloco.service

# Watchdog ogni minuto
cat > /usr/local/bin/proloco-watchdog.sh << 'EOFWATCH'
#!/bin/bash
# Controlla e riavvia servizi se necessario
for SERVICE in hostapd dnsmasq apache2 mariadb; do
    if ! systemctl is-active --quiet $SERVICE; then
        systemctl restart $SERVICE
    fi
done
rfkill unblock wlan 2>/dev/null
EOFWATCH

chmod +x /usr/local/bin/proloco-watchdog.sh

# Cron per watchdog
echo "* * * * * root /usr/local/bin/proloco-watchdog.sh" > /etc/cron.d/proloco-watchdog

echo -e "${GREEN}✓ Avvio automatico configurato${NC}"

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
echo ""
echo -e "${CYAN}CREDENZIALI:${NC}"
echo -e "  Database: ${DB_USER} / ${DB_PASS}"
echo -e "  Password Reset App: 5054"
echo ""
echo -e "${YELLOW}>>> RIAVVIA ORA: sudo reboot${NC}"
echo ""
