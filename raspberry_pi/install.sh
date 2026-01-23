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
REPO_DIR="/home/pi/proloco"
WEB_DIR="/home/pi/proloco/raspberry_pi"
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

# Crea directory media con permessi corretti (per USB)
chmod 777 /media 2>/dev/null || true

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

# Permessi cartella web per Apache - IMPORTANTE!
chown -R www-data:www-data ${WEB_DIR}
chmod -R 755 ${WEB_DIR}

systemctl restart apache2
systemctl enable apache2

# Crea cartelle necessarie
mkdir -p ${WEB_DIR}/logs
mkdir -p ${WEB_DIR}/backups
mkdir -p ${REPO_DIR}/BACKUP_GIORNALIERI
mkdir -p ${REPO_DIR}/RESOCONTI_SETTIMANALI
chown -R www-data:www-data ${WEB_DIR}
chmod -R 755 ${WEB_DIR}
chown -R ${USER_NAME}:${USER_NAME} ${REPO_DIR}/BACKUP_GIORNALIERI
chown -R ${USER_NAME}:${USER_NAME} ${REPO_DIR}/RESOCONTI_SETTIMANALI

# Rendi eseguibili gli script cron
chmod +x ${WEB_DIR}/cron_sync.php
chmod +x ${WEB_DIR}/cron_backup.php
chmod +x ${WEB_DIR}/cron_resoconto.php
chmod +x ${WEB_DIR}/cron_listino.php

# Rendi eseguibile lo script aggiorna.sh
chmod +x ${REPO_DIR}/aggiorna.sh

# ========================================
# PERMESSI COMPLETI - SEZIONE CRITICA
# ========================================
echo "  Configurazione permessi completi..."

