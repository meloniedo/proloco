# PRD - Proloco Santa Bianca Bar Manager

## Descrizione Progetto
Applicazione web per la gestione di un bar della Proloco, progettata per funzionare offline su Raspberry Pi 3A+ configurato come hotspot WiFi.

## Requisiti Principali
- **Registrazione vendite** con prodotti organizzati per categoria
- **Gestione spese** con categorie predefinite
- **Statistiche** con riepilogo incassi/spese/profitto
- **Sistema backup** su USB e WiFi (file Excel)
- **Funzionamento offline** tramite hotspot WiFi
- **PWA** per installazione su dispositivi mobili

## Architettura Tecnica
- **Frontend:** HTML5, CSS3, JavaScript Vanilla
- **Backend:** PHP 8.x, MySQL/MariaDB
- **Deploy:** Raspberry Pi 3A+ con Apache, hostapd, dnsmasq
- **Automazione:** Cron jobs per sync e backup

## Struttura Directory
```
/app/raspberry_pi/
├── index.html          # Frontend principale
├── install.sh          # Script installazione automatica
├── database.sql        # Schema DB
├── manifest.json       # PWA manifest
├── sw.js               # Service Worker
├── api/                # Endpoint PHP
│   ├── prodotti.php
│   ├── vendite.php
│   ├── spese.php
│   ├── statistiche.php
│   ├── listino.php
│   ├── download_backup.php
│   ├── usb_backup.php
│   ├── backup_settings.php
│   ├── system_time.php
│   └── view_resoconto.php
├── includes/
│   ├── config.php
│   └── storico_txt.php
├── cron_sync.php       # Sync STORICO.txt ogni minuto
├── cron_backup.php     # Backup automatico programmato
├── cron_resoconto.php  # Report settimanale (lunedì 08:00)
└── css/style.css
```

## Credenziali Sistema
- **WiFi Hotspot:** SSID `ProlocoBar`, Password `proloco2024`
- **IP Raspberry:** `192.168.4.1`
- **DB MySQL:** User `edo`, Password `5054`, DB `proloco_bar`
- **Password Reset:** `5054`

---

# CHANGELOG

## [2024-12-XX] - Versione Finale

### ✅ Implementato in questa sessione:

1. **Font più grandi** - Aumentata la dimensione dei caratteri in tutta l'app per migliore leggibilità su schermo touch
   - Base font 18px
   - Titoli 24-28px
   - Pulsanti prodotti con icone 40px e testo 18-22px

2. **Statistiche complete** - Ripristinate le sezioni "Per Categoria" e "Top 5 Prodotti" nella pagina statistiche

3. **Report settimanale automatico** - Cron job che genera file .TXT ogni lunedì alle 08:00
   - Percorso: `/home/pi/proloco/RESOCONTI_SETTIMANALI/`
   - Formato: riepilogo settimana + top 3 prodotti

4. **Copia locale backup** - Ogni backup (WiFi o USB) viene salvato anche in `/home/pi/proloco/BACKUP_GIORNALIERI/`

5. **Popup ora all'avvio** - Se l'ora del sistema non è sincronizzata, mostra popup per impostarla manualmente

6. **Modulo cambio ora nelle impostazioni** - Aggiunto pannello per modificare data/ora del sistema

7. **Rimosso "Importa Backup"** - Modulo rimosso dalle impostazioni come richiesto

8. **Resoconto Totale** - Creato file `RESOCONTO_TOTALE.txt` con riepilogo completo
   - Pulsante "Visualizza Resoconto Totale" nelle impostazioni
   - Apre il report in una nuova scheda

9. **Gestione listino migliorata** - Interfaccia più stabile e leggibile
   - Pulsanti più grandi
   - Icone più visibili
   - Padding aumentato

### Script di installazione aggiornato:
- Crea cartelle `/home/pi/proloco/BACKUP_GIORNALIERI` e `/home/pi/proloco/RESOCONTI_SETTIMANALI`
- Configura cron per report settimanale
- Aggiunge permessi sudo per www-data (cambio ora sistema)

---

## [Sessioni Precedenti]

### Sistema di Sincronizzazione
- Sync bidirezionale DB ↔ STORICO.txt ogni minuto via cron

### Backup Excel Reale
- Generazione file .xlsx con ZipArchive
- Fallback a .xls (XML) se php-zip non disponibile

### Gestione Rete
- Script `modalita_hotspot.sh` e `modalita_internet.sh`
- Risolto problema blocco rete con NetworkManager

### PWA
- Manifest configurato
- Service Worker per funzionamento offline
- Icone SVG

### Fix UI
- Scroll bloccato risolto
- Menu navigazione responsive
- Popup con sfondo solido

---

# ROADMAP

## P0 - Completato
- [x] 9 requisiti finali implementati

## P1 - Verifica Utente
- [ ] Test completo su Raspberry Pi reale
- [ ] Verifica funzionamento popup ora
- [ ] Test report settimanale

## P2 - Possibili Miglioramenti Futuri
- [ ] Grafici statistiche con Chart.js
- [ ] Export PDF report
- [ ] Notifiche push per backup
- [ ] Tema scuro/chiaro
