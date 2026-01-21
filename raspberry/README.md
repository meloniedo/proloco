# 🍺 BAR MANAGER - GUIDA INSTALLAZIONE RASPBERRY PI

## 📦 Contenuto della Cartella

```
bar_manager_raspberry/
├── config.py          # ⚙️ Configurazione (email, password, WiFi, listino)
├── server.py          # 🖥️ Server Python
├── index.html         # 📱 Interfaccia web (design rustico)
├── usb_backup.py      # 💾 Script backup automatico USB
├── requirements.txt   # 📚 Dipendenze Python
├── install.sh         # 🔧 Script installazione automatica
├── setup_wifi_ap.sh   # 📶 Script configurazione WiFi Access Point
└── README.md          # 📖 Questa guida
```

---

## 🚀 INSTALLAZIONE PASSO-PASSO

### STEP 1: Copia i file sul Raspberry Pi

**Con chiavetta USB:**
1. Copia la cartella `bar_manager_raspberry` su una chiavetta USB
2. Inserisci la chiavetta nel Raspberry Pi
3. Apri il terminale e copia i file:
```bash
mkdir -p /home/pi/bar_manager
cp -r /media/pi/*/bar_manager_raspberry/* /home/pi/bar_manager/
```

### STEP 2: Installa il server

```bash
cd /home/pi/bar_manager
sudo bash install.sh
```

### STEP 3: Configura il WiFi Access Point

```bash
sudo bash setup_wifi_ap.sh
sudo reboot
```

### STEP 4: Connettiti e usa l'app

1. Dal telefono, connettiti al WiFi **BarManager_WiFi** (password: **proloco**)
2. Apri il browser e vai a: **http://192.168.4.1:8080**

---

## 📶 CONFIGURAZIONE WIFI

Le credenziali WiFi si modificano nel file `config.py`:

```python
"wifi_ssid": "BarManager_WiFi",      # Nome rete
"wifi_password": "proloco",       # Password (min 8 caratteri)
```

Dopo la modifica:
```bash
sudo bash setup_wifi_ap.sh
sudo reboot
```

---

## 📊 DOWNLOAD EXCEL CON PASSWORD

Nella sezione **Storico** c'è un campo password per scaricare i dati:
- Password predefinita: **5054**
- Modificabile in `config.py` → `"password_download": "5054"`

---

## 📥 REPORT SETTIMANALE AUTOMATICO

Quando ti connetti al WiFi del Raspberry:
- Se non hai scaricato il report questa settimana
- E ci sono vendite registrate
- **Appare un banner verde** con pulsante "Scarica Report"

Il report contiene:
- 📊 Settimana in corso (da Lunedì)
- 📊 Settimana scorsa
- 📊 Mese corrente
- 📊 Totale generale

---

## 💾 BACKUP AUTOMATICO SU USB

**Inserisci una chiavetta USB** → Il backup parte automaticamente!

Viene creata questa struttura:
```
/BarManager_Backup/
├── storico_completo.xlsx    # Tutti i dati grezzi
├── report_generale.xlsx     # Statistiche complete
├── ultimo_backup.txt        # Data ultimo backup
└── mensili/
    ├── 2026-01/
    │   └── report_gennaio_2026.xlsx
    ├── 2026-02/
    │   └── report_febbraio_2026.xlsx
    └── ...
```

**Consiglio:** Fai un backup USB almeno una volta a settimana!

---

## ⚙️ FILE DI CONFIGURAZIONE (config.py)

```python
CONFIG = {
    # Nome del bar
    "nome_bar": "Proloco Santa Bianca",
    
    # WiFi Access Point
    "wifi_ssid": "BarManager_WiFi",
    "wifi_password": "proloco",
    
    # Password per reset e download
    "password_reset": "5054",
    "password_download": "5054",
    
    # Listino prezzi (modificabile)
    "listino": [...],
    
    # Categorie spese
    "categorie_spese": [...]
}
```

Dopo ogni modifica:
```bash
sudo systemctl restart barmanager
```

---

## 📥 IMPORTARE DATI DALLA VECCHIA APP

1. Apri la vecchia app sul telefono
2. Apri la console browser (F12 → Console)
3. Esegui:
```javascript
copy(JSON.stringify({vendite: JSON.parse(localStorage.getItem('vendite')), spese: JSON.parse(localStorage.getItem('spese'))}))
```
4. Nella nuova app: **Storico → Importa Dati**
5. Incolla e clicca "Importa"

---

## 🔧 COMANDI UTILI

```bash
# Stato del server
sudo systemctl status barmanager

# Riavvia server (dopo modifiche config)
sudo systemctl restart barmanager

# Vedi log server
sudo journalctl -u barmanager -f

# Vedi log backup USB
cat /var/log/usb_backup.log

# Controlla IP
hostname -I
```

---

## ❓ RISOLUZIONE PROBLEMI

### "Non trovo la rete WiFi"
```bash
sudo systemctl status hostapd
sudo bash setup_wifi_ap.sh
sudo reboot
```

### "La pagina non si carica"
```bash
sudo systemctl restart barmanager
```

### "Il backup USB non funziona"
- Assicurati che la chiavetta sia formattata FAT32 o ext4
- Controlla i log: `cat /var/log/usb_backup.log`

---

## 🔌 AVVIO AUTOMATICO

Il sistema è configurato per:
- ✅ **Auto-login** senza monitor/tastiera
- ✅ **Avvio server** automatico al boot
- ✅ **Watchdog** - riavvio automatico se si blocca

Puoi staccare e riattaccare la corrente: tutto riparte da solo!

---

**Versione:** 2.0 Raspberry Pi Edition  
**Design:** Rustico (legno + verde biliardo)  
**Data:** Gennaio 2026
