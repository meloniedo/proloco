# Proloco Santa Bianca - Bar Manager
## Per Raspberry Pi 3A+

### Requisiti
- Raspberry Pi 3A+ con Raspberry Pi OS
- Connessione WiFi locale
- Accesso SSH (opzionale ma consigliato)

### Installazione Rapida

1. **Abilita SSH sul Raspberry Pi** (se non già fatto):
   - Crea un file vuoto chiamato `ssh` nella partizione boot della SD card
   - Oppure: `sudo systemctl enable ssh && sudo systemctl start ssh`

2. **Connettiti al Raspberry Pi dal tuo PC**:
```bash
ssh pi@192.168.1.18
```
Password predefinita: `raspberry` (cambiala dopo il primo accesso!)

3. **Scarica i file dell'app** (dal tuo PC, trasferisci la cartella raspberry_pi):
```bash
scp -r ./raspberry_pi pi@192.168.1.18:/home/pi/
```

4. **Esegui lo script di installazione** (sul Raspberry Pi):
```bash
cd /home/pi/raspberry_pi
sudo chmod +x install.sh
sudo ./install.sh
```

5. **Accedi all'app** dal browser:
   - `http://192.168.1.18/proloco/`

---

### Struttura File

```
raspberry_pi/
├── index.html          # App principale (HTML + JavaScript)
├── css/
│   └── style.css       # Stili (locale, no CDN)
├── api/
│   ├── prodotti.php    # API prodotti
│   ├── vendite.php     # API vendite  
│   ├── spese.php       # API spese
│   ├── statistiche.php # API statistiche
│   ├── export.php      # Export CSV/Excel
│   ├── import.php      # Import backup
│   └── reset.php       # Reset periodo
├── includes/
│   └── config.php      # Configurazione DB
├── database.sql        # Schema database MySQL
├── install.sh          # Script installazione automatica
└── README.md           # Questa guida
```

---

### Sistema di Backup

#### Export (Scarica backup)
1. Vai su **Storico**
2. Clicca **"Scarica Backup CSV/Excel"**
3. Si scarica un file `.csv` con tutte vendite e spese

#### Import (Ripristina backup)  
1. Vai su **Storico**
2. Clicca **"Importa Dati da Backup"**
3. Seleziona il file `.csv` precedentemente salvato
4. I dati vengono aggiunti al database

**Nota**: L'import aggiunge i dati, non li sostituisce. Per un ripristino completo, prima resetta i dati.

---

### Credenziali

| Elemento | Valore |
|----------|--------|
| User MySQL | `edo` |
| Password MySQL | `5054` |
| Database | `proloco_bar` |
| Password Reset App | `5054` |

---

### Comandi Utili

```bash
# Riavvia Apache
sudo systemctl restart apache2

# Vedi log errori
sudo tail -f /var/log/apache2/proloco_error.log

# Accedi a MySQL
mysql -u edo -p5054 proloco_bar

# Backup database completo
mysqldump -u edo -p5054 proloco_bar > backup_$(date +%Y%m%d).sql

# Ripristina database
mysql -u edo -p5054 proloco_bar < backup_file.sql
```

---

### Personalizzazione

Modifica `includes/config.php` per:
- Cambiare credenziali database
- Aggiungere categorie spese

Modifica il database direttamente per:
- Aggiungere/modificare prodotti
- Cambiare prezzi

---

### Troubleshooting

**L'app non si apre?**
```bash
sudo systemctl status apache2
sudo tail -20 /var/log/apache2/error.log
```

**Errore database?**
```bash
mysql -u edo -p5054 -e "SHOW DATABASES;"
```

**Permessi file?**
```bash
sudo chown -R www-data:www-data /var/www/html/proloco
sudo chmod -R 755 /var/www/html/proloco
```
