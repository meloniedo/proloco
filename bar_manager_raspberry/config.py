# ========================================
# CONFIGURAZIONE BAR MANAGER
# Proloco Santa Bianca
# ========================================
# 
# Modifica questo file per personalizzare l'app
# Dopo le modifiche, riavvia il server con:
#   sudo systemctl restart barmanager
#
# ========================================

CONFIG = {
    # ==================== INFORMAZIONI BAR ====================
    "nome_bar": "Proloco Santa Bianca",
    
    # ==================== EMAIL ====================
    # Email mittente (quella con App Password)
    "email_mittente": "meloni.edo@gmail.com",
    "email_password": "ybel pjks ueio cetu",
    
    # Email destinatari (separa con virgola)
    # Puoi aggiungerne quante vuoi
    "email_destinatari": [
        "alberto.melorec@gmail.com",
        "meloni.edo@gmail.com"
    ],
    
    # ==================== REPORT AUTOMATICO ====================
    # Invia report quando rileva internet (max 1 al giorno)
    "report_automatico": True,
    
    # Ore minime tra un report e l'altro (default: 24 ore)
    "ore_minime_tra_report": 24,
    
    # ==================== SICUREZZA ====================
    "password_reset": "5054",
    
    # ==================== SERVER ====================
    # Porta del server (default: 8080)
    "porta_server": 8080,
    
    # ==================== LISTINO PREZZI ====================
    "listino": [
        # CAFFETTERIA
        {"nome": "Caffè", "prezzo": 1.20, "categoria": "CAFFETTERIA", "icona": "☕"},
        {"nome": "Caffè Deca", "prezzo": 1.20, "categoria": "CAFFETTERIA", "icona": "☕"},
        {"nome": "Caffè Corretto", "prezzo": 2.00, "categoria": "CAFFETTERIA", "icona": "🥃"},
        
        # BEVANDE
        {"nome": "Caraffa di Vino 0,5L", "prezzo": 6.00, "categoria": "BEVANDE", "icona": "🍷"},
        {"nome": "Caraffa di Vino 1LT", "prezzo": 11.00, "categoria": "BEVANDE", "icona": "🍷"},
        {"nome": "Calice di Vino", "prezzo": 1.50, "categoria": "BEVANDE", "icona": "🍷"},
        {"nome": "Liquori, Grappe, Vodka e Amari", "prezzo": 2.50, "categoria": "BEVANDE", "icona": "🥃"},
        {"nome": "Bibite in Lattina", "prezzo": 2.20, "categoria": "BEVANDE", "icona": "🥤"},
        {"nome": "Bottiglietta d'Acqua", "prezzo": 1.00, "categoria": "BEVANDE", "icona": "💧"},
        
        # GELATI
        {"nome": "Cremino", "prezzo": 1.20, "categoria": "GELATI", "icona": "🍦"},
        {"nome": "Cucciolone", "prezzo": 1.50, "categoria": "GELATI", "icona": "🍦"},
        {"nome": "Magnum, Soia e altri Gelati", "prezzo": 2.00, "categoria": "GELATI", "icona": "🍦"},
        
        # PERSONALIZZATE (prezzo 0 = inserimento manuale)
        {"nome": "Bigliardo", "prezzo": 0.00, "categoria": "PERSONALIZZATE", "icona": "🎱"},
        {"nome": "Extra", "prezzo": 0.00, "categoria": "PERSONALIZZATE", "icona": "➕"}
    ],
    
    # ==================== CATEGORIE SPESE ====================
    "categorie_spese": [
        {"nome": "Cialde caffè", "icona": "☕", "colore": "from-amber-500 to-orange-600"},
        {"nome": "Vino", "icona": "🍷", "colore": "from-purple-500 to-pink-600"},
        {"nome": "Articoli Pulizia", "icona": "🧹", "colore": "from-cyan-500 to-blue-600"},
        {"nome": "Articoli S. Mercato", "icona": "🛒", "colore": "from-green-500 to-emerald-600"},
        {"nome": "Rimborso Servizio", "icona": "💼", "colore": "from-indigo-500 to-purple-600"},
        {"nome": "Spesa Generica", "icona": "📋", "colore": "from-gray-500 to-gray-700"}
    ],
    
    # ==================== DEBUG ====================
    "debug": False
}
