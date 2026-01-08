# 📝 GUIDA FILE CONFIGURAZIONE (config.js)

## 🎯 Panoramica

Il file `config.js` ti permette di modificare tutte le impostazioni dell'app senza dover modificare il codice principale.

---

## 📂 Come Modificare

1. Apri `config.js` con qualsiasi editor di testo (Notepad, VS Code, etc.)
2. Modifica i valori che ti interessano
3. Salva il file
4. Ricarica l'app sul telefono (o fai nuovo upload su GitHub)
5. Le modifiche sono attive! ✅

---

## ⚙️ Impostazioni Disponibili

### **1. INFORMAZIONI BAR**

```javascript
nome_bar: "Proloco Santa Bianca"
```
- Nome che appare nei report e email
- Modifica con il nome del tuo bar

---

### **2. EMAIL E RESOCONTI**

```javascript
email_destinatari: "email1@example.com,email2@example.com"
```
- Email dove ricevere resoconti settimanali
- Separa più email con virgola
- Esempio: `"mario@example.com,luigi@example.com"`

```javascript
invio_resoconto_automatico: true
```
- `true` = attivato (consigliato)
- `false` = disattivato

```javascript
giorno_resoconto: 0
```
- Giorno preferito per resoconto settimanale
- `0` = Domenica
- `1` = Lunedì
- `2` = Martedì
- `3` = Mercoledì
- `4` = Giovedì
- `5` = Venerdì
- `6` = Sabato

---

### **3. TIMER E REFRESH**

```javascript
timer_controllo_minuti: 60
```
- Ogni quanti minuti controllare la connessione internet
- Consigliato: `60` (1 ora)
- Minimo: `15` minuti
- Massimo: `720` (12 ore)

```javascript
auto_refresh: false
```
- `true` = Ricarica pagina dopo ogni controllo connessione
- `false` = Controlla senza ricaricare (consigliato)

---

### **4. EXPORT AUTOMATICO**

```javascript
export_automatico_attivo: true
```
- `true` = Export Excel automatico ogni giorno
- `false` = Disattiva export automatico

```javascript
orario_export: {
    ore: 23,
    minuti: 59
}
```
- Orario export automatico (formato 24h)
- Esempio: `{ore: 20, minuti: 30}` = 20:30 (8:30 PM)

---

### **5. SICUREZZA**

```javascript
password_reset: "5054"
```
- Password per reset periodo nelle statistiche
- Cambiala per maggiore sicurezza
- Esempio: `"1234"` o `"9876"`

---

### **6. LISTINO PREZZI**

```javascript
listino: [
    {nome: "Caffè", prezzo: 1.20, categoria: "CAFFETTERIA", icona: "☕"},
    // ... altri prodotti
]
```

**Come aggiungere un prodotto:**
```javascript
{nome: "Cappuccino", prezzo: 1.50, categoria: "CAFFETTERIA", icona: "☕"}
```

**Come rimuovere un prodotto:**
- Cancella tutta la riga del prodotto

**Come modificare prezzo:**
- Cambia solo il valore di `prezzo:`

**Categorie disponibili:**
- `CAFFETTERIA`
- `BEVANDE`
- `GELATI`
- `PERSONALIZZATE` (prezzo 0 = richiede inserimento manuale)

**Emoji disponibili:**
- ☕ 🥃 🍷 🥤 💧 🍦 🎱 ➕ 🍕 🍔 🥗 🍰 🧃

---

### **7. CATEGORIE SPESE**

```javascript
categorie_spese: [
    {nome: "Cialde caffè", icona: "☕", colore: "from-amber-500 to-orange-600"},
    // ... altre categorie
]
```

**Come aggiungere categoria:**
```javascript
{nome: "Benzina", icona: "⛽", colore: "from-yellow-500 to-orange-600"}
```

**Colori disponibili:**
- `from-amber-500 to-orange-600` (arancione)
- `from-purple-500 to-pink-600` (viola/rosa)
- `from-cyan-500 to-blue-600` (blu)
- `from-green-500 to-emerald-600` (verde)
- `from-indigo-500 to-purple-600` (indaco)
- `from-gray-500 to-gray-700` (grigio)
- `from-red-500 to-red-600` (rosso)