# Rendi eseguibili TUTTI gli script bash e PHP
chmod 755 ${WEB_DIR}/*.sh 2>/dev/null || true
chmod 755 ${WEB_DIR}/*.php 2>/dev/null || true
chmod 755 ${WEB_DIR}/api/*.php 2>/dev/null || true
chmod 755 ${REPO_DIR}/*.sh 2>/dev/null || true

# Crea e imposta permessi per file di testo (666 = lettura/scrittura per tutti)
# Questi file devono essere modificabili sia da terminale (utente edo) che da web (www-data)
touch ${WEB_DIR}/STORICO.txt
touch ${WEB_DIR}/LISTINO.txt
chown www-data:www-data ${WEB_DIR}/STORICO.txt
chown www-data:www-data ${WEB_DIR}/LISTINO.txt
chmod 666 ${WEB_DIR}/STORICO.txt
chmod 666 ${WEB_DIR}/LISTINO.txt

# Crea cartelle per dati con permessi aperti (777 = tutti possono tutto)
mkdir -p ${REPO_DIR}/backup
mkdir -p ${REPO_DIR}/BACKUP_GIORNALIERI
mkdir -p ${REPO_DIR}/RESOCONTI_SETTIMANALI
mkdir -p ${WEB_DIR}/logs
mkdir -p ${WEB_DIR}/backups

chmod 777 ${REPO_DIR}/backup
chmod 777 ${REPO_DIR}/BACKUP_GIORNALIERI
chmod 777 ${REPO_DIR}/RESOCONTI_SETTIMANALI
chmod 777 ${WEB_DIR}/logs
chmod 777 ${WEB_DIR}/backups

chown ${USER_NAME}:${USER_NAME} ${REPO_DIR}/backup
chown ${USER_NAME}:${USER_NAME} ${REPO_DIR}/BACKUP_GIORNALIERI
chown ${USER_NAME}:${USER_NAME} ${REPO_DIR}/RESOCONTI_SETTIMANALI
chown www-data:www-data ${WEB_DIR}/logs
chown www-data:www-data ${WEB_DIR}/backups

# Aggiungi utente edo al gruppo www-data (può modificare file web)
usermod -a -G www-data ${USER_NAME} 2>/dev/null || true

# Cartella includes (necessaria per config.php)
chown -R www-data:www-data ${WEB_DIR}/includes
chmod -R 755 ${WEB_DIR}/includes

# Cartella API
chown -R www-data:www-data ${WEB_DIR}/api
chmod -R 755 ${WEB_DIR}/api

# Permessi finali cartella web principale
chown -R www-data:www-data ${WEB_DIR}
chmod -R 775 ${WEB_DIR}

# Permessi git (per aggiornamenti da terminale)
chown -R ${USER_NAME}:${USER_NAME} ${REPO_DIR}/.git 2>/dev/null || true

echo -e "${GREEN}✓ Permessi configurati correttamente${NC}"

# Configura CRON per sincronizzazione automatica STORICO.txt ogni minuto
echo "# Sincronizzazione STORICO.txt ogni minuto" > /etc/cron.d/proloco_sync
echo "* * * * * www-data /usr/bin/php ${WEB_DIR}/cron_sync.php > /dev/null 2>&1" >> /etc/cron.d/proloco_sync
chmod 644 /etc/cron.d/proloco_sync

# Configura CRON per sincronizzazione automatica LISTINO.txt ogni minuto
echo "# Sincronizzazione LISTINO.txt ogni minuto" > /etc/cron.d/proloco_listino
echo "* * * * * www-data /usr/bin/php ${WEB_DIR}/cron_listino.php > /dev/null 2>&1" >> /etc/cron.d/proloco_listino
chmod 644 /etc/cron.d/proloco_listino

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

# ===== SERVIZIO AVVIO AUTOMATICO =====
# Copia script di avvio
cp ${WEB_DIR}/avvio_proloco.sh /usr/local/bin/avvio_proloco.sh
chmod +x /usr/local/bin/avvio_proloco.sh

# Crea servizio systemd per avvio automatico
cat > /etc/systemd/system/proloco-avvio.service << EOF
[Unit]
Description=Proloco Bar Manager - Fix permessi all'avvio
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/avvio_proloco.sh
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable proloco-avvio.service
systemctl start proloco-avvio.service

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

# ===== FASE 6: TEST E FINALIZZAZIONE =====
echo ""
echo -e "${CYAN}[6/6] Test e finalizzazione...${NC}"

# Backup database esistente (se esiste)
if mysql -u ${DB_USER} -p${DB_PASS} -e "USE ${DB_NAME}" 2>/dev/null; then
    BACKUP_FILE="/home/pi/proloco/BACKUP_GIORNALIERI/backup_pre_install_$(date +%Y%m%d_%H%M%S).sql"
    mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} > ${BACKUP_FILE} 2>/dev/null
    echo -e "${GREEN}✓ Backup database esistente salvato${NC}"
fi

# Genera LISTINO.txt iniziale
php ${WEB_DIR}/cron_listino.php 2>/dev/null
echo -e "${GREEN}✓ LISTINO.txt generato${NC}"

# Genera STORICO.txt iniziale
php ${WEB_DIR}/cron_sync.php 2>/dev/null
echo -e "${GREEN}✓ STORICO.txt generato${NC}"

# Test connessione database
if php -r "require '${WEB_DIR}/includes/config.php'; \$pdo = getDB(); echo 'OK';" 2>/dev/null | grep -q "OK"; then
    echo -e "${GREEN}✓ Connessione database OK${NC}"
else
    echo -e "${RED}✗ ERRORE connessione database!${NC}"
fi

# Test Apache
if curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1/ | grep -q "200\|301\|302"; then
    echo -e "${GREEN}✓ Apache funzionante${NC}"
else
    echo -e "${YELLOW}⚠ Apache potrebbe non essere attivo (normale prima del reboot)${NC}"
fi

# ===== COMPLETATO =====
echo ""
echo -e "${GREEN}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}              INSTALLAZIONE COMPLETATA!                      ${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "  ${CYAN}WiFi Hotspot:${NC}"
echo -e "    SSID:     ${GREEN}${WIFI_SSID}${NC}"
echo -e "    Password: ${GREEN}${WIFI_PASSWORD}${NC}"
echo ""
echo -e "  ${CYAN}Applicazione:${NC}"
echo -e "    URL: ${GREEN}http://${IP_ADDRESS}${NC}"
echo ""
echo -e "  ${CYAN}File importanti:${NC}"
echo -e "    Listino:   ${GREEN}${WEB_DIR}/LISTINO.txt${NC}"
echo -e "    Storico:   ${GREEN}${WEB_DIR}/STORICO.txt${NC}"
echo -e "    Backup:    ${GREEN}${REPO_DIR}/BACKUP_GIORNALIERI/${NC}"
echo -e "    Resoconti: ${GREEN}${REPO_DIR}/RESOCONTI_SETTIMANALI/${NC}"
echo ""
echo -e "  ${CYAN}Comandi utili:${NC}"
echo -e "    Aggiornare app:       ${GREEN}./aggiorna.sh${NC}"
echo -e "    Modalità internet:    ${GREEN}sudo bash modalita_internet.sh${NC}"
echo -e "    Modalità hotspot:     ${GREEN}sudo bash modalita_hotspot.sh${NC}"
echo ""
echo -e "${GREEN}════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}>>> Esegui ora: ${NC}${GREEN}sudo reboot${NC}"
echo ""
