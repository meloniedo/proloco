# 📘 MANUALE PROLOCO BAR MANAGER
## Raspberry Pi 3A+ - Guida Completa

---

**Versione:** 4.0  
**WiFi:** ProlocoBar / proloco2024  
**IP:** 192.168.4.1

---

## 📑 INDICE

1. [Struttura Cartelle](#1-struttura-cartelle)
2. [Comandi Base Terminale](#2-comandi-base-terminale)
3. [Connessione al Raspberry](#3-connessione-al-raspberry)
4. [Aggiornamento da GitHub](#4-aggiornamento-da-github)
5. [Gestione Hotspot/Internet](#5-gestione-hotspot-internet)
6. [Importazione Dati Excel](#6-importazione-dati-excel)
7. [Backup e Reset Database](#7-backup-e-reset-database)
8. [Gestione Permessi](#8-gestione-permessi)
9. [Comandi di Verifica](#9-comandi-di-verifica)
10. [Risoluzione Problemi](#10-risoluzione-problemi)
11. [File Importanti](#11-file-importanti)

---

## 1. STRUTTURA CARTELLE

```
/home/pi/proloco/                    ← CARTELLA PRINCIPALE
│
├── raspberry_pi/                    ← APP WEB
│   ├── index.html                   ← Pagina principale
│   ├── STORICO.txt                  ← Storico vendite/spese
│   ├── LISTINO.txt                  ← Lista prodotti
│   │
│   ├── api/                         ← API Backend
│   │   ├── vendite.php
│   │   ├── spese.php
│   │   └── ...
│   │
│   ├── includes/                    ← Configurazione
│   │   └── config.php               ← Credenziali database
│   │
│   ├── import_xlsx.php              ← Importazione Excel
│   ├── backup_database.sh           ← Script backup
│   ├── reset_database.sh            ← Script reset
│   └── backup_e_reset.sh            ← Backup + Reset
│
├── BACKUP_GIORNALIERI/              ← Backup automatici
├── RESOCONTI_SETTIMANALI/           ← Report settimanali
├── backup/                          ← Backup manuali
│
├── aggiorna.sh                      ← Aggiornamento GitHub
├── modalita_internet.sh             ← Attiva WiFi
└── modalita_hotspot.sh              ← Attiva hotspot
```

---

## 2. COMANDI BASE TERMINALE

### Navigazione

| Comando | Cosa fa |
|---------|---------|
| `cd /percorso` | Vai in una cartella |
| `cd ..` | Torna indietro |
| `cd ~` | Vai alla home |
| `pwd` | Mostra dove sei |
| `ls` | Lista file |
| `ls -la` | Lista dettagliata |

### Operazioni File

| Comando | Cosa fa |
|---------|---------|
| `cat file.txt` | Mostra contenuto |
| `nano file.txt` | Modifica file |
| `cp file1 file2` | Copia |
| `mv file1 file2` | Sposta/rinomina |
| `rm file` | Cancella |
| `mkdir cartella` | Crea cartella |

### Sistema

| Comando | Cosa fa |
|---------|---------|
| `sudo comando` | Esegui come admin |
| `sudo reboot` | Riavvia |
| `sudo shutdown -h now` | Spegni |

---

## 3. CONNESSIONE AL RASPBERRY

### Metodo 1: WiFi Hotspot (normale)
1. Connettiti al WiFi **"ProlocoBar"** con password **"proloco2024"**
2. Apri browser: `http://192.168.4.1`
3. Per SSH: `ssh edo@192.168.4.1`

### Metodo 2: Monitor e tastiera
1. Collega monitor HDMI e tastiera USB
2. Login: **edo** / **5054**

---

## 4. AGGIORNAMENTO DA GITHUB

### Metodo Veloce
```bash
cd /home/pi/proloco
./aggiorna.sh
```

### Metodo Manuale
```bash
cd /home/pi/proloco
git pull origin main

# Se ci sono errori:
git fetch --all
git reset --hard origin/main

# Reinstalla
cd raspberry_pi
sudo bash install.sh
sudo reboot
```

---

## 5. GESTIONE HOTSPOT / INTERNET

### Attivare HOTSPOT (uso bar)
```bash
sudo bash /home/pi/proloco/modalita_hotspot.sh
```

### Attivare INTERNET (per aggiornamenti)
```bash
sudo bash /home/pi/proloco/modalita_internet.sh

# Connetti al WiFi:
sudo nmcli device wifi connect "NOME_RETE" password "PASSWORD"
```

---

## 6. IMPORTAZIONE DATI EXCEL

### 📋 Struttura del File Excel (.xlsx)

Il file deve contenere **UN SOLO FOGLIO** con questa struttura:

#### Sezione VENDITE (in alto)

| Colonna A | Colonna B | Colonna C | Colonna D | Colonna E |
|-----------|-----------|-----------|-----------|-----------|
| **Data** | **Ora** | **Prodotto** | **Categoria** | **Importo** |
| 22/01/2026 | 15:41:11 | Caffè | CAFFETTERIA | 1.2 |
| 22/01/2026 | 15:40:38 | Extra | PERSONALIZZATE | 2.6 |
| ... | ... | ... | ... | ... |
| | | | TOTALE VENDITE: | 910.60 |

#### Sezione SPESE (sotto le vendite)

| Colonna A | Colonna B | Colonna C | Colonna D | Colonna E |
|-----------|-----------|-----------|-----------|-----------|
| **SPESE** | | | | |
| **Data** | **Ora** | **Categoria** | | **Importo** |
| 20/01/2026 | 17:50:52 | Cialde caffè | | 140 |
| 17/01/2026 | 18:53:41 | Articoli Pulizia | | 6 |
| ... | ... | ... | | ... |
| | | | TOTALE SPESE: | 627.50 |

### ⚠️ Regole Importanti

1. File DEVE essere **.xlsx** (non .xls)
2. Vendite e spese nello **STESSO foglio** (Sheet 1)
3. Date in formato: **GG/MM/AAAA** (es. 22/01/2026)
4. Ora in formato: **HH:MM:SS** (es. 15:41:11)
5. Importi con **PUNTO** decimale (es. 1.20, non 1,20)
6. "TOTALE VENDITE" separa vendite e spese
7. La parola "SPESE" indica inizio sezione spese

### Procedura Importazione

```bash
cd /home/pi/proloco/raspberry_pi

# 1. Testa l'importazione (opzionale)
php test_import_xlsx.php /percorso/file.xlsx

# 2. Backup e reset database
./backup_e_reset.sh

# 3. Importa
php import_xlsx.php /percorso/file.xlsx
```

### Esempio con chiavetta USB
```bash
cd /home/pi/proloco/raspberry_pi
./backup_e_reset.sh
php import_xlsx.php /media/usb_sda1/storico.xlsx
```

### Risoluzione Problemi

| Problema | Soluzione |
|----------|-----------|
| Vendite: 0 | Verifica intestazione con "Prodotto" in colonna C |
| Spese: 0 | Verifica riga "TOTALE VENDITE" e riga "SPESE" |
| Importi sbagliati | Usa PUNTO decimale (1.20 non 1,20) |

---

## 7. BACKUP E RESET DATABASE

### 🎯 Menu Interattivo (CONSIGLIATO)
```bash
cd /home/pi/proloco/raspberry_pi
./gestione_database.sh
```

Apre un menu grafico con tutte le opzioni:

```
╔══════════════════════════════════════════════════════════════╗
║          🗄️  GESTIONE DATABASE - PROLOCO BAR                 ║
╚══════════════════════════════════════════════════════════════╝

   1) 📦 Crea nuovo backup
   2) 📋 Lista backup disponibili
   3) 🔄 Ripristina un backup
   4) 🗑️  Elimina backup
   5) ⚠️  Reset database (cancella vendite/spese)
   6) 📄 Esporta in formato leggibile (.txt)
   
   0) 🚪 Esci
```

### Comandi Rapidi (senza menu)
```bash
# Solo Backup
./backup_database.sh

# Solo Reset (cancella tutto!)
./reset_database.sh

# Backup + Reset
./backup_e_reset.sh
```

### Dove sono i backup?
- **Cartella:** `/home/pi/proloco/backup/`
- **Formato:** `backup_YYYYMMDD_HHMMSS.sql.gz`
- `.sql` = formato database MySQL
- `.gz` = compresso

### Ripristino Manuale
```bash
# Lista backup disponibili
ls -la /home/pi/proloco/backup/

# Decomprimi
gunzip -k /home/pi/proloco/backup/backup_XXXXXXXX.sql.gz

# Ripristina
mysql -u edo -p5054 proloco_bar < /home/pi/proloco/backup/backup_XXXXXXXX.sql
```

---

## 8. GESTIONE PERMESSI

### Fix Rapido
```bash
sudo /usr/local/bin/avvio_proloco.sh
```

### Fix Manuale
```bash
cd /home/pi/proloco/raspberry_pi
sudo chown -R www-data:www-data .
sudo chmod -R 775 .
sudo chmod 666 STORICO.txt LISTINO.txt
```

---

## 9. COMANDI DI VERIFICA

### Verifica Servizi
```bash
sudo systemctl status apache2     # Web server
sudo systemctl status mariadb     # Database
sudo systemctl status hostapd     # Hotspot
```

### Verifica Database
```bash
# Conta vendite
mysql -u edo -p5054 proloco_bar -e "SELECT COUNT(*) FROM vendite"

# Conta spese
mysql -u edo -p5054 proloco_bar -e "SELECT COUNT(*) FROM spese"
```

### Verifica Log Errori
```bash
sudo tail -50 /var/log/apache2/error.log
```

---

## 10. RISOLUZIONE PROBLEMI

| Problema | Soluzione |
|----------|-----------|
| **App non si apre** | `sudo systemctl restart apache2` |
| **WiFi non funziona** | `sudo reboot` |
| **Permission denied** | `sudo /usr/local/bin/avvio_proloco.sh` |
| **Git conflitti** | `git fetch --all && git reset --hard origin/main` |
| **STORICO vuoto** | `sudo chmod 666 STORICO.txt` poi `php cron_sync.php` |

---

## 11. FILE IMPORTANTI

| File | Descrizione |
|------|-------------|
| `includes/config.php` | Credenziali database |
| `STORICO.txt` | Vendite e spese (auto-sync) |
| `LISTINO.txt` | Prodotti (auto-sync) |
| `install.sh` | Installazione completa |
| `aggiorna.sh` | Aggiornamento GitHub |
| `avvio_proloco.sh` | Fix permessi all'avvio |
| `modalita_internet.sh` | Attiva WiFi |
| `modalita_hotspot.sh` | Attiva hotspot |
| `gestione_database.sh` | 🎯 Menu interattivo backup/ripristino |
| `backup_e_reset.sh` | Backup + reset DB |
| `import_xlsx.php` | Importa Excel |

---

## 🔐 CREDENZIALI

| Cosa | Valore |
|------|--------|
| **WiFi Nome** | ProlocoBar |
| **WiFi Password** | proloco2024 |
| **IP** | 192.168.4.1 |
| **DB Utente** | edo |
| **DB Password** | 5054 |
| **DB Nome** | proloco_bar |
| **Utente Raspberry** | edo |
| **Password Raspberry** | 5054 |

---

## ⚡ COMANDI RAPIDI

```bash
# Vai alla cartella app
cd /home/pi/proloco/raspberry_pi

# 🎯 GESTIONE DATABASE (menu interattivo)
./gestione_database.sh

# Aggiorna da GitHub
cd /home/pi/proloco && ./aggiorna.sh

# Backup + Reset
./backup_e_reset.sh

# Importa Excel
php import_xlsx.php /percorso/file.xlsx

# Fix permessi
sudo /usr/local/bin/avvio_proloco.sh

# Riavvia
sudo reboot
```

---

*Fine Manuale - Proloco Bar Manager v4.0*
