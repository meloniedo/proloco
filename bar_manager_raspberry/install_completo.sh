#!/bin/bash

# ========================================
# INSTALLAZIONE COMPLETA BAR MANAGER
# Un solo comando per installare tutto!
# ========================================
#
# Uso:
#   curl -sSL https://raw.githubusercontent.com/meloniedo/proloco/main/raspberry/install_completo.sh | sudo bash
#
# ========================================

set -e

# Colori
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

echo ""
echo -e "${CYAN}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║     🍺 INSTALLAZIONE BAR MANAGER - RASPBERRY PI 🍺   ║${NC}"
echo -e "${CYAN}║              Proloco Santa Bianca                     ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════════╝${NC}"
echo ""

# Verifica root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}❌ Esegui come root: sudo bash install_completo.sh${NC}"
    exit 1
fi

# Directory di installazione
INSTALL_DIR="/home/pi/bar_manager"
GITHUB_RAW="https://raw.githubusercontent.com/meloniedo/proloco/main/raspberry"

echo -e "${YELLOW}📁 Creazione directory...${NC}"
mkdir -p $INSTALL_DIR
cd $INSTALL_DIR

echo -e "${YELLOW}📥 Download file da GitHub...${NC}"

# Download tutti i file
files=(
    "config.py"
    "server.py"
    "index.html"
    "usb_backup.py"
    "requirements.txt"
    "setup_wifi_ap.sh"
)

for file in "${files[@]}"; do
    echo -e "   Downloading ${file}..."
    curl -sSL "${GITHUB_RAW}/${file}" -o "${INSTALL_DIR}/${file}"
done

echo -e "${GREEN}✅ File scaricati${NC}"

# ==================== AUTO-LOGIN ====================
echo -e "${YELLOW}🔓 Configurazione auto-login...${NC}"

raspi-config nonint do_boot_behaviour B2 2>/dev/null || {
    mkdir -p /etc/systemd/system/getty@tty1.service.d/
    cat > /etc/systemd/system/getty@tty1.service.d/autologin.conf << 'AUTOLOGINEOF'
[Service]
ExecStart=
ExecStart=-/sbin/agetty --autologin pi --noclear %I $TERM
AUTOLOGINEOF
    systemctl set-default multi-user.target 2>/dev/null || true
}

echo -e "${GREEN}✅ Auto-login configurato${NC}"

# ==================== BOOT CONFIG ====================
echo -e "${YELLOW}⚡ Configurazione boot automatico...${NC}"

if [ -f /boot/firmware/config.txt ]; then
    CONFIG_FILE="/boot/firmware/config.txt"
else
    CONFIG_FILE="/boot/config.txt"
fi

if ! grep -q "# Bar Manager Auto Boot" $CONFIG_FILE 2>/dev/null; then
    echo "" >> $CONFIG_FILE
    echo "# Bar Manager Auto Boot" >> $CONFIG_FILE
    echo "initial_turbo=30" >> $CONFIG_FILE
    echo "dtparam=watchdog=on" >> $CONFIG_FILE
fi

echo -e "${GREEN}✅ Boot configurato${NC}"

# ==================== PACCHETTI SISTEMA ====================
echo -e "${YELLOW}📦 Aggiornamento sistema (può richiedere qualche minuto)...${NC}"
apt update -qq
apt install -y -qq python3 python3-pip python3-venv watchdog hostapd dnsmasq

echo -e "${GREEN}✅ Pacchetti installati${NC}"

# ==================== AMBIENTE PYTHON ====================
echo -e "${YELLOW}🐍 Configurazione Python...${NC}"

cd $INSTALL_DIR
python3 -m venv venv
source venv/bin/activate
pip install --upgrade pip -q
pip install -r requirements.txt -q

echo -e "${GREEN}✅ Ambiente Python pronto${NC}"

# ==================== SERVIZIO SYSTEMD ====================
echo -e "${YELLOW}⚙️ Creazione servizio...${NC}"

cat > /etc/systemd/system/barmanager.service << 'SERVICEEOF'
[Unit]
Description=Bar Manager Server
After=network.target

[Service]
Type=simple
User=pi
WorkingDirectory=/home/pi/bar_manager
ExecStart=/home/pi/bar_manager/venv/bin/python server.py
Restart=always
RestartSec=5
Environment=PYTHONUNBUFFERED=1

[Install]
WantedBy=multi-user.target
SERVICEEOF

systemctl daemon-reload
systemctl enable barmanager
systemctl start barmanager

echo -e "${GREEN}✅ Servizio avviato${NC}"

# ==================== BACKUP USB ====================
echo -e "${YELLOW}💾 Configurazione backup USB...${NC}"

chmod +x $INSTALL_DIR/usb_backup.py

