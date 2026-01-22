#!/bin/bash
# ========================================
# SCRIPT DI INSTALLAZIONE COMPLETA
# Proloco Santa Bianca - Bar Manager
# Per Raspberry Pi 3A+ come Hotspot WiFi
# VERSIONE 2.0 - Con Watchdog e fix NetworkManager
# ========================================

set -e

echo "================================================"
echo "  INSTALLAZIONE BAR MANAGER - PROLOCO"
echo "  Raspberry Pi 3A+ come Hotspot WiFi"
echo "  VERSIONE 2.0"
echo "================================================"
echo ""

# Colori per output
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

# Directory backup USB
USB_MOUNT="/media/usb_backup"
BACKUP_DIR="${USB_MOUNT}/proloco_backup"

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

# ==================== 2. RIMOZIONE NETWORK MANAGER ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 2: Rimozione NetworkManager${NC}"
echo -e "${CYAN}========================================${NC}"

# Ferma e disabilita NetworkManager (causa conflitti con hostapd)
echo -e "${YELLOW}Disabilitazione NetworkManager...${NC}"
systemctl stop NetworkManager 2>/dev/null || true
systemctl disable NetworkManager 2>/dev/null || true
systemctl mask NetworkManager 2>/dev/null || true

# Rimuovi anche wpa_supplicant per wlan0
systemctl stop wpa_supplicant 2>/dev/null || true
systemctl disable wpa_supplicant 2>/dev/null || true

# Ferma qualsiasi processo che usa wlan0
killall wpa_supplicant 2>/dev/null || true

echo -e "${GREEN}✓ NetworkManager disabilitato${NC}"

# ==================== 3. INSTALLAZIONE PACCHETTI ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 3: Installazione Pacchetti${NC}"
echo -e "${CYAN}========================================${NC}"

echo -e "${YELLOW}Installazione Apache, PHP, MySQL...${NC}"
apt install -y apache2 php php-mysql php-json mariadb-server mariadb-client

echo -e "${YELLOW}Installazione software Hotspot...${NC}"
# hostapd e dnsmasq senza NetworkManager
apt install -y hostapd dnsmasq

# Pacchetti per gestione USB
apt install -y usbmount ntfs-3g exfat-fuse exfat-utils

# Watchdog hardware (se disponibile)
apt install -y watchdog

# Ferma i servizi per la configurazione
systemctl stop hostapd 2>/dev/null || true
systemctl stop dnsmasq 2>/dev/null || true
systemctl unmask hostapd 2>/dev/null || true

echo -e "${GREEN}✓ Pacchetti installati${NC}"

# ==================== 4. CONFIGURAZIONE IP STATICO ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 4: Configurazione IP Statico${NC}"
echo -e "${CYAN}========================================${NC}"

# Backup configurazioni
cp /etc/dhcpcd.conf /etc/dhcpcd.conf.backup 2>/dev/null || true

# Rimuovi vecchie configurazioni wlan0
sed -i '/interface wlan0/,/^$/d' /etc/dhcpcd.conf
sed -i '/# Configurazione Hotspot/,/nohook wpa_supplicant/d' /etc/dhcpcd.conf

# Aggiungi configurazione IP statico
cat >> /etc/dhcpcd.conf << EOF

# Configurazione Hotspot Proloco - NON MODIFICARE
interface wlan0
    static ip_address=${IP_ADDRESS}/24
    nohook wpa_supplicant
EOF

echo -e "${GREEN}✓ IP statico: ${IP_ADDRESS}${NC}"

# ==================== 5. CONFIGURAZIONE HOSTAPD ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 5: Configurazione Hotspot WiFi${NC}"
echo -e "${CYAN}========================================${NC}"

# Crea configurazione hostapd
cat > /etc/hostapd/hostapd.conf << EOF
# Configurazione Hotspot Proloco Santa Bianca
# NON MODIFICARE - Generato automaticamente

interface=wlan0
driver=nl80211

# Rete
ssid=${WIFI_SSID}
hw_mode=g
channel=${WIFI_CHANNEL}
ieee80211n=1
wmm_enabled=0

# Sicurezza
macaddr_acl=0
auth_algs=1
ignore_broadcast_ssid=0
wpa=2
wpa_passphrase=${WIFI_PASSWORD}
wpa_key_mgmt=WPA-PSK
wpa_pairwise=TKIP
rsn_pairwise=CCMP

# Performance
country_code=IT
EOF

# Configura file default
echo 'DAEMON_CONF="/etc/hostapd/hostapd.conf"' > /etc/default/hostapd

echo -e "${GREEN}✓ Hotspot: ${WIFI_SSID} / ${WIFI_PASSWORD}${NC}"

# ==================== 6. CONFIGURAZIONE DNSMASQ ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 6: Configurazione DHCP Server${NC}"
echo -e "${CYAN}========================================${NC}"

# Backup e ricrea configurazione
mv /etc/dnsmasq.conf /etc/dnsmasq.conf.backup 2>/dev/null || true

