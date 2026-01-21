#!/bin/bash

# ========================================
# CONFIGURAZIONE RASPBERRY PI COME
# ACCESS POINT WiFi + SERVER BAR
# ========================================

echo "╔══════════════════════════════════════════╗"
echo "║   📶 CONFIGURAZIONE ACCESS POINT WiFi    ║"
echo "╚══════════════════════════════════════════╝"
echo ""

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}❌ Esegui come root: sudo bash setup_wifi_ap.sh${NC}"
    exit 1
fi

# Configurazione
WIFI_SSID="BarManager_WiFi"
WIFI_PASSWORD="proloco2024"
IP_ADDRESS="192.168.4.1"

echo -e "${YELLOW}📦 Installazione hostapd e dnsmasq...${NC}"
apt install -y hostapd dnsmasq

echo -e "${YELLOW}⚙️ Fermata servizi...${NC}"
systemctl stop hostapd
systemctl stop dnsmasq

echo -e "${YELLOW}⚙️ Configurazione IP statico per wlan0...${NC}"

# Backup e modifica dhcpcd.conf
cp /etc/dhcpcd.conf /etc/dhcpcd.conf.backup

cat >> /etc/dhcpcd.conf << EOF

# Configurazione Access Point
interface wlan0
    static ip_address=${IP_ADDRESS}/24
    nohook wpa_supplicant
EOF

echo -e "${YELLOW}⚙️ Configurazione dnsmasq (DHCP)...${NC}"

mv /etc/dnsmasq.conf /etc/dnsmasq.conf.backup

cat > /etc/dnsmasq.conf << EOF
interface=wlan0
dhcp-range=192.168.4.2,192.168.4.20,255.255.255.0,24h
domain=local
address=/bar.local/${IP_ADDRESS}
EOF

echo -e "${YELLOW}⚙️ Configurazione hostapd (Access Point)...${NC}"

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

# Configura hostapd default
sed -i 's|#DAEMON_CONF=""|DAEMON_CONF="/etc/hostapd/hostapd.conf"|' /etc/default/hostapd

echo -e "${YELLOW}⚙️ Abilitazione servizi...${NC}"
systemctl unmask hostapd
systemctl enable hostapd
systemctl enable dnsmasq

echo -e "${YELLOW}🔄 Riavvio servizi...${NC}"
systemctl restart dhcpcd
sleep 2
systemctl start dnsmasq
systemctl start hostapd

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   ✅ ACCESS POINT CONFIGURATO!           ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════╝${NC}"
echo ""
echo -e "📶 Rete WiFi creata:"
echo -e "   Nome (SSID): ${YELLOW}${WIFI_SSID}${NC}"
echo -e "   Password:    ${YELLOW}${WIFI_PASSWORD}${NC}"
echo ""
echo -e "📱 Per connetterti:"
echo -e "   1. Connetti il telefono alla rete '${WIFI_SSID}'"
echo -e "   2. Apri il browser e vai a:"
echo -e "      ${YELLOW}http://${IP_ADDRESS}:8080${NC}"
echo -e "      oppure ${YELLOW}http://bar.local:8080${NC}"
echo ""
echo -e "${YELLOW}⚠️  Dopo questa configurazione, il Raspberry NON avrà${NC}"
echo -e "${YELLOW}    più accesso a internet via WiFi.${NC}"
echo -e "${YELLOW}    Per i report email, collega un cavo Ethernet${NC}"
echo -e "${YELLOW}    o usa il tuo hotspot mobile.${NC}"
echo ""
echo -e "🔄 Riavvia il Raspberry per completare:"
echo -e "   ${YELLOW}sudo reboot${NC}"
echo ""