---

## 📧 Come Funziona il Resoconto Automatico

### **Logica:**

1. **Timer attivo:** Ogni X minuti (configurabile) l'app controlla:
   - Connessione internet disponibile?
   - È passata almeno 1 settimana dall'ultimo resoconto?
   - È il giorno preferito della settimana?

2. **Se tutte le condizioni sono vere:**
   - Genera resoconto settimanale
   - Apre client email con:
     * Destinatari pre-compilati (dal config)
     * Oggetto: "Resoconto [Nome Bar] - Settimana [Data]"
     * Corpo: Statistiche settimanali
   
3. **Tu devi solo:**
   - Premere "Invia" ✅
   - (Opzionale) Allegare Excel per dettagli completi

### **Frequenza:**

- **MASSIMO 1 resoconto alla settimana**
- Inviato solo nel giorno configurato
- Solo se c'è connessione internet

### **Esempio:**

- Config: `giorno_resoconto: 0` (Domenica)
- Timer: `60` minuti
- Ultimo resoconto: 10 giorni fa

**Risultato:** Domenica prossima, l'app aprirà automaticamente il client email con il resoconto!

---

## 🔧 Debug Mode

```javascript
debug: true
```

- `true` = Mostra log in console browser (per sviluppatori)
- `false` = Normale (consigliato)

Utile per vedere cosa fa l'app nei controlli periodici.

---

## ⚠️ ATTENZIONE

### **Cosa NON modificare:**

- ❌ Nomi delle variabili (solo i valori!)
- ❌ Struttura `{ }` e `[ ]`
- ❌ Virgole e punti e virgola
- ❌ Ultima riga: `if (typeof module...`

### **Backup:**

Prima di modificare, **crea una copia** del file originale!

---

## 💡 Esempi Pratici

### **Cambiare password reset:**

**Prima:**
```javascript
password_reset: "5054"
```

**Dopo:**
```javascript
password_reset: "1234"
```

---

### **Aggiungere email destinatario:**

**Prima:**
```javascript
email_destinatari: "mario@example.com"
```

**Dopo:**
```javascript
email_destinatari: "mario@example.com,luigi@example.com,peach@example.com"
```

---

### **Cambiare giorno resoconto a Venerdì:**

**Prima:**
```javascript
giorno_resoconto: 0  // Domenica
```

**Dopo:**
```javascript
giorno_resoconto: 5  // Venerdì
```

---

### **Aggiungere prodotto "Cappuccino":**

Trova la sezione `// CAFFETTERIA` e aggiungi:

```javascript
// CAFFETTERIA
{nome: "Caffè", prezzo: 1.20, categoria: "CAFFETTERIA", icona: "☕"},
{nome: "Cappuccino", prezzo: 1.50, categoria: "CAFFETTERIA", icona: "☕"},  // NUOVO!
{nome: "Caffè Deca", prezzo: 1.20, categoria: "CAFFETTERIA", icona: "☕"},
```

---

### **Aumentare prezzo vino:**

**Prima:**
```javascript
{nome: "Calice di Vino", prezzo: 1.50, categoria: "BEVANDE", icona: "🍷"}
```

**Dopo:**
```javascript
{nome: "Calice di Vino", prezzo: 2.00, categoria: "BEVANDE", icona: "🍷"}
```

---

## 🆘 Problemi?

### **App non si carica dopo modifica:**

1. Controlla di non aver cancellato virgole o parentesi
2. Ripristina backup
3. Riprova modifiche una alla volta

### **Resoconto non si invia:**

1. Verifica `invio_resoconto_automatico: true`
2. Controlla `email_destinatari` (deve avere almeno 1 email)
3. Attiva `debug: true` per vedere log

### **Listino non aggiornato:**

1. Svuota cache app
2. Reinstalla app
3. Oppure vai su Impostazioni telefono → App → Bar Manager → Cancella dati

---

## 📞 Supporto

Per modifiche complesse o problemi, contattami!

---

**Versione Guida:** 1.0  
**Data:** Gennaio 2026
