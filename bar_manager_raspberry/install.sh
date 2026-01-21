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
