import { useState, useEffect } from "react";
import "@/App.css";

// ==================== CONFIGURAZIONE ====================
const CONFIG = {
  nome_bar: "Proloco Santa Bianca",
  email_destinatari: "alberto.melorec@gmail.com,meloni.edo@gmail.com",
  invio_resoconto_automatico: true,
  emailjs_service_id: "service_l12wg3h",
  emailjs_template_id: "template_to1ne4j",
  emailjs_public_key: "8Z--4zZR5hi4yeyOS",
  giorno_invio_report: 1,
  ora_invio_report: 8,
  minuti_invio_report: 0,
  giorni_minimo_tra_report: 7,
  timer_controllo_minuti: 5,
  auto_refresh: false,
  export_automatico_attivo: true,
  orario_export: { ore: 23, minuti: 59 },
  password_reset: "5054",
  listino: [
    {nome: "Caffè", prezzo: 1.20, categoria: "CAFFETTERIA", icona: "☕"},
    {nome: "Caffè Deca", prezzo: 1.20, categoria: "CAFFETTERIA", icona: "☕"},
    {nome: "Caffè Corretto", prezzo: 2.00, categoria: "CAFFETTERIA", icona: "🥃"},
    {nome: "Caraffa di Vino 0,5L", prezzo: 6.00, categoria: "BEVANDE", icona: "🍷"},
    {nome: "Caraffa di Vino 1LT", prezzo: 11.00, categoria: "BEVANDE", icona: "🍷"},
    {nome: "Calice di Vino", prezzo: 1.50, categoria: "BEVANDE", icona: "🍷"},
    {nome: "Liquori, Grappe, Vodka e Amari", prezzo: 2.50, categoria: "BEVANDE", icona: "🥃"},
    {nome: "Bibite in Lattina", prezzo: 2.20, categoria: "BEVANDE", icona: "🥤"},
    {nome: "Bottiglietta d'Acqua", prezzo: 1.00, categoria: "BEVANDE", icona: "💧"},
    {nome: "Cremino", prezzo: 1.20, categoria: "GELATI", icona: "🍦"},
    {nome: "Cucciolone", prezzo: 1.50, categoria: "GELATI", icona: "🍦"},
    {nome: "Magnum, Soia e altri Gelati", prezzo: 2.00, categoria: "GELATI", icona: "🍦"},
    {nome: "Bigliardo", prezzo: 0.00, categoria: "PERSONALIZZATE", icona: "🎱"},
    {nome: "Extra", prezzo: 0.00, categoria: "PERSONALIZZATE", icona: "➕"}
  ],
  categorie_spese: [
    {nome: "Cialde caffè", icona: "☕", colore: "bg-amber-700"},
    {nome: "Vino", icona: "🍷", colore: "bg-red-800"},
    {nome: "Articoli Pulizia", icona: "🧹", colore: "bg-teal-700"},
    {nome: "Articoli S. Mercato", icona: "🛒", colore: "bg-green-700"},
    {nome: "Rimborso Servizio", icona: "💼", colore: "bg-stone-700"},
    {nome: "Spesa Generica", icona: "📋", colore: "bg-stone-600"}
  ],
  debug: true,
  versione: "2.1"
};

// ==================== DATABASE LOCALE (localStorage) ====================
class LocalDB {
  constructor() {
    this.init();
  }
  
  init() {
    if (!localStorage.getItem('prodotti')) {
      const prodottiIniziali = CONFIG.listino.map(p => ({
        ...p,
        id: this.generateId()
      }));
      localStorage.setItem('prodotti', JSON.stringify(prodottiIniziali));
    }
    
    if (!localStorage.getItem('vendite')) {
      localStorage.setItem('vendite', JSON.stringify([]));
    }
    
    if (!localStorage.getItem('spese')) {
      localStorage.setItem('spese', JSON.stringify([]));
    }
    
    if (!localStorage.getItem('ultimoResocontoInviato')) {
      localStorage.setItem('ultimoResocontoInviato', '');
    }
  }
  
  generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
  }
  
  getProdotti() {
    return JSON.parse(localStorage.getItem('prodotti') || '[]');
  }
  
  getVendite() {
    return JSON.parse(localStorage.getItem('vendite') || '[]');
  }
  
  getSpese() {
    return JSON.parse(localStorage.getItem('spese') || '[]');
  }
  
  addVendita(vendita) {
    const vendite = this.getVendite();
    vendite.unshift(vendita);
    localStorage.setItem('vendite', JSON.stringify(vendite));
  }
  
  addSpesa(spesa) {
    const spese = this.getSpese();
    spese.unshift(spesa);
    localStorage.setItem('spese', JSON.stringify(spese));
  }
  
  deleteVendita(id) {
    const vendite = this.getVendite().filter(v => v.id !== id);
    localStorage.setItem('vendite', JSON.stringify(vendite));
  }
  
  deleteSpesa(id) {
    const spese = this.getSpese().filter(s => s.id !== id);
    localStorage.setItem('spese', JSON.stringify(spese));
  }
  
  resetPeriodo(periodo) {
    const now = new Date();
    let dataInizio;
    
    if (periodo === 'oggi') {
      dataInizio = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    } else if (periodo === 'settimana') {
      dataInizio = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
    } else if (periodo === 'mese') {
      dataInizio = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
    }
    
    const vendite = this.getVendite().filter(v => new Date(v.timestamp) < dataInizio);
    const spese = this.getSpese().filter(s => new Date(s.timestamp) < dataInizio);
    
    localStorage.setItem('vendite', JSON.stringify(vendite));
    localStorage.setItem('spese', JSON.stringify(spese));
  }
}

const db = new LocalDB();

// ==================== COMPONENTI ====================

