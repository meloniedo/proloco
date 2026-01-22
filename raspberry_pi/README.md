# Proloco Santa Bianca - Bar Manager
## Per Raspberry Pi 3A+ come Hotspot WiFi

### Cosa fa
- Il Raspberry Pi crea una **rete WiFi autonoma** (non ha bisogno di internet)
- Connettendoti alla rete WiFi con il telefono, puoi usare l'app
- L'app si installa come "webapp" a schermo intero sul telefono

---

## 🚀 Installazione

### Passo 1: Prepara la SD Card
1. Scarica e installa [Raspberry Pi Imager](https://www.raspberrypi.com/software/)
2. Scrivi **Raspberry Pi OS Lite (32-bit)** sulla SD card
3. Prima di espellere, crea un file vuoto chiamato `ssh` nella partizione boot

### Passo 2: Primo avvio e connessione
1. Inserisci la SD nel Raspberry Pi
2. Collegalo con cavo Ethernet al router (solo per installazione)
3. Accendi il Raspberry
4. Trova il suo IP (controlla sul router o usa `ping raspberrypi.local`)
5. Connettiti via SSH:
```bash
ssh pi@<IP_DEL_RASPBERRY>
# Password default: raspberry
```

### Passo 3: Trasferisci i file
Dal tuo PC, trasferisci la cartella:
```bash
scp -r ./raspberry_pi pi@<IP_DEL_RASPBERRY>:/home/pi/
```

### Passo 4: Esegui l'installazione
Sul Raspberry Pi (via SSH):
```bash
cd /home/pi/raspberry_pi
sudo chmod +x install.sh
sudo ./install.sh
```

### Passo 5: Riavvia
```bash
sudo reboot
```

---

## 📱 Come Usare

### Connessione
1. **Cerca la rete WiFi** sul telefono: `ProlocoBar`
2. **Password WiFi**: `proloco2024`
3. **Apri il browser** e vai su: `http://192.168.4.1`

### Installare come App (schermo intero)

**Su iPhone:**
1. Apri Safari e vai su `http://192.168.4.1`
2. Tocca l'icona "Condividi" (quadrato con freccia)
3. Scorri e tocca "Aggiungi a schermata Home"
4. Tocca "Aggiungi"

**Su Android:**
1. Apri Chrome e vai su `http://192.168.4.1`
2. Tocca i tre puntini in alto a destra
3. Tocca "Aggiungi a schermata Home"
4. Tocca "Aggiungi"

L'app si aprirà a **schermo intero** senza barre del browser!

---

## ⚙️ Configurazione

### Credenziali predefinite

| Elemento | Valore |
|----------|--------|
| Nome WiFi (SSID) | `ProlocoBar` |
| Password WiFi | `proloco2024` |
| IP Raspberry | `192.168.4.1` |
| User MySQL | `edo` |
| Password MySQL | `5054` |
| Password Reset App | `5054` |

### Modificare nome/password WiFi
Modifica il file `/etc/hostapd/hostapd.conf`:
```bash
sudo nano /etc/hostapd/hostapd.conf
```
Cambia `ssid=` e `wpa_passphrase=`, poi riavvia:
```bash
sudo reboot
```

---

## 💾 Backup e Ripristino

### Esportare i dati
1. Vai su **Storico** nell'app
2. Tocca **"Scarica Backup"**
3. Si scarica un file `.csv`

### Importare i dati
1. Vai su **Storico** nell'app
2. Tocca **"Importa Backup"**
3. Seleziona il file `.csv`

### Backup completo database
```bash
mysqldump -u edo -p5054 proloco_bar > backup_$(date +%Y%m%d).sql
```

### Ripristino completo database
```bash
mysql -u edo -p5054 proloco_bar < backup_file.sql
```

---

## 🔧 Comandi Utili

```bash
# Stato servizi
sudo systemctl status hostapd
sudo systemctl status dnsmasq
sudo systemctl status apache2

# Riavviare l'hotspot
sudo systemctl restart hostapd
sudo systemctl restart dnsmasq

# Vedere log errori
sudo tail -f /var/log/apache2/proloco_error.log

# Riavviare tutto
sudo reboot
```

---

## ❓ Problemi Comuni

### La rete WiFi non appare
```bash
sudo rfkill unblock wlan
sudo systemctl restart hostapd
```

### Non riesco a connettermi al 192.168.4.1
1. Verifica di essere connesso alla rete `ProlocoBar`
2. Disattiva i "dati mobili" sul telefono
3. Prova a ricaricare la pagina

### L'app non si carica
```bash
sudo systemctl restart apache2
sudo tail -20 /var/log/apache2/proloco_error.log
```

---

## 📂 Struttura File

```
/var/www/html/proloco/
├── index.html          # App principale (PWA)
├── manifest.json       # Manifest per installazione app
├── sw.js              # Service Worker
├── css/
│   └── style.css      # Stili
├── api/
│   ├── prodotti.php
│   ├── vendite.php
│   ├── spese.php
│   ├── statistiche.php
│   ├── export.php
│   ├── import.php
│   └── reset.php
├── includes/
│   └── config.php     # Config database
└── icons/
    └── *.png          # Icone app
```

---

## 🎯 Prossimi Passi
- Aggiungere pulsante Impostazioni
- Configurare invio email report
