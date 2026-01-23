#!/bin/bash
# ========================================
# ATTIVA MODALITÀ INTERNET (NetworkManager)
# Usa questo per connetterti al WiFi di casa
# ========================================

echo "🌐 Attivazione modalità Internet..."

# Ferma hotspot
sudo systemctl stop hostapd 2>/dev/null
sudo systemctl stop dnsmasq 2>/dev/null

# SBLOCCA e attiva NetworkManager
sudo systemctl unmask NetworkManager
sudo systemctl unmask wpa_supplicant
sudo systemctl enable NetworkManager
sudo systemctl enable wpa_supplicant

# Resetta interfaccia WiFi
sudo ip addr flush dev wlan0
sudo ip link set wlan0 down
sudo iwconfig wlan0 mode managed 2>/dev/null
sudo ip link set wlan0 up

# Avvia servizi
sudo systemctl start wpa_supplicant
sudo systemctl start NetworkManager

echo ""
echo "✅ NetworkManager attivato!"
echo ""
echo "Ora puoi:"
echo "  - Usare 'nmcli device wifi list' per vedere le reti"
echo "  - Usare 'nmcli device wifi connect NOME_RETE password PASSWORD' per connetterti"
echo ""
echo "Oppure riavvia con 'sudo reboot' e usa l'interfaccia grafica"