cat > /etc/udev/rules.d/99-usb-backup.rules << 'UDEVEOF'
ACTION=="add", SUBSYSTEM=="block", KERNEL=="sd[a-z][0-9]", RUN+="/bin/bash -c '/usr/bin/python3 /home/pi/bar_manager/usb_backup.py >> /var/log/usb_backup.log 2>&1 &'"
UDEVEOF

udevadm control --reload-rules
udevadm trigger

echo -e "${GREEN}✅ Backup USB configurato${NC}"

# ==================== WATCHDOG ====================
echo -e "${YELLOW}🐕 Configurazione watchdog...${NC}"

cat > /etc/watchdog.conf << 'WATCHDOGEOF'
watchdog-device = /dev/watchdog
watchdog-timeout = 15
max-load-1 = 24
WATCHDOGEOF

systemctl enable watchdog
systemctl start watchdog 2>/dev/null || true

echo -e "${GREEN}✅ Watchdog attivo${NC}"

# ==================== WIFI ACCESS POINT ====================
echo -e "${YELLOW}📶 Configurazione WiFi Access Point...${NC}"

# Leggi configurazione
source $INSTALL_DIR/venv/bin/activate
WIFI_SSID=$(python3 -c "exec(open('$INSTALL_DIR/config.py').read()); print(CONFIG.get('wifi_ssid', 'BarManager_WiFi'))" 2>/dev/null || echo "BarManager_WiFi")
WIFI_PASSWORD=$(python3 -c "exec(open('$INSTALL_DIR/config.py').read()); print(CONFIG.get('wifi_password', 'proloco2024'))" 2>/dev/null || echo "proloco2024")

IP_ADDRESS="192.168.4.1"

# Configura dhcpcd
cp /etc/dhcpcd.conf /etc/dhcpcd.conf.backup 2>/dev/null || true
sed -i '/# Configurazione Access Point/,/nohook wpa_supplicant/d' /etc/dhcpcd.conf 2>/dev/null || true

cat >> /etc/dhcpcd.conf << EOF

# Configurazione Access Point
interface wlan0
    static ip_address=${IP_ADDRESS}/24
    nohook wpa_supplicant
EOF

# Configura dnsmasq
mv /etc/dnsmasq.conf /etc/dnsmasq.conf.backup 2>/dev/null || true
cat > /etc/dnsmasq.conf << EOF
interface=wlan0
dhcp-range=192.168.4.2,192.168.4.20,255.255.255.0,24h
domain=local
address=/bar.local/${IP_ADDRESS}
EOF

# Configura hostapd
cat > /etc/hostapd/hostapd.conf << EOF
country_code=IT
interface=wlan0
ssid=${WIFI_SSID}
hw_mode=g
channel=7
macaddr_acl=0
auth_algs=1
ignore_broadcast_ssid=0
wpa=2
wpa_passphrase=${WIFI_PASSWORD}
wpa_key_mgmt=WPA-PSK
wpa_pairwise=TKIP
rsn_pairwise=CCMP
EOF

sed -i 's|#DAEMON_CONF=""|DAEMON_CONF="/etc/hostapd/hostapd.conf"|' /etc/default/hostapd 2>/dev/null || true

systemctl unmask hostapd
systemctl enable hostapd
systemctl enable dnsmasq

echo -e "${GREEN}✅ WiFi Access Point configurato${NC}"

# ==================== PERMESSI ====================
echo -e "${YELLOW}🔐 Configurazione permessi...${NC}"
chown -R pi:pi $INSTALL_DIR

# ==================== COMPLETATO ====================
IP=$(hostname -I | awk '{print $1}')

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║         ✅ INSTALLAZIONE COMPLETATA! ✅              ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "📶 ${YELLOW}Rete WiFi che verrà creata:${NC}"
echo -e "   Nome (SSID): ${CYAN}${WIFI_SSID}${NC}"
echo -e "   Password:    ${CYAN}${WIFI_PASSWORD}${NC}"
echo ""
echo -e "📱 ${YELLOW}Dopo il riavvio:${NC}"
echo -e "   1. Connetti il telefono a '${WIFI_SSID}'"
echo -e "   2. Apri il browser: ${CYAN}http://192.168.4.1:8080${NC}"
echo ""
echo -e "💾 ${YELLOW}Backup:${NC} Inserisci una chiavetta USB per backup automatico"
echo ""
echo -e "🔐 ${YELLOW}Password app:${NC} 5054 (modificabile dalle impostazioni)"
echo ""
echo -e "${YELLOW}⚠️  IMPORTANTE: Riavvia il Raspberry per attivare tutto:${NC}"
echo -e "   ${CYAN}sudo reboot${NC}"
echo ""
