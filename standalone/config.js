// ========================================
// FILE DI CONFIGURAZIONE
// Proloco Santa Bianca - Bar Manager
// ========================================
// 
// ISTRUZIONI:
// - Modifica questo file con qualsiasi editor di testo
// - Salva e ricarica l'app per applicare le modifiche
// - Non modificare i nomi delle variabili (solo i valori dopo ":")
//
// ========================================

const CONFIG = {
    
    // ==================== INFORMAZIONI BAR ====================
    
    // Nome del bar (appare nei report e email)
    nome_bar: "Proloco Santa Bianca",
    
    // ==================== EMAIL E RESOCONTI ====================
    
    // Email destinatari resoconto (separate da virgola)
    // Esempio: "email1@example.com,email2@example.com,email3@example.com"
    email_destinatari: "tuaemail@example.com",
    
    // Invia resoconto automatico quando c'è connessione
    // true = attivato, false = disattivato
    invio_resoconto_automatico: true,
    
    // Giorni preferiti per resoconto settimanale (array)
    // 0=Domenica, 1=Lunedì, 2=Martedì, 3=Mercoledì, 4=Giovedì, 5=Venerdì, 6=Sabato
    // Esempio: [1, 4] = Lunedì e Giovedì
    giorni_resoconto: [1, 4], // Lunedì e Giovedì
    
    // Orario preferito per resoconto (formato 24h)
    orario_resoconto: {
        ore: 17,
        minuti: 0
    },
    
    // ==================== TIMER E REFRESH ====================
    
    // Timer controllo connessione internet (in minuti)
    // L'app controllerà la connessione ogni X minuti
    // Consigliato: 30-60 minuti
    timer_controllo_minuti: 60,
    
    // Auto-refresh pagina dopo controllo connessione
    // true = ricarica pagina dopo ogni controllo
    // false = controlla senza ricaricare
    auto_refresh: false,
    
    // ==================== EXPORT AUTOMATICO ====================
    
    // Export automatico Excel ogni giorno
    // true = attivato, false = disattivato
    export_automatico_attivo: true,
    
    // Orario export automatico (formato 24h)
    orario_export: {
        ore: 23,
        minuti: 59
    },
    
    // ==================== SICUREZZA ====================
    
    // Password per reset periodo
    // Modifica questa password se vuoi cambiarla
    password_reset: "5054",
    
    // ==================== LISTINO PREZZI ====================
    // Puoi aggiungere, modificare o rimuovere prodotti
    // Formato: {nome: "Nome Prodotto", prezzo: 1.50, categoria: "CATEGORIA", icona: "emoji"}
    // Categorie disponibili: CAFFETTERIA, BEVANDE, GELATI, PERSONALIZZATE
    
    listino: [
        // CAFFETTERIA
        {nome: "Caffè", prezzo: 1.20, categoria: "CAFFETTERIA", icona: "☕"},
        {nome: "Caffè Deca", prezzo: 1.20, categoria: "CAFFETTERIA", icona: "☕"},
        {nome: "Caffè Corretto", prezzo: 2.00, categoria: "CAFFETTERIA", icona: "🥃"},
        
        // BEVANDE
        {nome: "Caraffa di Vino 0,5L", prezzo: 6.00, categoria: "BEVANDE", icona: "🍷"},
        {nome: "Caraffa di Vino 1LT", prezzo: 11.00, categoria: "BEVANDE", icona: "🍷"},
        {nome: "Calice di Vino", prezzo: 1.50, categoria: "BEVANDE", icona: "🍷"},
        {nome: "Liquori, Grappe, Vodka e Amari", prezzo: 2.50, categoria: "BEVANDE", icona: "🥃"},
        {nome: "Bibite in Lattina", prezzo: 2.20, categoria: "BEVANDE", icona: "🥤"},
        {nome: "Bottiglietta d'Acqua", prezzo: 1.00, categoria: "BEVANDE", icona: "💧"},
        
        // GELATI
        {nome: "Cremino", prezzo: 1.20, categoria: "GELATI", icona: "🍦"},
        {nome: "Cucciolone", prezzo: 1.50, categoria: "GELATI", icona: "🍦"},
        {nome: "Magnum, Soia e altri Gelati", prezzo: 2.00, categoria: "GELATI", icona: "🍦"},
        
        // PERSONALIZZATE (prezzo 0 = richiede inserimento manuale)
        {nome: "Bigliardo", prezzo: 0.00, categoria: "PERSONALIZZATE", icona: "🎱"},
        {nome: "Extra", prezzo: 0.00, categoria: "PERSONALIZZATE", icona: "➕"}
    ],
    
    // ==================== CATEGORIE SPESE ====================
    // Modifica le categorie di spesa
    
    categorie_spese: [
        {nome: "Cialde caffè", icona: "☕", colore: "from-amber-500 to-orange-600"},
        {nome: "Vino", icona: "🍷", colore: "from-purple-500 to-pink-600"},
        {nome: "Articoli Pulizia", icona: "🧹", colore: "from-cyan-500 to-blue-600"},
        {nome: "Articoli S. Mercato", icona: "🛒", colore: "from-green-500 to-emerald-600"},
        {nome: "Rimborso Servizio", icona: "💼", colore: "from-indigo-500 to-purple-600"},
        {nome: "Spesa Generica", icona: "📋", colore: "from-gray-500 to-gray-700"}
    ],
    
    // ==================== AVANZATE ====================
    
    // Debug mode (mostra log in console)
    debug: false,
    
    // Versione configurazione (non modificare)
    versione: "1.0"
};

// Non modificare questa riga
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CONFIG;
}
