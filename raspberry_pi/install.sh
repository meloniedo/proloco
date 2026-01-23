#!/bin/bash
# ========================================
# INSTALLAZIONE PROLOCO BAR MANAGER
# Raspberry Pi 3A+ - Hotspot WiFi
# VERSIONE 4.0 - TUTTO AUTOMATICO
# ========================================

echo "================================================"
echo "  PROLOCO BAR MANAGER - INSTALLAZIONE"
echo "================================================"

# Colori
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configurazione
WIFI_SSID="ProlocoBar"
WIFI_PASSWORD="proloco2024"
IP_ADDRESS="192.168.4.1"
DB_USER="edo"
DB_PASS="5054"
DB_NAME="proloco_bar"
WEB_DIR="/home/pi/proloco"
USER_NAME="edo"

# Verifica root
if [ "$EUID" -ne 0 ]; then 
    echo "Errore: esegui con sudo"
    exit 1
fi

# ===== FASE 1: INSTALLAZIONE PACCHETTI =====
echo ""
echo -e "${CYAN}[1/5] Installazione pacchetti...${NC}"
apt update
apt install -y apache2 php php-mysql php-json php-zip php-xml php-mbstring mariadb-server mariadb-client hostapd dnsmasq
echo -e "${GREEN}✓ Pacchetti installati${NC}"

# ===== FASE 2: DATABASE =====
echo ""
echo -e "${CYAN}[2/5] Configurazione database...${NC}"
systemctl start mariadb
systemctl enable mariadb
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Pulisci duplicati prodotti PRIMA di inserire nuovi dati
echo "  Pulizia duplicati prodotti..."
mysql ${DB_NAME} -e "
-- Elimina prodotti duplicati mantenendo solo il primo per ogni nome
DELETE p1 FROM prodotti p1
INNER JOIN prodotti p2 
WHERE p1.id > p2.id AND p1.nome = p2.nome;
" 2>/dev/null || true

# Aggiungi constraint UNIQUE se non esiste
mysql ${DB_NAME} -e "
ALTER TABLE prodotti ADD UNIQUE INDEX unique_nome (nome);
" 2>/dev/null || true

echo -e "${GREEN}✓ Database OK${NC}"