cat > /etc/dnsmasq.conf << EOF
# Configurazione DHCP per Hotspot Proloco
# NON MODIFICARE - Generato automaticamente

interface=wlan0
bind-interfaces
server=8.8.8.8

# Range DHCP
dhcp-range=${DHCP_RANGE_START},${DHCP_RANGE_END},${NETMASK},24h

# DNS locale
domain=local
address=/proloco.local/${IP_ADDRESS}
address=/bar.local/${IP_ADDRESS}

# Captive portal - redirect tutto al Raspberry
address=/#/${IP_ADDRESS}
EOF

echo -e "${GREEN}✓ DHCP configurato${NC}"

# ==================== 7. CONFIGURAZIONE USB AUTO-MOUNT ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 7: Configurazione Auto-Mount USB${NC}"
echo -e "${CYAN}========================================${NC}"

# Crea directory mount
mkdir -p ${USB_MOUNT}
chmod 777 ${USB_MOUNT}

# Configura usbmount per FAT32/NTFS/exFAT
cat > /etc/usbmount/usbmount.conf << 'EOF'
ENABLED=1
MOUNTPOINTS="/media/usb_backup /media/usb1 /media/usb2 /media/usb3"
FILESYSTEMS="vfat ntfs fuseblk ext2 ext3 ext4 exfat"
MOUNTOPTIONS="sync,noexec,nodev,noatime,nodiratime,uid=33,gid=33,umask=000"
FS_MOUNTOPTIONS=""
VERBOSE=no
EOF

# Crea regola udev per auto-mount USB
cat > /etc/udev/rules.d/99-usb-backup.rules << EOF
# Auto-mount chiavette USB per backup
ACTION=="add", KERNEL=="sd[a-z][0-9]", SUBSYSTEM=="block", RUN+="/usr/local/bin/usb-mount.sh add %k"
ACTION=="remove", KERNEL=="sd[a-z][0-9]", SUBSYSTEM=="block", RUN+="/usr/local/bin/usb-mount.sh remove %k"
EOF

# Script mount/unmount USB
cat > /usr/local/bin/usb-mount.sh << 'EOFUSB'
#!/bin/bash
ACTION=$1
DEVICE=$2
MOUNT_POINT="/media/usb_backup"

case "$ACTION" in
    add)
        mkdir -p $MOUNT_POINT
        mount /dev/$DEVICE $MOUNT_POINT -o uid=33,gid=33,umask=000 2>/dev/null || \
        mount /dev/$DEVICE $MOUNT_POINT 2>/dev/null
        chmod 777 $MOUNT_POINT
        ;;
    remove)
        umount $MOUNT_POINT 2>/dev/null
        ;;
esac
EOFUSB

chmod +x /usr/local/bin/usb-mount.sh

# Ricarica regole udev
udevadm control --reload-rules

echo -e "${GREEN}✓ Auto-mount USB configurato${NC}"

# ==================== 8. CONFIGURAZIONE MYSQL ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 8: Configurazione Database${NC}"
echo -e "${CYAN}========================================${NC}"

systemctl start mariadb
systemctl enable mariadb

mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo -e "${GREEN}✓ Database: ${DB_NAME}${NC}"

# ==================== 9. INSTALLAZIONE APP WEB ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 9: Installazione App Web${NC}"
echo -e "${CYAN}========================================${NC}"

