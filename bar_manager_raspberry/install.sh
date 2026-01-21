#!/bin/bash

# ========================================
# INSTALLAZIONE BAR MANAGER
# Per Raspberry Pi
# ========================================

echo "╔══════════════════════════════════════════╗"
echo "║   🍺 INSTALLAZIONE BAR MANAGER 🍺        ║"
echo "╚══════════════════════════════════════════╝"
echo ""

# Colori
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verifica se è root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}❌ Esegui come root: sudo bash install.sh${NC}"
    exit 1
fi

# ==================== AVVIO AUTOMATICO RASPBERRY ====================
echo -e "${YELLOW}⚡ Configurazione avvio automatico Raspberry Pi...${NC}"

# Configura boot automatico quando riceve corrente (via config.txt)
if ! grep -q "# Bar Manager Auto Boot" /boot/config.txt 2>/dev/null && ! grep -q "# Bar Manager Auto Boot" /boot/firmware/config.txt 2>/dev/null; then
    # Prova prima /boot/firmware/config.txt (Raspberry Pi OS più recente)
    if [ -f /boot/firmware/config.txt ]; then
        CONFIG_FILE="/boot/firmware/config.txt"
    else
        CONFIG_FILE="/boot/config.txt"
    fi
    
    echo "" >> $CONFIG_FILE
    echo "# Bar Manager Auto Boot" >> $CONFIG_FILE
    echo "# Avvio automatico quando riceve corrente" >> $CONFIG_FILE
    echo "initial_turbo=30" >> $CONFIG_FILE
    
    echo -e "${GREEN}✅ Config boot aggiornato${NC}"
fi

# Disabilita splash screen e riduci tempo boot
if [ -f /boot/cmdline.txt ]; then
    CMDLINE_FILE="/boot/cmdline.txt"
elif [ -f /boot/firmware/cmdline.txt ]; then
    CMDLINE_FILE="/boot/firmware/cmdline.txt"
fi

if [ -n "$CMDLINE_FILE" ]; then
    # Aggiungi quiet e splash per boot più veloce
    if ! grep -q "quiet" $CMDLINE_FILE; then
        sed -i 's/$/ quiet splash/' $CMDLINE_FILE
        echo -e "${GREEN}✅ Boot velocizzato${NC}"
    fi
fi

echo -e "${YELLOW}📦 Aggiornamento sistema...${NC}"
apt update && apt upgrade -y

echo -e "${YELLOW}📦 Installazione Python e dipendenze...${NC}"
apt install -y python3 python3-pip python3-venv

echo -e "${YELLOW}📦 Creazione ambiente virtuale...${NC}"
cd /home/pi/bar_manager
python3 -m venv venv
source venv/bin/activate

echo -e "${YELLOW}📦 Installazione librerie Python...${NC}"
pip install --upgrade pip
pip install -r requirements.txt

echo -e "${YELLOW}⚙️ Creazione servizio systemd...${NC}"

cat > /etc/systemd/system/barmanager.service << 'EOF'
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

[Install]
WantedBy=multi-user.target
EOF

echo -e "${YELLOW}⚙️ Abilitazione servizio...${NC}"
systemctl daemon-reload
systemctl enable barmanager
systemctl start barmanager

echo -e "${YELLOW}🔥 Configurazione firewall...${NC}"
ufw allow 8080/tcp 2>/dev/null || true

# ==================== WATCHDOG - Riavvio automatico se si blocca ====================
echo -e "${YELLOW}🐕 Configurazione watchdog (riavvio automatico se si blocca)...${NC}"

# Abilita watchdog hardware
if ! grep -q "dtparam=watchdog=on" /boot/config.txt 2>/dev/null && ! grep -q "dtparam=watchdog=on" /boot/firmware/config.txt 2>/dev/null; then
    if [ -f /boot/firmware/config.txt ]; then
        echo "dtparam=watchdog=on" >> /boot/firmware/config.txt
    else
        echo "dtparam=watchdog=on" >> /boot/config.txt
    fi
fi

# Installa e configura watchdog
apt install -y watchdog

cat > /etc/watchdog.conf << 'WATCHDOGEOF'
watchdog-device = /dev/watchdog
watchdog-timeout = 15
max-load-1 = 24
WATCHDOGEOF

systemctl enable watchdog
systemctl start watchdog 2>/dev/null || true

# Ottieni IP
IP=$(hostname -I | awk '{print $1}')

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   ✅ INSTALLAZIONE COMPLETATA!           ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════╝${NC}"
echo ""
echo -e "📱 Accedi all'app da qualsiasi dispositivo:"
echo -e "   ${YELLOW}http://${IP}:8080${NC}"
echo ""
echo -e "🔧 Comandi utili:"
echo -e "   ${YELLOW}sudo systemctl status barmanager${NC}  - Stato server"
echo -e "   ${YELLOW}sudo systemctl restart barmanager${NC} - Riavvia server"
echo -e "   ${YELLOW}sudo systemctl stop barmanager${NC}    - Ferma server"
echo ""
echo -e "📁 File dati: /home/pi/bar_manager/dati/storico_bar.xlsx"
echo ""
