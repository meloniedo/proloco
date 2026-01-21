# 🍺 BAR MANAGER - GUIDA INSTALLAZIONE RASPBERRY PI

## 📦 Contenuto della Cartella

```
bar_manager_raspberry/
├── config.py          # ⚙️ Configurazione (email, listino, ecc.)
├── server.py          # 🖥️ Server Python
├── index.html         # 📱 Interfaccia web
├── requirements.txt   # 📚 Dipendenze Python
├── install.sh         # 🔧 Script installazione automatica
├── setup_wifi_ap.sh   # 📶 Script configurazione WiFi Access Point
└── README.md          # 📖 Questa guida
```

---

## 🚀 INSTALLAZIONE PASSO-PASSO

### STEP 1: Copia i file sul Raspberry Pi

**Opzione A - Con chiavetta USB:**
1. Copia la cartella `bar_manager_raspberry` su una chiavetta USB
2. Inserisci la chiavetta nel Raspberry Pi
3. Apri il terminale e copia i file:
```bash
mkdir -p /home/pi/bar_manager
cp -r /media/pi/NOME_CHIAVETTA/bar_manager_raspberry/* /home/pi/bar_manager/
```

**Opzione B - Via rete (se hai accesso SSH):**
```bash
scp -r bar_manager_raspberry/* pi@IP_RASPBERRY:/home/pi/bar_manager/
```

### STEP 2: Installa il server

Apri il terminale sul Raspberry Pi e esegui:

```bash
cd /home/pi/bar_manager
sudo bash install.sh
```

Questo script:
- ✅ Aggiorna il sistema
- ✅ Installa Python e le dipendenze
- ✅ Configura l'avvio automatico del server
- ✅ Avvia il server

### STEP 3: Verifica che funzioni

Dopo l'installazione, vedrai l'indirizzo IP del Raspberry.
Da un altro dispositivo sulla stessa rete, apri il browser e vai a:

```
http://IP_DEL_RASPBERRY:8080
```

Esempio: `http://192.168.1.100:8080`

---

## 📶 CONFIGURAZIONE ACCESS POINT WiFi (Opzionale)

Se vuoi usare il Raspberry come router WiFi dedicato al bar:

```bash
cd /home/pi/bar_manager
sudo bash setup_wifi_ap.sh
sudo reboot
```

Dopo il riavvio:
- 📶 Il Raspberry creerà una rete WiFi: **BarManager_WiFi**
- 🔐 Password: **proloco2024**
- 📱 Connettiti e vai a: **http://192.168.4.1:8080**

**⚠️ Nota:** Con l'Access Point attivo, il Raspberry non avrà più internet via WiFi. Per inviare i report email, dovrai:
- Collegare un cavo Ethernet, oppure
- Attivare l'hotspot dal tuo telefono (il Raspberry userà quello per i report)

---

## 📧 COME FUNZIONA L'INVIO REPORT AUTOMATICO

1. Il Raspberry controlla ogni 30 secondi se c'è connessione internet
2. Quando rileva internet (es. quando attivi l'hotspot dal telefono):
   - Se sono passate almeno 24 ore dall'ultimo report
   - Genera automaticamente il report Excel
   - Lo invia via email agli indirizzi configurati
3. Il report contiene:
   - 📊 Settimana in corso (da Lunedì a oggi)
   - 📊 Settimana scorsa
   - 📊 Mese corrente
   - 📊 Totale dall'installazione

### Per forzare l'invio manuale:
1. Connettiti all'app dal browser
2. Vai su **📋 Storico**
3. Clicca **📧 Invia Report Ora**

---

## 📥 IMPORTARE I DATI DALLA VECCHIA APP

1. Apri la **vecchia app** sul telefono (quella che usavi prima)
2. Apri gli strumenti sviluppatore del browser:
   - Chrome Android: digita `chrome://inspect` in un altro tab
   - Oppure collega il telefono al PC e usa Chrome DevTools
3. Nella Console, incolla questo comando:
```javascript
copy(JSON.stringify({
    vendite: JSON.parse(localStorage.getItem('vendite')),
    spese: JSON.parse(localStorage.getItem('spese'))
}))
```
4. Apri la **nuova app** su `http://IP_RASPBERRY:8080`
5. Vai su **📋 Storico** → **📥 Importa Dati**
6. Incolla il JSON copiato
7. Clicca **Importa**

---

## ⚙️ PERSONALIZZAZIONI

### Modificare il listino prezzi
Modifica il file `config.py` nella sezione `listino`:

```python
"listino": [
    {"nome": "Caffè", "prezzo": 1.20, "categoria": "CAFFETTERIA", "icona": "☕"},
    # Aggiungi nuovi prodotti qui...
]
```

Dopo le modifiche, riavvia il server:
```bash
sudo systemctl restart barmanager
```

### Aggiungere email destinatari
Nel file `config.py`:

```python
"email_destinatari": [
    "alberto.melorec@gmail.com",
    "meloni.edo@gmail.com",
    "nuova.email@example.com"  # Aggiungi qui
]
```

### Cambiare la password di reset
```python
"password_reset": "nuova_password"
```

---

## 📁 DOVE SONO I DATI

I dati sono salvati in:
```
/home/pi/bar_manager/dati/storico_bar.xlsx
```

Questo file Excel contiene:
- **Foglio "Vendite"**: Tutte le vendite registrate
- **Foglio "Spese"**: Tutte le spese registrate
- **Foglio "Prodotti"**: Il listino prodotti

Puoi:
- ✅ Aprirlo con Excel/LibreOffice per visualizzare i dati
- ✅ Copiarlo su chiavetta USB per backup
- ✅ Modificarlo manualmente (con cautela!)

---

## 🔧 COMANDI UTILI

```bash
# Stato del server
sudo systemctl status barmanager

# Riavvia il server (dopo modifiche a config.py)
sudo systemctl restart barmanager

# Ferma il server
sudo systemctl stop barmanager

# Avvia il server
sudo systemctl start barmanager

# Vedi i log del server
sudo journalctl -u barmanager -f

# Controlla l'IP del Raspberry
hostname -I
```

---

## ❓ RISOLUZIONE PROBLEMI

### "Non riesco a connettermi all'app"
1. Verifica che il server sia attivo: `sudo systemctl status barmanager`
2. Verifica l'IP: `hostname -I`
3. Assicurati che telefono e Raspberry siano sulla stessa rete

### "Il report email non arriva"
1. Verifica la connessione internet del Raspberry
2. Controlla le credenziali email in `config.py`
3. Verifica che l'App Password Gmail sia corretta

### "Ho modificato config.py ma non cambia nulla"
Riavvia il server: `sudo systemctl restart barmanager`

### "Voglio cambiare la password WiFi dell'Access Point"
Modifica `/etc/hostapd/hostapd.conf` e cambia `wpa_passphrase`, poi:
```bash
sudo systemctl restart hostapd
```

---

## 📞 SUPPORTO

Per problemi o domande, controlla i log del server:
```bash
sudo journalctl -u barmanager -n 50
```

---

**Versione:** 2.0 Raspberry Pi Edition  
**Data:** Gennaio 2026