mkdir -p ${WEB_DIR}/{api,css,includes,icons}

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cp -r ${SCRIPT_DIR}/* ${WEB_DIR}/ 2>/dev/null || true
rm -f ${WEB_DIR}/install.sh ${WEB_DIR}/generate_icons.php

# Importa schema database
mysql ${DB_NAME} < ${WEB_DIR}/database.sql 2>/dev/null || true

# Permessi
chown -R www-data:www-data ${WEB_DIR}
chmod -R 755 ${WEB_DIR}

echo -e "${GREEN}✓ App web installata${NC}"

# ==================== 10. CONFIGURAZIONE APACHE ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 10: Configurazione Web Server${NC}"
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

echo -e "${GREEN}✓ Web server configurato${NC}"

# ==================== 11. WATCHDOG E PROTEZIONE CORRENTE ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 11: Watchdog e Protezione${NC}"
echo -e "${CYAN}========================================${NC}"

# Configura watchdog hardware (se disponibile)
cat > /etc/watchdog.conf << EOF
# Watchdog configuration
watchdog-device = /dev/watchdog
watchdog-timeout = 15
interval = 5
max-load-1 = 24
min-memory = 1
EOF

# Abilita watchdog
systemctl enable watchdog 2>/dev/null || true

# ==================== SCRIPT WATCHDOG SERVIZI ====================
cat > /usr/local/bin/proloco-watchdog.sh << 'EOFWATCH'
#!/bin/bash
# Watchdog per servizi Proloco Bar Manager
# Controlla e riavvia servizi se non funzionano

LOG="/var/log/proloco-watchdog.log"

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> $LOG
}

check_service() {
    SERVICE=$1
    if ! systemctl is-active --quiet $SERVICE; then
        log "ERRORE: $SERVICE non attivo, riavvio..."
        systemctl restart $SERVICE
        sleep 3
        if systemctl is-active --quiet $SERVICE; then
            log "OK: $SERVICE riavviato con successo"
        else
            log "CRITICO: $SERVICE non si riavvia!"
        fi
    fi
}

# Sblocca WiFi se bloccato
rfkill unblock wlan 2>/dev/null

# Controlla servizi critici
check_service "hostapd"
check_service "dnsmasq"
check_service "apache2"
check_service "mariadb"

# Verifica che wlan0 abbia IP corretto
CURRENT_IP=$(ip -4 addr show wlan0 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}')
if [ "$CURRENT_IP" != "192.168.4.1" ]; then
    log "ERRORE: IP wlan0 errato ($CURRENT_IP), riconfigurazione..."
    ip addr flush dev wlan0
    ip addr add 192.168.4.1/24 dev wlan0
    ip link set wlan0 up
    systemctl restart hostapd
    systemctl restart dnsmasq
fi

# Mantieni log piccolo (ultime 1000 righe)
tail -1000 $LOG > ${LOG}.tmp && mv ${LOG}.tmp $LOG 2>/dev/null
EOFWATCH

chmod +x /usr/local/bin/proloco-watchdog.sh

# ==================== CRON PER WATCHDOG ====================
# Esegue ogni minuto
cat > /etc/cron.d/proloco-watchdog << EOF
# Watchdog Proloco - controlla servizi ogni minuto
* * * * * root /usr/local/bin/proloco-watchdog.sh
EOF

echo -e "${GREEN}✓ Watchdog configurato (controlla ogni minuto)${NC}"

# ==================== 12. SCRIPT AVVIO AL BOOT ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 12: Configurazione Avvio Boot${NC}"
echo -e "${CYAN}========================================${NC}"

# Script principale di avvio
cat > /usr/local/bin/proloco-startup.sh << 'EOFSTART'
#!/bin/bash
# Script avvio Proloco Bar Manager
# Eseguito al boot del sistema

LOG="/var/log/proloco-startup.log"
echo "$(date '+%Y-%m-%d %H:%M:%S') - Avvio Proloco Bar Manager" >> $LOG

# Attendi che il sistema sia pronto
sleep 10

# Sblocca WiFi
rfkill unblock wlan
sleep 2

# Forza IP statico su wlan0
ip addr flush dev wlan0 2>/dev/null
ip addr add 192.168.4.1/24 dev wlan0
ip link set wlan0 up
sleep 2

# Riavvia servizi in ordine
systemctl restart dhcpcd
sleep 3

systemctl restart hostapd
sleep 3

systemctl restart dnsmasq
sleep 2

systemctl restart apache2
sleep 2

systemctl restart mariadb

echo "$(date '+%Y-%m-%d %H:%M:%S') - Avvio completato" >> $LOG
EOFSTART

chmod +x /usr/local/bin/proloco-startup.sh

# Servizio systemd per avvio
cat > /etc/systemd/system/proloco.service << EOF
[Unit]
Description=Proloco Bar Manager Startup
After=network.target
Wants=network.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/proloco-startup.sh
RemainAfterExit=yes
TimeoutStartSec=120

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable proloco.service

# Anche in rc.local come backup
cat > /etc/rc.local << 'EOF'
#!/bin/bash
# Avvio backup - eseguito se systemd fallisce
sleep 15
/usr/local/bin/proloco-startup.sh &
exit 0
EOF
chmod +x /etc/rc.local

echo -e "${GREEN}✓ Avvio automatico configurato${NC}"

# ==================== 13. ABILITA SERVIZI ====================
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  FASE 13: Abilitazione Servizi${NC}"
echo -e "${CYAN}========================================${NC}"

systemctl unmask hostapd
systemctl enable hostapd
systemctl enable dnsmasq
systemctl enable apache2
systemctl enable mariadb

# Avvia tutto ora
rfkill unblock wlan
sleep 2

systemctl restart dhcpcd
sleep 3

systemctl start hostapd
systemctl start dnsmasq

echo -e "${GREEN}✓ Servizi abilitati e avviati${NC}"

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
echo -e "${CYAN}PROTEZIONI ATTIVE:${NC}"
echo -e "  ✓ Watchdog controlla servizi ogni minuto"
echo -e "  ✓ Riavvio automatico servizi se cadono"
echo -e "  ✓ Avvio automatico dopo perdita corrente"
echo -e "  ✓ Auto-mount chiavette USB per backup"
echo ""
echo -e "${CYAN}BACKUP USB:${NC}"
echo -e "  Inserisci una chiavetta USB e usa il"
echo -e "  pulsante 'Backup su USB' nelle Impostazioni"
echo ""
echo -e "${YELLOW}>>> RIAVVIA ORA: sudo reboot${NC}"
echo ""
