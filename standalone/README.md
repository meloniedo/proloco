# 📱 Proloco Santa Bianca - Bar Manager (STANDALONE)

## 🎯 App Completamente Offline per Gestione Bar

### ✅ Caratteristiche:
- **100% Offline** - Nessuna connessione internet necessaria
- **Dati locali** - Tutto salvato nel browser (localStorage)
- **PWA Installabile** - Si comporta come app nativa
- **Export Excel** - Backup completo con fogli per mese
- **Zero configurazione** - Apri e usa!

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

### **Metodo 2: Hosting Online (richiede internet UNA VOLTA)**

1. Carica `index.html` su un hosting gratuito:
   - GitHub Pages
   - Netlify
   - Vercel

2. Apri l'URL con Chrome sul telefono

3. Aggiungi alla Home Screen

4. Da quel momento funziona offline!

---

## 💾 Backup dei Dati:

### **Export Excel:**
- Vai su **Storico**
- Tap "📊 Scarica Excel Completo"
- File salvato in Download
- Contiene tutti i dati suddivisi per mese

### **Invio via Email:**
- Dopo download Excel
- Apri app Email
- Allega il file dalla cartella Download
- Invia a te stesso

### **Backup Manuale (opzionale):**
I dati sono salvati nel localStorage del browser.
Per backup completo:
1. Apri Chrome DevTools (su PC)
2. Application → Local Storage
3. Copia tutto il contenuto

---

## 🔒 **NUOVE FUNZIONALITÀ:**

### **1. Modalità Kiosk (Schermo Bloccato)**

**Dove:** Sezione Storico

**Come funziona:**
1. Vai su "📋 Storico"
2. Tap su "🔒 Blocca Schermo (Modalità Kiosk)"
3. Il telefono entra in **fullscreen bloccato**
4. Per uscire: Tap sull'indicatore "🔒 Schermo Bloccato" in alto a destra
5. Inserisci password **5054**
6. Schermo sbloccato!

**Vantaggi:**
- ✅ Impedisce che altri utenti escano dall'app
- ✅ Perfetto per lasciare il telefono sul bancone del bar
- ✅ Blocca tasto back e navigazione
- ✅ Solo tu puoi uscire con la password

---

### **2. Export Automatico Notturno**

**Funzionamento Automatico:**
- ✅ Ogni giorno alle **23:59** l'app esporta automaticamente lo storico
- ✅ File salvato in **Download** con nome: `storico_DD-MM-YYYY_HH-MM.xlsx`
- ✅ Esempio: `storico_08-01-2026_23-59.xlsx`
- ✅ Backup giornaliero senza doverti ricordare!

**Come verificare:**
- Vai nella cartella Download del telefono il giorno dopo
- Troverai il file Excel con la data di ieri

**Nota:** L'app deve essere **aperta** alle 23:59 perché l'export si attivi. Se è chiusa, l'export avverrà alla prossima apertura dell'app vicino alle 23:59.

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

**Q: Posso usarla su più telefoni?**
A: Sì, ma i dati non si sincronizzano. Ogni telefono ha i suoi dati separati.

---

## 🚀 Sviluppi Futuri:

Se in futuro vorrai:
- Sincronizzazione tra dispositivi
- Backup automatici cloud
- Grafici avanzati
- Server locale (Raspberry Pi)

Contattami per upgrade!

---

## 📞 Supporto:

Per domande o problemi, puoi:
1. Controllare questa guida
2. Verificare i backup Excel
3. Chiedere assistenza

---

**Versione:** 1.0 Standalone  
**Data:** Gennaio 2026  
**Tecnologie:** HTML5, JavaScript ES6, Tailwind CSS, SheetJS  
**Licenza:** Uso personale Proloco Santa Bianca