# ===== FASE 3: APP WEB =====
echo ""
echo -e "${CYAN}[3/5] Installazione app web...${NC}"
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
mkdir -p ${WEB_DIR}/{api,css,includes,icons}
cp -r ${SCRIPT_DIR}/* ${WEB_DIR}/ 2>/dev/null || true
rm -f ${WEB_DIR}/install.sh
[ -f "${WEB_DIR}/database.sql" ] && mysql ${DB_NAME} < ${WEB_DIR}/database.sql 2>/dev/null || true
chown -R www-data:www-data ${WEB_DIR}
chmod -R 755 ${WEB_DIR}

# Aggiungi www-data al gruppo per accesso USB
usermod -a -G plugdev www-data 2>/dev/null || true
usermod -a -G disk www-data 2>/dev/null || true

# Installa udisks2 per montaggio automatico USB
apt install -y udisks2 2>/dev/null || true

# Crea regola udev per montare USB con permessi corretti
cat > /etc/udev/rules.d/99-usb-mount.rules << 'UDEVRULE'
# Auto-mount USB con permessi per www-data
ACTION=="add", KERNEL=="sd[a-z][0-9]", TAG+="systemd", ENV{SYSTEMD_WANTS}="usb-mount@%k.service"
UDEVRULE

# Crea servizio systemd per mount USB
cat > /etc/systemd/system/usb-mount@.service << 'USBSERVICE'
[Unit]
Description=Mount USB Drive %i
[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/bin/bash -c 'mkdir -p /media/usb_%i && mount -o uid=33,gid=33,umask=000 /dev/%i /media/usb_%i'
ExecStop=/bin/bash -c 'umount /media/usb_%i && rmdir /media/usb_%i'
[Install]
WantedBy=multi-user.target
USBSERVICE

# Ricarica udev
udevadm control --reload-rules 2>/dev/null || true

# Crea directory media con permessi corretti
mkdir -p /media/${USER_NAME}
chmod 777 /media/${USER_NAME}
chown www-data:www-data /media/${USER_NAME} 2>/dev/null || true

# Apache
cat > /etc/apache2/sites-available/proloco.conf << EOF
<VirtualHost *:80>
    DocumentRoot ${WEB_DIR}
    <Directory ${WEB_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF
a2dissite 000-default.conf 2>/dev/null || true
a2ensite proloco.conf
a2enmod rewrite

# Permessi cartella web per Apache
chown -R ${USER_NAME}:${USER_NAME} ${WEB_DIR}
chmod -R 755 ${WEB_DIR}

systemctl restart apache2
systemctl enable apache2

# Crea cartelle necessarie
mkdir -p ${WEB_DIR}/logs
mkdir -p ${WEB_DIR}/backups
mkdir -p /home/${USER_NAME}/proloco/BACKUP_GIORNALIERI
mkdir -p /home/${USER_NAME}/proloco/RESOCONTI_SETTIMANALI
chown -R www-data:www-data ${WEB_DIR}/logs
chown -R www-data:www-data ${WEB_DIR}/backups
chown -R ${USER_NAME}:${USER_NAME} /home/${USER_NAME}/proloco/BACKUP_GIORNALIERI
chown -R ${USER_NAME}:${USER_NAME} /home/${USER_NAME}/proloco/RESOCONTI_SETTIMANALI

# Rendi eseguibili gli script cron
chmod +x ${WEB_DIR}/cron_sync.php
chmod +x ${WEB_DIR}/cron_backup.php
chmod +x ${WEB_DIR}/cron_resoconto.php

# Configura CRON per sincronizzazione automatica STORICO.txt ogni minuto
echo "# Sincronizzazione STORICO.txt ogni minuto" > /etc/cron.d/proloco_sync
echo "* * * * * www-data /usr/bin/php ${WEB_DIR}/cron_sync.php > /dev/null 2>&1" >> /etc/cron.d/proloco_sync
chmod 644 /etc/cron.d/proloco_sync

# Configura CRON per backup automatico (controlla ogni minuto se è l'ora programmata)
echo "# Backup automatico programmato" > /etc/cron.d/proloco_backup
echo "* * * * * www-data /usr/bin/php ${WEB_DIR}/cron_backup.php > /dev/null 2>&1" >> /etc/cron.d/proloco_backup
chmod 644 /etc/cron.d/proloco_backup

# Configura CRON per resoconto settimanale (ogni lunedì alle 08:00)
echo "# Resoconto settimanale automatico" > /etc/cron.d/proloco_resoconto
echo "0 8 * * 1 www-data /usr/bin/php ${WEB_DIR}/cron_resoconto.php > /dev/null 2>&1" >> /etc/cron.d/proloco_resoconto
chmod 644 /etc/cron.d/proloco_resoconto

# Configura sudo per permettere a www-data di cambiare l'ora del sistema
echo "# Permetti a www-data di cambiare data/ora" > /etc/sudoers.d/proloco-time
echo "www-data ALL=(ALL) NOPASSWD: /bin/date" >> /etc/sudoers.d/proloco-time
echo "www-data ALL=(ALL) NOPASSWD: /sbin/hwclock" >> /etc/sudoers.d/proloco-time
chmod 440 /etc/sudoers.d/proloco-time

systemctl restart cron


echo -e "${GREEN}✓ App web OK${NC}"
echo -e "${GREEN}✓ Cron sync automatico attivo (ogni minuto)${NC}"

# ===== FASE 4: CONFIGURAZIONE HOTSPOT =====
echo ""
echo -e "${CYAN}[4/5] Configurazione hotspot...${NC}"

# Ferma tutto
systemctl stop hostapd 2>/dev/null || true
systemctl stop dnsmasq 2>/dev/null || true
systemctl stop wpa_supplicant 2>/dev/null || true
systemctl stop dhcpcd 2>/dev/null || true
killall wpa_supplicant 2>/dev/null || true
rfkill unblock wlan 2>/dev/null || true
systemctl unmask hostapd 2>/dev/null || true

# Disabilita servizi che interferiscono (MA NON MASCHERARLI!)
sudo systemctl disable wpa_supplicant 2>/dev/null
# NON usare mask - rende impossibile riattivare NetworkManager!
if systemctl is-enabled NetworkManager 2>/dev/null; then
    systemctl stop NetworkManager
    systemctl disable NetworkManager
    # RIMOSSO: systemctl mask NetworkManager - causava blocco permanente
fi

# Configura IP statico - METODO DIRETTO (più affidabile)
cat > /etc/network/interfaces.d/wlan0 << EOF
auto wlan0
iface wlan0 inet static
    address ${IP_ADDRESS}
    netmask 255.255.255.0
EOF

# Anche in dhcpcd come backup
cat > /etc/dhcpcd.conf << EOF
hostname
clientid
persistent
option rapid_commit
option domain_name_servers, domain_name, domain_search, host_name
option classless_static_routes
option interface_mtu
require dhcp_server_identifier
slaac private
nohook wpa_supplicant

interface wlan0
    static ip_address=${IP_ADDRESS}/24
    nohook wpa_supplicant
EOF

# Hostapd
cat > /etc/hostapd/hostapd.conf << EOF
interface=wlan0
driver=nl80211
ssid=${WIFI_SSID}
hw_mode=g
channel=7
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
echo 'DAEMON_CONF="/etc/hostapd/hostapd.conf"' > /etc/default/hostapd

# Dnsmasq
cat > /etc/dnsmasq.conf << EOF
interface=wlan0
bind-interfaces
dhcp-range=192.168.4.10,192.168.4.50,255.255.255.0,24h
domain=local
address=/proloco.local/${IP_ADDRESS}
EOF

echo -e "${GREEN}✓ Hotspot configurato${NC}"

# ===== FASE 5: SCRIPT AVVIO AUTOMATICO =====
echo ""
echo -e "${CYAN}[5/5] Configurazione avvio automatico...${NC}"

# Script di avvio ROBUSTO
cat > /usr/local/bin/proloco-start.sh << 'STARTSCRIPT'
#!/bin/bash
# Avvio Proloco - eseguito al boot

sleep 3

# Sblocca WiFi
rfkill unblock wlan

# Ferma processi interferenti
killall wpa_supplicant 2>/dev/null

# Configura IP manualmente (SEMPRE)
ip link set wlan0 down
ip addr flush dev wlan0
ip addr add 192.168.4.1/24 dev wlan0
ip link set wlan0 up

sleep 2

# Avvia hotspot
systemctl restart hostapd
sleep 2
systemctl restart dnsmasq

# Log
echo "$(date): Proloco avviato" >> /var/log/proloco.log
STARTSCRIPT

chmod +x /usr/local/bin/proloco-start.sh

# Servizio systemd
cat > /etc/systemd/system/proloco.service << EOF
[Unit]
Description=Proloco Hotspot
After=network.target
Wants=network.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/proloco-start.sh
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

# Abilita servizi
systemctl daemon-reload
systemctl enable proloco.service
systemctl enable hostapd
systemctl enable dnsmasq

# Anche in rc.local come ulteriore backup
cat > /etc/rc.local << 'EOF'
#!/bin/bash
sleep 10
/usr/local/bin/proloco-start.sh &
exit 0
EOF
chmod +x /etc/rc.local

echo -e "${GREEN}✓ Avvio automatico OK${NC}"

# ===== COMPLETATO =====
echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}  INSTALLAZIONE COMPLETATA!${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo -e "WiFi: ${GREEN}${WIFI_SSID}${NC} / ${GREEN}${WIFI_PASSWORD}${NC}"
echo -e "App:  ${GREEN}http://${IP_ADDRESS}${NC}"
echo ""
echo -e "${YELLOW}Esegui ora: sudo reboot${NC}"
echo ""
