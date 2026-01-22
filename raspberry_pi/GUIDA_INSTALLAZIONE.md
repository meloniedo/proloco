# GUIDA: Reset Raspberry Pi e Installazione da Terminale

## PREREQUISITI
- Raspberry Pi 3A+ collegato alla stessa rete WiFi del tuo PC
- IP del Raspberry: `192.168.1.18` (o quello che hai)
- Il tuo PC con terminale (Mac/Linux) o PowerShell/WSL (Windows)

---

## PARTE 1: COLLEGAMENTO AL RASPBERRY

### 1.1 Abilita SSH (se non già fatto)
Se hai accesso fisico al Raspberry con monitor:
```bash
sudo systemctl enable ssh
sudo systemctl start ssh
```

Oppure crea un file vuoto `ssh` nella partizione boot della SD card.

### 1.2 Connettiti dal tuo PC
```bash
ssh pi@192.168.1.18
```
Password default: `raspberry`

**Se non funziona**, prova:
```bash
ssh -o StrictHostKeyChecking=no pi@192.168.1.18
```

---

## PARTE 2: RESET COMPLETO (Impostazioni di Fabbrica)

### Opzione A: Reset Software (Mantiene il sistema, cancella configurazioni)
```bash
# Dal tuo terminale, connesso via SSH al Raspberry:

# Ferma tutti i servizi
sudo systemctl stop hostapd dnsmasq apache2 mariadb

# Rimuovi configurazioni hotspot
sudo rm -f /etc/hostapd/hostapd.conf
sudo rm -f /etc/dnsmasq.conf
sudo mv /etc/dhcpcd.conf.backup /etc/dhcpcd.conf 2>/dev/null

# Rimuovi app web
sudo rm -rf /var/www/html/proloco

# Rimuovi database
sudo mysql -e "DROP DATABASE IF EXISTS proloco_bar;"

# Rimuovi script avvio
sudo rm -f /usr/local/bin/proloco-*.sh
sudo rm -f /etc/systemd/system/proloco*.service
sudo rm -f /etc/cron.d/proloco-watchdog

# Riabilita NetworkManager (se vuoi WiFi normale)
sudo systemctl unmask NetworkManager
sudo systemctl enable NetworkManager

# Riavvia
sudo reboot
```

### Opzione B: Reset Completo SD Card (Reinstallazione pulita)
**Dal tuo PC (NON dal Raspberry):**

1. Scarica Raspberry Pi Imager: https://www.raspberrypi.com/software/
2. Inserisci la SD card nel PC
3. Seleziona: **Raspberry Pi OS Lite (32-bit)**
4. Clicca la rotella ⚙️ e configura:
   - Abilita SSH
   - Imposta username: `pi`
   - Imposta password: `raspberry` (o altra)
   - Configura WiFi (SSID e password della tua rete di casa)
5. Scrivi sulla SD
6. Inserisci nel Raspberry e accendi

---

## PARTE 3: INSTALLAZIONE COMPLETA DA TERMINALE

### 3.1 Trasferisci i file dal tuo PC al Raspberry
**Dal terminale del TUO PC:**
```bash
# Vai nella cartella dove hai i file
cd /percorso/alla/cartella/raspberry_pi

# Copia tutto sul Raspberry
scp -r . pi@192.168.1.18:/home/pi/proloco_app/
```

Se i file sono qui su Emergent, prima scaricali:
1. Usa "Download Code" nel menu
2. Estrai lo zip
3. Vai nella cartella `raspberry_pi`
4. Esegui il comando scp sopra

### 3.2 Connettiti al Raspberry ed esegui installazione
```bash
# Dal tuo PC
ssh pi@192.168.1.18

# Ora sei NEL Raspberry
cd /home/pi/proloco_app

# Rendi eseguibile lo script
chmod +x install.sh

# Esegui installazione (ci vorranno 5-10 minuti)
sudo ./install.sh

# Al termine, riavvia
sudo reboot
```

### 3.3 Verifica che funzioni
Dopo il riavvio (aspetta 1-2 minuti):

1. **Cerca la rete WiFi** sul telefono: `ProlocoBar`
2. **Password**: `proloco2024`
3. **Apri browser**: `http://192.168.4.1`

---

## PARTE 4: COMANDI UTILI DA TERMINALE

### Controllo stato servizi
```bash
ssh pi@192.168.1.18 "sudo systemctl status hostapd"
ssh pi@192.168.1.18 "sudo systemctl status apache2"
ssh pi@192.168.1.18 "sudo systemctl status mariadb"
```

### Riavvio servizi
```bash
ssh pi@192.168.1.18 "sudo systemctl restart hostapd dnsmasq apache2"
```

### Vedere log errori
```bash
ssh pi@192.168.1.18 "sudo tail -50 /var/log/apache2/proloco_error.log"
ssh pi@192.168.1.18 "sudo tail -50 /var/log/proloco-watchdog.log"
```

### Backup database manuale
```bash
ssh pi@192.168.1.18 "mysqldump -u edo -p5054 proloco_bar" > backup_$(date +%Y%m%d).sql
```

### Riavvio completo Raspberry
```bash
ssh pi@192.168.1.18 "sudo reboot"
```

### Spegnimento Raspberry
```bash
ssh pi@192.168.1.18 "sudo shutdown -h now"
```

---

## RISOLUZIONE PROBLEMI

### "Connection refused" quando fai SSH
Il Raspberry potrebbe non essere raggiungibile. Verifica:
```bash
ping 192.168.1.18
```
Se non risponde, controlla che sia acceso e sulla stessa rete.

### La rete WiFi "ProlocoBar" non appare
```bash
ssh pi@192.168.1.18 "sudo rfkill unblock wlan && sudo systemctl restart hostapd"
```

### L'app non si carica
```bash
ssh pi@192.168.1.18 "sudo systemctl restart apache2"
ssh pi@192.168.1.18 "sudo tail -20 /var/log/apache2/proloco_error.log"
```

### Errore database
```bash
ssh pi@192.168.1.18 "sudo systemctl restart mariadb"
ssh pi@192.168.1.18 "mysql -u edo -p5054 -e 'SHOW DATABASES;'"
```

---

## SCHEMA RIASSUNTIVO

```
TUO PC (Terminale)                    RASPBERRY PI
      |                                    |
      |-- ssh pi@192.168.1.18 ----------->| Connessione
      |                                    |
      |-- scp -r ./files pi@IP:/path ---->| Trasferimento file
      |                                    |
      |-- ssh ... "sudo ./install.sh" --->| Installazione
      |                                    |
      |                                    | (Dopo reboot)
      |                                    |
      |<-------- WiFi "ProlocoBar" -------| Hotspot attivo
      |                                    |
TELEFONO                                   |
      |-- Connetti a ProlocoBar           |
      |-- http://192.168.4.1 ------------>| App funzionante!
```
