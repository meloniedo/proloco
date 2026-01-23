#!/bin/bash
# ========================================
# ATTIVA MODALITÀ HOTSPOT (Bar)
# Usa questo per attivare la rete del bar
# ========================================

echo "📶 Attivazione modalità Hotspot Bar..."

# Configurazione
IP_ADDRESS="192.168.4.1"

# Ferma NetworkManager
sudo systemctl stop NetworkManager 2>/dev/null
sudo systemctl stop wpa_supplicant 2>/dev/null

# NON mascherare, solo disabilitare temporaneamente
sudo systemctl disable NetworkManager 2>/dev/null
sudo systemctl disable wpa_supplicant 2>/dev/null

# Configura IP statico
sudo ip addr flush dev wlan0
sudo ip link set wlan0 down
sudo ip link set wlan0 up
sudo ip addr add ${IP_ADDRESS}/24 dev wlan0

# Avvia hotspot
sudo systemctl unmask hostapd 2>/dev/null
sudo systemctl unmask dnsmasq 2>/dev/null
sudo systemctl start dnsmasq
sudo systemctl start hostapd

echo ""
echo "✅ Hotspot attivato!"
echo ""
echo "Rete WiFi: ProlocoBar"
echo "Password: proloco2024"
echo "Indirizzo app: http://192.168.4.1"
