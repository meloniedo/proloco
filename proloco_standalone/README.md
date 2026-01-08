# 📱 Proloco Santa Bianca - Bar Manager (STANDALONE)

## 🎯 App Completamente Offline per Gestione Bar

### ✅ Caratteristiche:

- **100% Offline** - Nessuna connessione internet necessaria
- **Dati locali** - Tutto salvato nel browser (localStorage)
- **PWA Installabile** - Si comporta come app nativa
- **Export Excel** - Backup completo con fogli per mese
- **📧 Invio Email Automatico** - Resoconto via EmailJS quando c'è connessione
- **Zero configurazione** - Apri e usa!

---

## 📧 NUOVA FUNZIONALITÀ: Invio Email Automatico

### Come funziona:

1. 🟠 **Pallino arancione** = Resoconto in attesa di invio
2. 📶 Quando l'app rileva connessione internet → invia automaticamente
3. 🟢 **Pallino verde** = Resoconto inviato con successo
4. ⏰ Controllo automatico ogni 5 minuti (configurabile)

### Configurazione Email (config.js):

```javascript
// Email destinatari
email_destinatari: "email1@example.com,email2@example.com",

// Credenziali EmailJS
emailjs_service_id: "service_xxxxx",
emailjs_template_id: "template_xxxxx", 
emailjs_public_key: "xxxxx",

// Timer controllo (minuti)
timer_controllo_minuti: 5,
```

---

## 📦 Come Installare sul Telefono Android:

### **Metodo 1: File Locale (CONSIGLIATO per uso senza internet)**

1. **Copia il file `index.html` sul telefono:**
   - Via USB
   - Via Bluetooth
   - Via email (invia a te stesso)
   - Via cloud (Google Drive, Dropbox)

2. **Apri il file con Chrome:**
   - Trova il file nella cartella Download
   - Tap sul file
   - Scegli "Chrome" come app per aprire

3. **Aggiungi alla Home Screen:**
   - Menu Chrome (⋮) → "Aggiungi a schermata Home"
   - Conferma
   - L'icona ☕ apparirà sulla home

4. **Usa l'app:**
   - Tap sull'icona dalla home
   - Si apre fullscreen senza barra browser
   - Funziona completamente offline! ✅

---

## 💾 Backup dei Dati:

### **Export Excel:**

- Vai su **Storico**
- Tap "📊 Crea Resoconto Excel"
- File salvato in Download
- Contiene tutti i dati suddivisi per mese

### **Invio Email Manuale:**

- Vai su **Storico**
- Tap "📧 Invia Resoconto Email Ora"
- Oppure clicca sul pallino arancione nell'header

---

## 📊 Funzionalità Complete:

### **Vendite:**

- 12 prodotti dal listino
- Registrazione rapida con tap
- Prodotti personalizzabili (Bigliardo, Extra)
- Tastierino per importi custom

### **Spese:**

- 6 categorie predefinite
- Tastierino per inserire importi
- Storico ultimi inserimenti

### **Statistiche:**

- Incasso Totale
- Spese Totali
- Profitto Netto
- Statistiche per Oggi/Settimana/Mese
- Top 5 prodotti più venduti
- Vendite per categoria

### **Storico:**

- Ultime 100 vendite
- Export Excel completo
- Eliminazione vendite

### **Sicurezza:**

- Reset periodo con password 5054
- Conferma per eliminazioni

---

## ⚠️ Importante:

### **Dati Locali:**

- I dati sono salvati **solo sul telefono**
- Se cancelli i dati del browser, perdi tutto
- **Fai backup regolari** (Excel settimanale/mensile)

### **Capacità Storage:**

- localStorage: ~5-10MB
- Sufficiente per **anni di vendite**

### **Compatibilità:**

- ✅ Chrome Android (consigliato)
- ✅ Firefox Android
- ✅ Safari iOS
- ✅ Edge

---

## 🔧 Problemi Comuni:

**Q: L'app non salva i dati?**  
A: Controlla che Chrome non sia in modalità Incognito

**Q: Ho perso i dati?**  
A: Se hai cancellato i dati del browser, non sono recuperabili. Usa i backup Excel!

**Q: L'app non si apre fullscreen?**  
A: Assicurati di averla "Aggiunta alla schermata Home" e di aprirla dall'icona, non dal browser

**Q: L'email non viene inviata?**  
A: Verifica le credenziali EmailJS nel file config.js e controlla la connessione internet

---

**Versione:** 2.0 con EmailJS

**Data:** Gennaio 2026

**Tecnologie:** HTML5, JavaScript ES6, Tailwind CSS, SheetJS, EmailJS

**Licenza:** Uso personale Proloco Santa Bianca
