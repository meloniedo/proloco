# Proloco Bar Manager - PRD

## Problema Originale
Applicazione web PHP/MySQL per la gestione di un bar, da eseguire offline su Raspberry Pi. Richieste di risoluzione bug critici e creazione sistema di gestione completo.

## Stack Tecnologico
- **Backend:** PHP, MySQL (MariaDB)
- **Frontend:** Vanilla JavaScript, HTML/CSS
- **Ambiente:** Raspberry Pi offline con hotspot WiFi
- **Automazione:** Bash scripting, cron jobs, systemd

## Architettura
```
/home/pi/proloco/
├── aggiorna.sh                 # Script aggiornamento da GitHub
├── raspberry_pi/               # Directory principale app
│   ├── gestione_database.sh    # Menu interattivo gestione DB
│   ├── import_xlsx.php         # Import Excel
│   ├── export_xlsx.php         # Export Excel
│   ├── index.html              # Frontend
│   ├── includes/
│   │   ├── config.php
│   │   ├── storico_txt.php     # Sync STORICO.txt ↔ DB
│   │   └── listino_txt.php     # Sync LISTINO.txt ↔ DB
│   └── api/
├── backup/                     # Backup SQL
├── BACKUP_GIORNALIERI/         # Backup Excel
└── RESOCONTI_SETTIMANALI/
```

## Funzionalità Implementate
- ✅ Gestione vendite e spese
- ✅ Gestione prodotti/listino
- ✅ Script gestione_database.sh (backup SQL/XLSX, import, export, reset)
- ✅ Sincronizzazione automatica STORICO.txt e LISTINO.txt
- ✅ Backup automatici programmati
- ✅ Import/Export Excel (.xlsx)

## Bug Risolti (24/01/2026)

### P0 - Perdita Dati dopo aggiorna.sh ✅
- **Causa:** `cron_sync.php` (ogni minuto) leggeva `STORICO.txt` e cancellava dal DB tutti i record non presenti nel file. Quando `git reset --hard` sovrascriveva il file, il cron cancellava tutti i dati.
- **Fix:** Aggiunta protezione anti-cancellazione di massa in `includes/storico_txt.php`:
  - Non cancella se file ha <50% dei record del DB
  - Non cancella se file vuoto ma DB ha dati
  - Non cancella più di 10 record alla volta

### Bug Conteggio Export SQL ✅
- **Problema:** Mostrava "~4 record" invece dei record effettivi
- **Fix:** Corretto conteggio in `gestione_database.sh` per contare righe effettive nelle sezioni vendite/spese

## Bug Aperti

### P1 - Pulsante Impostazioni (⚙️) non funziona
- **Descrizione:** Cliccando sull'icona ingranaggio non si accede alle impostazioni
- **File:** `/raspberry_pi/index.html`
- **Status:** Da investigare

## Prossimi Passi
1. Risolvere bug pulsante impostazioni
2. Test completo di tutte le funzionalità