const Header = ({ reportPendente, giorniPassati }) => {
  return (
    <div className="header-wood sticky top-0 z-40">
      <div className="max-w-7xl mx-auto px-4 py-4">
        <div className="flex items-center justify-center gap-3">
          <h1 className="text-2xl md:text-3xl font-bold text-amber-100 text-center drop-shadow-lg" style={{fontFamily: "'Georgia', serif"}}>
            ☕ {CONFIG.nome_bar}
          </h1>
          {reportPendente ? (
            <div className="relative group cursor-pointer">
              <span className="inline-flex items-center justify-center w-4 h-4 bg-orange-500 rounded-full animate-pulse" title={`Report in attesa (${giorniPassati} giorni)`}></span>
            </div>
          ) : (
            <div className="relative group">
              <span className="inline-flex items-center justify-center w-4 h-4 bg-green-500 rounded-full" title="Report OK"></span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

const Feedback = ({ message }) => {
  if (!message) return null;
  return (
    <div className="fixed top-32 left-1/2 transform -translate-x-1/2 z-50 animate-bounce">
      <div className="bg-amber-100 text-amber-900 px-8 py-4 rounded-2xl shadow-2xl text-xl font-bold border-4 border-amber-800">
        {message}
      </div>
    </div>
  );
};

const Navigation = ({ view, setView }) => {
  const navItems = [
    { id: 'vendita', icon: '🛒', label: 'Vendita' },
    { id: 'spese', icon: '💸', label: 'Spese' },
    { id: 'statistiche', icon: '📊', label: 'Stats' },
    { id: 'storico', icon: '📋', label: 'Storico' },
    { id: 'impostazioni', icon: '⚙️', label: 'Imposta.' }
  ];
  
  return (
    <div className="nav-wood fixed bottom-0 left-0 right-0 z-40 pb-safe">
      <div className="max-w-7xl mx-auto px-2 py-2">
        <div className="grid grid-cols-5 gap-1">
          {navItems.map(item => (
            <button
              key={item.id}
              onClick={() => setView(item.id)}
              data-testid={`nav-${item.id}`}
              className={`flex flex-col items-center justify-center py-2 px-1 rounded-xl font-semibold transition-all ${
                view === item.id 
                  ? 'btn-wood-active shadow-lg' 
                  : 'btn-wood-inactive hover:bg-amber-800/50'
              }`}
            >
              <span className="text-xl mb-1">{item.icon}</span>
              <span className="text-xs capitalize">{item.label}</span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
};

const Keypad = ({ selectedProduct, customPrice, setCustomPrice, onClose, onConfirm, onClear }) => {
  const handleKeyPress = (key) => {
    if (key === ',') {
      if (!customPrice.includes(',')) setCustomPrice(customPrice + ',');
    } else if (key === '←') {
      setCustomPrice(customPrice.slice(0, -1));
    } else {
      if (customPrice.includes(',')) {
        const parts = customPrice.split(',');
        if (parts[1] && parts[1].length >= 2) return;
      }
      setCustomPrice(customPrice + key);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="card-felt rounded-3xl shadow-2xl max-w-md w-full p-6 border-4 border-amber-800">
        <div className="text-center mb-6">
          <div className="text-5xl mb-3">{selectedProduct?.icona || '💰'}</div>
          <h3 className="text-2xl font-bold text-amber-100 mb-2">{selectedProduct?.nome || ''}</h3>
          <p className="text-amber-200/70">Inserisci l'importo</p>
        </div>
        
        <div className="bg-amber-900/50 rounded-2xl p-6 mb-6 border-2 border-amber-700">
          <div className="text-center">
            <div className="text-5xl font-black text-amber-100">€{customPrice || '0'}</div>
          </div>
        </div>
        
        <div className="grid grid-cols-3 gap-3 mb-6">
          {['1','2','3','4','5','6','7','8','9',',','0','←'].map(key => (
            <button
              key={key}
              onClick={() => handleKeyPress(key)}
              data-testid={`keypad-${key}`}
              className="btn-wood text-amber-100 text-3xl font-bold p-6 rounded-2xl shadow-lg active:scale-95 transform transition-all"
            >
              {key}
            </button>
          ))}
        </div>
        
        <div className="grid grid-cols-2 gap-3">
          <button 
            onClick={onClose}
            data-testid="keypad-cancel"
            className="bg-red-800 hover:bg-red-700 text-amber-100 font-bold py-4 px-6 rounded-2xl shadow-lg active:scale-95 transform transition-all text-lg border-2 border-red-600"
          >
            ❌ Annulla
          </button>
          <button 
            onClick={onConfirm}
            data-testid="keypad-confirm"
            className="bg-green-800 hover:bg-green-700 text-amber-100 font-bold py-4 px-6 rounded-2xl shadow-lg active:scale-95 transform transition-all text-lg border-2 border-green-600"
          >
            ✅ Conferma
          </button>
        </div>
        
        <button 
          onClick={onClear}
          data-testid="keypad-clear"
          className="w-full mt-3 bg-amber-700 hover:bg-amber-600 text-amber-100 font-bold py-3 px-6 rounded-2xl shadow-lg active:scale-95 transform transition-all border-2 border-amber-600"
        >
          🗑️ Cancella Tutto
        </button>
      </div>
    </div>
  );
};

const ResetModal = ({ periodo, resetPassword, setResetPassword, onClose, onConfirm }) => {
  return (
    <div className="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-gradient-to-br from-red-900 to-red-950 rounded-3xl shadow-2xl max-w-md w-full p-8 border-4 border-red-500">
        <div className="text-center mb-6">
          <div className="text-6xl mb-4">⚠️</div>
          <h3 className="text-3xl font-bold text-white mb-2">Reset Periodo</h3>
          <p className="text-red-200">Elimina tutti i dati del periodo: <span className="font-bold">{periodo}</span></p>
        </div>
        
        <div className="bg-white/10 rounded-2xl p-6 mb-6 border-2 border-red-400">
          <label className="block text-white font-semibold mb-3 text-center">Inserisci Password</label>
          <input 
            type="password" 
            value={resetPassword}
            onChange={(e) => setResetPassword(e.target.value)}
            placeholder="Password" 
            maxLength="4"
            data-testid="reset-password-input"
            className="w-full px-6 py-4 bg-white/20 text-white text-center text-2xl font-bold rounded-xl border-2 border-white/30 focus:border-white focus:outline-none placeholder-white/50"
          />
          <p className="text-xs text-red-200 mt-3 text-center">⚠️ Questa azione è irreversibile!</p>
        </div>
        
        <div className="grid grid-cols-2 gap-3">
          <button 
            onClick={onClose}
            data-testid="reset-cancel"
            className="bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white font-bold py-4 px-6 rounded-2xl shadow-lg active:scale-95 transform transition-all"
          >
            Annulla
          </button>
          <button 
            onClick={onConfirm}
            data-testid="reset-confirm"
            className="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-bold py-4 px-6 rounded-2xl shadow-lg active:scale-95 transform transition-all"
          >
            🗑️ Reset
          </button>
        </div>
      </div>
    </div>
  );
};

const VenditaView = ({ onProductClick, showFeedback }) => {
  const prodotti = db.getProdotti();
  const grouped = {};
  prodotti.forEach(p => {
    if (!grouped[p.categoria]) grouped[p.categoria] = [];
    grouped[p.categoria].push(p);
  });
  
  const categoriaColors = {
    'CAFFETTERIA': 'cat-caffetteria',
    'BEVANDE': 'cat-bevande',
    'GELATI': 'cat-gelati',
    'PERSONALIZZATE': 'cat-personalizzate'
  };
  
  const categoriaIcons = {
    'CAFFETTERIA': '☕',
    'BEVANDE': '🍷',
    'GELATI': '🍦',
    'PERSONALIZZATE': '💰'
  };
  
  return (
    <div className="space-y-8" data-testid="vendita-view">
      {Object.entries(grouped).map(([categoria, items]) => (
        <div key={categoria} className="space-y-4">
          <div className="flex items-center gap-3 mb-4">
            <div className={`${categoriaColors[categoria]} p-3 rounded-xl text-4xl shadow-lg border-2 border-amber-900/50`}>
              {categoriaIcons[categoria]}
            </div>
            <h2 className="text-2xl font-bold text-amber-100 drop-shadow-lg" style={{fontFamily: "'Georgia', serif"}}>{categoria}</h2>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            {items.map(prodotto => (
              <button
                key={prodotto.id}
                onClick={() => onProductClick(prodotto)}
                data-testid={`product-${prodotto.nome.replace(/\s+/g, '-').toLowerCase()}`}
                className={`${categoriaColors[prodotto.categoria]} text-amber-100 p-6 rounded-2xl shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all duration-200 active:scale-95 border-2 border-amber-900/30`}
              >
                <div className="text-4xl mb-3">{prodotto.icona}</div>
                <div className="font-bold text-lg mb-2 leading-tight">{prodotto.nome}</div>
                <div className="text-2xl font-black">
                  {prodotto.prezzo === 0 ? '💰 Inserisci' : `€${prodotto.prezzo.toFixed(2)}`}
                </div>
              </button>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
};

const SpeseView = ({ onSpesaClick, showFeedback, refresh }) => {
  const [spese, setSpese] = useState([]);
  
  useEffect(() => {
    setSpese(db.getSpese().slice(0, 10));
  }, [refresh]);
  
  const handleDelete = (id) => {
    if (window.confirm('Eliminare questa spesa?')) {
      db.deleteSpesa(id);
      setSpese(db.getSpese().slice(0, 10));
      showFeedback('✅ Eliminata');
    }
  };
  
  return (
    <div className="space-y-6" data-testid="spese-view">
      <div className="card-felt p-6 rounded-3xl border-4 border-amber-800">
        <h2 className="text-2xl font-bold text-amber-100 mb-6 text-center" style={{fontFamily: "'Georgia', serif"}}>💸 Registra Spese</h2>
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          {CONFIG.categorie_spese.map(cat => (
            <button
              key={cat.nome}
              onClick={() => onSpesaClick(cat.nome, cat.icona)}
              data-testid={`spesa-${cat.nome.replace(/\s+/g, '-').toLowerCase()}`}
              className={`${cat.colore} text-amber-100 p-8 rounded-2xl shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all active:scale-95 border-2 border-amber-900/30`}
            >
              <div className="text-5xl mb-3">{cat.icona}</div>
              <div className="font-bold text-lg">{cat.nome}</div>
            </button>
          ))}
        </div>
      </div>
      
      <div className="card-felt p-6 rounded-3xl border-4 border-amber-800">
        <div className="flex justify-between items-center mb-6">
          <h3 className="text-2xl font-bold text-amber-100" style={{fontFamily: "'Georgia', serif"}}>📋 Ultime Spese</h3>
          <button 
            onClick={() => setSpese(db.getSpese().slice(0, 10))}
            className="btn-wood text-amber-100 px-4 py-2 rounded-xl transition-all"
          >
            🔄 Aggiorna
          </button>
        </div>
        
        {spese.length === 0 ? (
          <div className="text-amber-200/70 text-center py-8">Nessuna spesa registrata</div>
        ) : (
          <div className="space-y-2 max-h-96 overflow-y-auto hide-scrollbar">
            {spese.map(s => {
              const dt = new Date(s.timestamp);
              return (
                <div key={s.id} className="bg-amber-900/30 p-4 rounded-xl flex justify-between items-center hover:bg-amber-900/50 transition-all border border-amber-800/50">
                  <div className="text-amber-100">
                    <div className="font-bold text-lg">{s.categoria_spesa}</div>
                    <div className="text-sm text-amber-200/70">
                      {dt.toLocaleDateString('it-IT')} - {dt.toLocaleTimeString('it-IT')}
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    <div className="text-2xl font-black text-red-400">-€{s.importo.toFixed(2)}</div>
                    <button 
                      onClick={() => handleDelete(s.id)}
                      data-testid={`delete-spesa-${s.id}`}
                      className="text-red-400 hover:text-red-300 p-2 hover:bg-red-500/20 rounded-lg transition-all"
                    >
                      🗑️
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};

const StatisticheView = ({ periodo, setPeriodo, openResetModal }) => {
  const calcolaStatistiche = (per) => {
    const now = new Date();
    let dataInizio;
    
    if (per === 'oggi') {
      dataInizio = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    } else if (per === 'settimana') {
      dataInizio = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
    } else {
      dataInizio = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
    }
    
    const vendite = db.getVendite().filter(v => new Date(v.timestamp) >= dataInizio);
    const spese = db.getSpese().filter(s => new Date(s.timestamp) >= dataInizio);
    
    const totaleVendite = vendite.length;
    const totaleIncasso = vendite.reduce((sum, v) => sum + v.prezzo, 0);
    const totaleSpese = spese.reduce((sum, s) => sum + s.importo, 0);
    const profittoNetto = totaleIncasso - totaleSpese;
    
    const perCategoria = {};
    vendite.forEach(v => {
      if (!perCategoria[v.categoria]) {
        perCategoria[v.categoria] = {vendite: 0, incasso: 0};
      }
      perCategoria[v.categoria].vendite++;
      perCategoria[v.categoria].incasso += v.prezzo;
    });
    
    const prodottiCount = {};
    vendite.forEach(v => {
      if (!prodottiCount[v.prodotto_id]) {
        prodottiCount[v.prodotto_id] = {
          nome: v.nome_prodotto,
          count: 0,
          incasso: 0
        };
      }
      prodottiCount[v.prodotto_id].count++;
      prodottiCount[v.prodotto_id].incasso += v.prezzo;
    });
    
    const prodottiPiuVenduti = Object.values(prodottiCount)
      .sort((a, b) => b.count - a.count)
      .slice(0, 5)
      .map(p => ({
        nome: p.nome,
        quantita: p.count,
        incasso: p.incasso
      }));
    
    return {
      totaleVendite,
      totaleIncasso,
      totaleSpese,
      profittoNetto,
      perCategoria,
      prodottiPiuVenduti,
      periodoLabel: per === 'oggi' ? 'Oggi' : per === 'settimana' ? 'Ultimi 7 giorni' : 'Ultimi 30 giorni'
    };
  };
  
  const stats = calcolaStatistiche(periodo);
  
  return (
    <div className="space-y-6" data-testid="statistiche-view">
      <div className="grid grid-cols-3 gap-2">
        {['oggi', 'settimana', 'mese'].map(p => (
          <button
            key={p}
            onClick={() => setPeriodo(p)}
            data-testid={`periodo-${p}`}
            className={`px-3 py-2 rounded-xl font-semibold transition-all text-sm ${
              periodo === p 
                ? 'btn-wood-active shadow-lg' 
                : 'btn-wood-inactive hover:bg-amber-800/50'
            }`}
          >
            {p === 'oggi' ? '📅 Oggi' : p === 'settimana' ? '📆 Sett.' : '🗓️ Mese'}
          </button>
        ))}
      </div>
      
      <div className="grid md:grid-cols-3 gap-6">
        <div className="stat-card-green p-8 rounded-3xl shadow-2xl text-amber-100 border-4 border-green-900">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xl font-semibold">Incasso Totale</h3>
            <span className="text-3xl">📈</span>
          </div>
          <div className="text-5xl font-black" data-testid="totale-incasso">€{stats.totaleIncasso.toFixed(2)}</div>
          <div className="text-green-200/70 mt-2">{stats.periodoLabel}</div>
        </div>
        
        <div className="stat-card-red p-8 rounded-3xl shadow-2xl text-amber-100 border-4 border-red-900">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xl font-semibold">Spese Totali</h3>
            <span className="text-3xl">📉</span>
          </div>
          <div className="text-5xl font-black" data-testid="totale-spese">€{stats.totaleSpese.toFixed(2)}</div>
          <div className="text-red-200/70 mt-2">{stats.periodoLabel}</div>
        </div>
        
        <div className={`${stats.profittoNetto >= 0 ? 'stat-card-blue' : 'stat-card-orange'} p-8 rounded-3xl shadow-2xl text-amber-100 border-4 ${stats.profittoNetto >= 0 ? 'border-blue-900' : 'border-orange-900'}`}>
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xl font-semibold">Profitto Netto</h3>
            <span className="text-3xl">💰</span>
          </div>
          <div className="text-5xl font-black" data-testid="profitto-netto">€{stats.profittoNetto.toFixed(2)}</div>
          <div className="text-blue-200/70 mt-2">{stats.totaleVendite} vendite</div>
        </div>
      </div>
      
      <div className="card-felt p-6 rounded-3xl border-4 border-amber-800">
        <h3 className="text-2xl font-bold text-amber-100 mb-6" style={{fontFamily: "'Georgia', serif"}}>📊 Per Categoria</h3>
        <div className="grid md:grid-cols-3 gap-4">
          {Object.entries(stats.perCategoria).map(([cat, data]) => (
            <div key={cat} className="bg-amber-800/50 p-6 rounded-2xl text-amber-100 border-2 border-amber-700">
              <div className="text-lg font-semibold mb-2">{cat}</div>
              <div className="text-3xl font-black mb-1">€{data.incasso.toFixed(2)}</div>
              <div className="text-sm text-amber-200/70">{data.vendite} vendite</div>
            </div>
          ))}
        </div>
      </div>
      
      <div className="card-felt p-6 rounded-3xl border-4 border-amber-800">
        <h3 className="text-2xl font-bold text-amber-100 mb-6" style={{fontFamily: "'Georgia', serif"}}>🏆 Top 5 Prodotti</h3>
        <div className="space-y-3">
          {stats.prodottiPiuVenduti.length > 0 ? stats.prodottiPiuVenduti.map((prod, idx) => (
            <div key={idx} className="bg-amber-900/30 p-4 rounded-xl flex justify-between items-center border border-amber-800/50">
              <div className="flex items-center gap-4">
                <div className="text-3xl font-black text-yellow-400">#{idx + 1}</div>
                <div className="text-amber-100">
                  <div className="font-bold text-lg">{prod.nome}</div>
                  <div className="text-sm text-amber-200/70">{prod.quantita} vendite</div>
                </div>
              </div>
              <div className="text-2xl font-black text-amber-100">€{prod.incasso.toFixed(2)}</div>
            </div>
          )) : (
            <div className="text-amber-200/50 text-center py-4">Nessuna vendita nel periodo</div>
          )}
        </div>
      </div>
      
      <button 
        onClick={openResetModal}
        data-testid="reset-periodo-btn"
        className="w-full btn-wood text-amber-100 py-4 rounded-2xl font-bold text-lg shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all flex items-center justify-center gap-3 border-2 border-red-700"
      >
        🗑️ 🔒 Reset Periodo (Password Richiesta)
      </button>
    </div>
  );
};

const StoricoView = ({ showFeedback, refresh }) => {
  const [transazioni, setTransazioni] = useState([]);
  
  const getGiorniDaUltimoReport = () => {
    const ultimoReport = localStorage.getItem('ultimoResocontoInviato');
    if (!ultimoReport) return Infinity;
    
    const dataUltimo = new Date(ultimoReport);
    const now = new Date();
    const diffMs = now - dataUltimo;
    const diffGiorni = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    return diffGiorni;
  };
  
  const getDataUltimoReport = () => {
    const ultimoReport = localStorage.getItem('ultimoResocontoInviato');
    if (!ultimoReport) return 'Mai inviato';
    
    const data = new Date(ultimoReport);
    return data.toLocaleDateString('it-IT', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };
  
  const isReportPendente = () => {
    if (!CONFIG.invio_resoconto_automatico) return false;
    const giorniPassati = getGiorniDaUltimoReport();
    return giorniPassati >= CONFIG.giorni_minimo_tra_report;
  };
  
  useEffect(() => {
    const vendite = db.getVendite().slice(0, 100);
    const spese = db.getSpese().slice(0, 100);
    
    const tutteTransazioni = [
      ...vendite.map(v => ({...v, tipo: 'vendita'})),
      ...spese.map(s => ({...s, tipo: 'spesa', nome_prodotto: s.categoria_spesa, prezzo: -s.importo}))
    ].sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp)).slice(0, 100);
    
    setTransazioni(tutteTransazioni);
  }, [refresh]);
  
  const handleDelete = (id, tipo) => {
    if (window.confirm(`Eliminare questa ${tipo}?`)) {
      if (tipo === 'vendita') {
        db.deleteVendita(id);
      } else {
        db.deleteSpesa(id);
      }
      
      const vendite = db.getVendite().slice(0, 100);
      const spese = db.getSpese().slice(0, 100);
      
      const tutteTransazioni = [
        ...vendite.map(v => ({...v, tipo: 'vendita'})),
        ...spese.map(s => ({...s, tipo: 'spesa', nome_prodotto: s.categoria_spesa, prezzo: -s.importo}))
      ].sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp)).slice(0, 100);
      
      setTransazioni(tutteTransazioni);
      showFeedback('✅ Eliminata');
    }
  };
  
  const exportExcel = () => {
    showFeedback('📊 Export Excel in preparazione...');
    // In una vera implementazione qui useremmo SheetJS
    setTimeout(() => {
      showFeedback('✅ Excel pronto! (Demo)');
    }, 1000);
  };
  
  const reportPendente = isReportPendente();
  const giorniPassati = getGiorniDaUltimoReport();
  
  return (
    <div className="space-y-4" data-testid="storico-view">
      {/* Info Report */}
      <div className="card-felt p-4 rounded-2xl border-4 border-amber-800">
        <div className="flex items-center justify-between">
          <div className="text-amber-100">
            <div className="text-sm text-amber-200/70">📧 Ultimo report inviato:</div>
            <div className="font-bold">{getDataUltimoReport()}</div>
          </div>
          <div className="text-right">
            {reportPendente ? (
              <span className="inline-flex items-center gap-2 bg-orange-500/20 text-orange-300 px-3 py-1 rounded-full text-sm">
                <span className="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></span>
                Pendente ({giorniPassati}+ giorni)
              </span>
            ) : (
              <span className="inline-flex items-center gap-2 bg-green-500/20 text-green-300 px-3 py-1 rounded-full text-sm">
                <span className="w-2 h-2 bg-green-500 rounded-full"></span>
                Inviato
              </span>
            )}
          </div>
        </div>
        <div className="text-xs text-amber-200/50 mt-2">
          ⏰ Prossimo invio programmato: Lunedì alle 08:00 (se passati 7+ giorni)
        </div>
      </div>
      
      <button 
        onClick={exportExcel}
        data-testid="export-excel-btn"
        className="w-full bg-green-800 hover:bg-green-700 text-amber-100 py-4 rounded-2xl font-bold text-lg shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all flex items-center justify-center gap-3 border-2 border-green-600"
      >
        📊 Crea Resoconto Excel
      </button>
      
      <button 
        data-testid="invia-report-btn"
        className={`w-full bg-blue-800 hover:bg-blue-700 text-amber-100 py-3 rounded-2xl font-bold text-lg shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all flex items-center justify-center gap-3 border-2 border-blue-600 ${!reportPendente ? 'opacity-50' : ''}`}
      >
        📧 {reportPendente ? 'Invia Report Settimanale Ora' : 'Report già inviato questa settimana ✓'}
      </button>
      
      <button 
        data-testid="import-excel-btn"
        className="w-full bg-amber-900/50 hover:bg-amber-900/70 text-amber-200/70 hover:text-amber-100 py-2 rounded-xl text-sm transition-all flex items-center justify-center gap-2 border border-amber-700"
      >
        📥 Importa Dati da Excel
      </button>
      
      <div className="card-felt p-6 rounded-3xl border-4 border-amber-800">
        <h3 className="text-2xl font-bold text-amber-100 mb-6" style={{fontFamily: "'Georgia', serif"}}>📋 Ultime 100 Transazioni</h3>
        
        {transazioni.length === 0 ? (
          <div className="text-amber-200/70 text-center py-8">Nessuna transazione registrata</div>
        ) : (
          <div className="space-y-2 max-h-[600px] overflow-y-auto hide-scrollbar">
            {transazioni.map(t => {
              const dt = new Date(t.timestamp);
              const isSpesa = t.tipo === 'spesa';
              
              return (
                <div 
                  key={t.id}
                  className={`${isSpesa ? 'bg-red-900/30 border-red-700/50' : 'bg-amber-900/30 border-amber-700/50'} p-4 rounded-xl flex justify-between items-center hover:bg-amber-900/50 transition-all border`}
                >
                  <div className={isSpesa ? 'text-red-200' : 'text-amber-100'}>
                    <div className="flex items-center gap-2">
                      <span className="text-2xl">{isSpesa ? '💸' : '💰'}</span>
                      <div>
                        <div className="font-bold text-lg">{t.nome_prodotto}</div>
                        <div className="text-sm opacity-75">
                          {dt.toLocaleDateString('it-IT')} - {dt.toLocaleTimeString('it-IT')}
                        </div>
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    <div className={`text-2xl font-black ${isSpesa ? 'text-red-400' : 'text-green-400'}`}>
                      {isSpesa ? '-' : '+'}€{Math.abs(t.prezzo).toFixed(2)}
                    </div>
                    <button 
                      onClick={() => handleDelete(t.id, t.tipo)}
                      data-testid={`delete-transazione-${t.id}`}
                      className="text-red-400 hover:text-red-300 p-2 hover:bg-red-500/20 rounded-lg transition-all"
                    >
                      🗑️
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};

// ==================== APP PRINCIPALE ====================

function App() {
  const [view, setView] = useState('vendita');
  const [periodo, setPeriodo] = useState('oggi');
  const [showKeypad, setShowKeypad] = useState(false);
  const [showResetModal, setShowResetModal] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [selectedSpesaCategoria, setSelectedSpesaCategoria] = useState('');
  const [customPrice, setCustomPrice] = useState('');
  const [keypadMode, setKeypadMode] = useState('vendita');
  const [resetPassword, setResetPassword] = useState('');
  const [feedback, setFeedback] = useState('');
  const [refresh, setRefresh] = useState(0);
  
  const getGiorniDaUltimoReport = () => {
    const ultimoReport = localStorage.getItem('ultimoResocontoInviato');
    if (!ultimoReport) return Infinity;
    const dataUltimo = new Date(ultimoReport);
    const now = new Date();
    const diffMs = now - dataUltimo;
    return Math.floor(diffMs / (1000 * 60 * 60 * 24));
  };
  
  const isReportPendente = () => {
    if (!CONFIG.invio_resoconto_automatico) return false;
    return getGiorniDaUltimoReport() >= CONFIG.giorni_minimo_tra_report;
  };
  
  const showFeedback = (message) => {
    setFeedback(message);
    setTimeout(() => setFeedback(''), 2000);
  };
  
  const handleProductClick = (prodotto) => {
    if (prodotto.prezzo === 0 || prodotto.categoria === 'PERSONALIZZATE') {
      setSelectedProduct(prodotto);
      setCustomPrice('');
      setKeypadMode('vendita');
      setShowKeypad(true);
    } else {
      registraVendita(prodotto, prodotto.prezzo);
    }
  };
  
  const handleSpesaClick = (categoria, icona) => {
    setSelectedSpesaCategoria(categoria);
    setSelectedProduct({nome: categoria, icona: icona});
    setCustomPrice('');
    setKeypadMode('spesa');
    setShowKeypad(true);
  };
  
  const registraVendita = (prodotto, prezzo) => {
    const vendita = {
      id: Date.now().toString(36) + Math.random().toString(36).substr(2),
      prodotto_id: prodotto.id,
      nome_prodotto: prodotto.nome,
      prezzo: prezzo,
      categoria: prodotto.categoria,
      timestamp: new Date().toISOString()
    };
    
    db.addVendita(vendita);
    showFeedback(`✅ ${prodotto.nome} - €${prezzo.toFixed(2)}`);
    setRefresh(r => r + 1);
  };
  
  const registraSpesa = (categoria, importo) => {
    const spesa = {
      id: Date.now().toString(36) + Math.random().toString(36).substr(2),
      categoria_spesa: categoria,
      importo: importo,
      note: '',
      timestamp: new Date().toISOString()
    };
    
    db.addSpesa(spesa);
    showFeedback(`✅ Spesa: ${categoria} - €${importo.toFixed(2)}`);
    setRefresh(r => r + 1);
  };
  
  const closeKeypad = () => {
    setShowKeypad(false);
    setCustomPrice('');
    setSelectedProduct(null);
  };
  
  const confirmKeypad = () => {
    const prezzo = parseFloat(customPrice.replace(',', '.'));
    if (isNaN(prezzo) || prezzo <= 0) {
      showFeedback('❌ Importo non valido!');
      return;
    }
    
    setShowKeypad(false);
    
    if (keypadMode === 'vendita') {
      registraVendita(selectedProduct, prezzo);
    } else {
      registraSpesa(selectedSpesaCategoria, prezzo);
    }
    
    setCustomPrice('');
    setSelectedProduct(null);
  };
  
  const clearKeypad = () => {
    setCustomPrice('');
  };
  
  const openResetModal = () => {
    setShowResetModal(true);
    setResetPassword('');
  };
  
  const closeResetModal = () => {
    setShowResetModal(false);
    setResetPassword('');
  };
  
  const confirmReset = () => {
    if (resetPassword !== CONFIG.password_reset) {
      showFeedback('❌ Password errata!');
      return;
    }
    
    if (!window.confirm(`Eliminare TUTTI i dati del periodo "${periodo}"?`)) return;
    
    db.resetPeriodo(periodo);
    setShowResetModal(false);
    setResetPassword('');
    showFeedback('✅ Reset completato!');
    setRefresh(r => r + 1);
  };
  
  return (
    <div className="App bg-felt min-h-screen">
      <Header 
        reportPendente={isReportPendente()} 
        giorniPassati={getGiorniDaUltimoReport()} 
      />
      
      <Feedback message={feedback} />
      
      <div className="max-w-7xl mx-auto px-4 py-6 pb-28">
        {view === 'vendita' && (
          <VenditaView 
            onProductClick={handleProductClick}
            showFeedback={showFeedback}
          />
        )}
        {view === 'spese' && (
          <SpeseView 
            onSpesaClick={handleSpesaClick}
            showFeedback={showFeedback}
            refresh={refresh}
          />
        )}
        {view === 'statistiche' && (
          <StatisticheView 
            periodo={periodo}
            setPeriodo={setPeriodo}
            openResetModal={openResetModal}
          />
        )}
        {view === 'storico' && (
          <StoricoView 
            showFeedback={showFeedback}
            refresh={refresh}
          />
        )}
        {view === 'impostazioni' && (
          <ImpostazioniView 
            showFeedback={showFeedback}
            refresh={refresh}
            setRefresh={setRefresh}
          />
        )}
      </div>
      
      <Navigation view={view} setView={setView} />
      
      {showKeypad && (
        <Keypad 
          selectedProduct={selectedProduct}
          customPrice={customPrice}
          setCustomPrice={setCustomPrice}
          onClose={closeKeypad}
          onConfirm={confirmKeypad}
          onClear={clearKeypad}
        />
      )}
      
      {showResetModal && (
        <ResetModal 
          periodo={periodo}
          resetPassword={resetPassword}
          setResetPassword={setResetPassword}
          onClose={closeResetModal}
          onConfirm={confirmReset}
        />
      )}
    </div>
  );
}

export default App;
