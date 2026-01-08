import { useState, useEffect } from 'react';
import '@/App.css';
import axios from 'axios';
import { Coffee, Wine, IceCream, TrendingUp, Calendar, Download, Trash2 } from 'lucide-react';

const BACKEND_URL = process.env.REACT_APP_BACKEND_URL;
const API = `${BACKEND_URL}/api`;

function App() {
  const [prodotti, setProdotti] = useState([]);
  const [vendite, setVendite] = useState([]);
  const [statistiche, setStatistiche] = useState(null);
  const [view, setView] = useState('vendita'); // 'vendita', 'statistiche', 'storico'
  const [loading, setLoading] = useState(false);
  const [feedback, setFeedback] = useState('');
  const [periodo, setPeriodo] = useState('oggi'); // 'oggi', 'settimana', 'mese'
  
  // Stati per tastierino
  const [showKeypad, setShowKeypad] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [customPrice, setCustomPrice] = useState('');

  useEffect(() => {
    caricaProdotti();
    caricaStatistiche('oggi');
  }, []);

  const caricaProdotti = async () => {
    try {
      const response = await axios.get(`${API}/prodotti`);
      setProdotti(response.data);
    } catch (error) {
      console.error('Errore caricamento prodotti:', error);
    }
  };

  const caricaStatistiche = async (per) => {
    try {
      const response = await axios.get(`${API}/statistiche/${per}`);
      setStatistiche(response.data);
    } catch (error) {
      console.error('Errore caricamento statistiche:', error);
    }
  };

  const caricaStorico = async () => {
    try {
      const response = await axios.get(`${API}/vendite?limite=100`);
      setVendite(response.data);
    } catch (error) {
      console.error('Errore caricamento storico:', error);
    }
  };

  const registraVendita = async (prodotto, prezzoPersonalizzato = null) => {
    setLoading(true);
    try {
      const payload = { prodotto_id: prodotto.id };
      if (prezzoPersonalizzato !== null) {
        payload.prezzo_personalizzato = prezzoPersonalizzato;
      }
      
      await axios.post(`${API}/vendite`, payload);
      const prezzoFinale = prezzoPersonalizzato !== null ? prezzoPersonalizzato : prodotto.prezzo;
      setFeedback(`✅ ${prodotto.nome} - €${prezzoFinale.toFixed(2)}`);
      setTimeout(() => setFeedback(''), 2000);
      // Ricarica statistiche
      caricaStatistiche(periodo);
    } catch (error) {
      setFeedback('❌ Errore!');
      console.error('Errore registrazione vendita:', error);
    }
    setLoading(false);
  };

  const handleProductClick = (prodotto) => {
    // Se è un prodotto personalizzabile (prezzo 0), apri tastierino
    if (prodotto.prezzo === 0 || prodotto.categoria === 'PERSONALIZZATE') {
      setSelectedProduct(prodotto);
      setCustomPrice('');
      setShowKeypad(true);
    } else {
      // Altrimenti registra vendita normale
      registraVendita(prodotto);
    }
  };

  const handleKeypadPress = (value) => {
    if (value === 'C') {
      setCustomPrice('');
    } else if (value === ',') {
      if (!customPrice.includes(',')) {
        setCustomPrice(customPrice + ',');
      }
    } else if (value === '←') {
      setCustomPrice(customPrice.slice(0, -1));
    } else {
      // Limita a 2 decimali
      if (customPrice.includes(',')) {
        const parts = customPrice.split(',');
        if (parts[1] && parts[1].length >= 2) return;
      }
      setCustomPrice(customPrice + value);
    }
  };

  const handleKeypadConfirm = () => {
    const prezzo = parseFloat(customPrice.replace(',', '.'));
    if (isNaN(prezzo) || prezzo <= 0) {
      setFeedback('❌ Inserisci un prezzo valido!');
      setTimeout(() => setFeedback(''), 2000);
      return;
    }
    
    setShowKeypad(false);
    registraVendita(selectedProduct, prezzo);
    setCustomPrice('');
    setSelectedProduct(null);
  };

  const handleKeypadCancel = () => {
    setShowKeypad(false);
    setCustomPrice('');
    setSelectedProduct(null);
  };

  const eliminaVendita = async (venditaId) => {
    if (!window.confirm('Eliminare questa vendita?')) return;
    try {
      await axios.delete(`${API}/vendite/${venditaId}`);
      caricaStorico();
      caricaStatistiche(periodo);
      setFeedback('✅ Vendita eliminata');
      setTimeout(() => setFeedback(''), 2000);
    } catch (error) {
      console.error('Errore eliminazione vendita:', error);
    }
  };

  const esportaCSV = () => {
    window.open(`${API}/export/csv`, '_blank');
  };

  const raggruppaProdottiPerCategoria = () => {
    const grouped = {};
    prodotti.forEach(p => {
      if (!grouped[p.categoria]) grouped[p.categoria] = [];
      grouped[p.categoria].push(p);
    });
    return grouped;
  };

  const getCategoriaColor = (categoria) => {
    const colors = {
      'CAFFETTERIA': 'from-amber-500 to-orange-600',
      'BEVANDE': 'from-purple-500 to-pink-600',
      'GELATI': 'from-cyan-500 to-blue-600'
    };
    return colors[categoria] || 'from-gray-500 to-gray-600';
  };

  const getCategoriaIcon = (categoria) => {
    const icons = {
      'CAFFETTERIA': <Coffee className="w-8 h-8" />,
      'BEVANDE': <Wine className="w-8 h-8" />,
      'GELATI': <IceCream className="w-8 h-8" />
    };
    return icons[categoria] || null;
  };

  const prodottiRaggruppati = raggruppaProdottiPerCategoria();

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
      {/* Header */}
      <div className="bg-white/10 backdrop-blur-lg border-b border-white/20 sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 py-4">
          <h1 className="text-2xl md:text-3xl font-bold text-white text-center mb-4">
            ☕ Proloco Santa Bianca
          </h1>
          
          {/* Navigation */}
          <div className="flex gap-2 justify-center flex-wrap">
            <button
              onClick={() => setView('vendita')}
              className={`px-6 py-3 rounded-xl font-semibold transition-all ${
                view === 'vendita'
                  ? 'bg-white text-purple-900 shadow-lg scale-105'
                  : 'bg-white/20 text-white hover:bg-white/30'
              }`}
              data-testid="tab-vendita"
            >
              🛒 Vendita
            </button>
            <button
              onClick={() => {
                setView('statistiche');
                caricaStatistiche(periodo);
              }}
              className={`px-6 py-3 rounded-xl font-semibold transition-all ${
                view === 'statistiche'
                  ? 'bg-white text-purple-900 shadow-lg scale-105'
                  : 'bg-white/20 text-white hover:bg-white/30'
              }`}
              data-testid="tab-statistiche"
            >
              📊 Statistiche
            </button>
            <button
              onClick={() => {
                setView('storico');
                caricaStorico();
              }}
              className={`px-6 py-3 rounded-xl font-semibold transition-all ${
                view === 'storico'
                  ? 'bg-white text-purple-900 shadow-lg scale-105'
                  : 'bg-white/20 text-white hover:bg-white/30'
              }`}
              data-testid="tab-storico"
            >
              📋 Storico
            </button>
          </div>
        </div>
      </div>

      {/* Feedback Toast */}
      {feedback && (
        <div className="fixed top-24 left-1/2 transform -translate-x-1/2 z-50 animate-bounce" data-testid="feedback-toast">
          <div className="bg-white text-gray-900 px-8 py-4 rounded-2xl shadow-2xl text-xl font-bold">
            {feedback}
          </div>
        </div>
      )}

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 py-6 pb-20">
        {/* VISTA VENDITA */}
        {view === 'vendita' && (
          <div className="space-y-8">
            {Object.entries(prodottiRaggruppati).map(([categoria, items]) => (
              <div key={categoria} className="space-y-4">
                <div className="flex items-center gap-3 mb-4">
                  <div className={`bg-gradient-to-r ${getCategoriaColor(categoria)} p-3 rounded-xl`}>
                    {getCategoriaIcon(categoria)}
                  </div>
                  <h2 className="text-2xl font-bold text-white">{categoria}</h2>
                </div>
                
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                  {items.map((prodotto) => (
                    <button
                      key={prodotto.id}
                      onClick={() => handleProductClick(prodotto)}
                      disabled={loading}
                      className={`bg-gradient-to-r ${getCategoriaColor(categoria)} text-white p-6 rounded-2xl shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all duration-200 active:scale-95 disabled:opacity-50`}
                      data-testid={`prodotto-${prodotto.nome.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`}
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
        )}

        {/* VISTA STATISTICHE */}
        {view === 'statistiche' && statistiche && (
          <div className="space-y-6">
            {/* Selettore Periodo */}
            <div className="flex gap-2 justify-center flex-wrap">
              {['oggi', 'settimana', 'mese'].map((p) => (
                <button
                  key={p}
                  onClick={() => {
                    setPeriodo(p);
                    caricaStatistiche(p);
                  }}
                  className={`px-6 py-3 rounded-xl font-semibold transition-all ${
                    periodo === p
                      ? 'bg-white text-purple-900 shadow-lg'
                      : 'bg-white/20 text-white hover:bg-white/30'
                  }`}
                  data-testid={`periodo-${p}`}
                >
                  {p === 'oggi' && '📅 Oggi'}
                  {p === 'settimana' && '📆 Settimana'}
                  {p === 'mese' && '🗓️ Mese'}
                </button>
              ))}
            </div>

            {/* Card Principali */}
            <div className="grid md:grid-cols-2 gap-6">
              <div className="bg-gradient-to-br from-green-500 to-emerald-600 p-8 rounded-3xl shadow-2xl text-white">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-xl font-semibold">Incasso Totale</h3>
                  <TrendingUp className="w-8 h-8" />
                </div>
                <div className="text-5xl font-black" data-testid="totale-incasso">€{statistiche.totale_incasso.toFixed(2)}</div>
                <div className="text-green-100 mt-2">{statistiche.periodo}</div>
              </div>

              <div className="bg-gradient-to-br from-blue-500 to-indigo-600 p-8 rounded-3xl shadow-2xl text-white">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-xl font-semibold">Vendite Totali</h3>
                  <Calendar className="w-8 h-8" />
                </div>
                <div className="text-5xl font-black" data-testid="totale-vendite">{statistiche.totale_vendite}</div>
                <div className="text-blue-100 mt-2">{statistiche.periodo}</div>
              </div>
            </div>

            {/* Per Categoria */}
            <div className="bg-white/10 backdrop-blur-lg p-6 rounded-3xl border border-white/20">
              <h3 className="text-2xl font-bold text-white mb-6">📊 Per Categoria</h3>
              <div className="grid md:grid-cols-3 gap-4">
                {Object.entries(statistiche.per_categoria).map(([cat, data]) => (
                  <div key={cat} className={`bg-gradient-to-br ${getCategoriaColor(cat)} p-6 rounded-2xl text-white`}>
                    <div className="text-lg font-semibold mb-2">{cat}</div>
                    <div className="text-3xl font-black mb-1">€{data.incasso.toFixed(2)}</div>
                    <div className="text-sm opacity-90">{data.vendite} vendite</div>
                  </div>
                ))}
              </div>
            </div>

            {/* Prodotti Più Venduti */}
            <div className="bg-white/10 backdrop-blur-lg p-6 rounded-3xl border border-white/20">
              <h3 className="text-2xl font-bold text-white mb-6">🏆 Top 5 Prodotti</h3>
              <div className="space-y-3">
                {statistiche.prodotti_piu_venduti.map((prod, idx) => (
                  <div key={idx} className="bg-white/10 p-4 rounded-xl flex justify-between items-center">
                    <div className="flex items-center gap-4">
                      <div className="text-3xl font-black text-yellow-400">#{idx + 1}</div>
                      <div className="text-white">
                        <div className="font-bold text-lg">{prod.nome}</div>
                        <div className="text-sm opacity-75">{prod.quantita} vendite</div>
                      </div>
                    </div>
                    <div className="text-2xl font-black text-white">€{prod.incasso.toFixed(2)}</div>
                  </div>
                ))}
              </div>
            </div>

            {/* Export Button */}
            <button
              onClick={esportaCSV}
              className="w-full bg-gradient-to-r from-orange-500 to-red-600 text-white py-4 rounded-2xl font-bold text-lg shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all flex items-center justify-center gap-3"
              data-testid="export-csv-btn"
            >
              <Download className="w-6 h-6" />
              Esporta CSV
            </button>
          </div>
        )}

        {/* VISTA STORICO */}
        {view === 'storico' && (
          <div className="space-y-4">
            <div className="bg-white/10 backdrop-blur-lg p-6 rounded-3xl border border-white/20">
              <h3 className="text-2xl font-bold text-white mb-6">📋 Ultime 100 Vendite</h3>
              
              {vendite.length === 0 ? (
                <div className="text-white text-center py-8 opacity-75">Nessuna vendita registrata</div>
              ) : (
                <div className="space-y-2 max-h-[600px] overflow-y-auto">
                  {vendite.map((v) => {
                    const dt = new Date(v.timestamp);
                    return (
                      <div key={v.id} className="bg-white/10 p-4 rounded-xl flex justify-between items-center hover:bg-white/20 transition-all">
                        <div className="text-white">
                          <div className="font-bold text-lg">{v.nome_prodotto}</div>
                          <div className="text-sm opacity-75">
                            {dt.toLocaleDateString('it-IT')} - {dt.toLocaleTimeString('it-IT')}
                          </div>
                          <div className="text-xs opacity-50">{v.categoria}</div>
                        </div>
                        <div className="flex items-center gap-4">
                          <div className="text-2xl font-black text-white">€{v.prezzo.toFixed(2)}</div>
                          <button
                            onClick={() => eliminaVendita(v.id)}
                            className="text-red-400 hover:text-red-300 p-2 hover:bg-red-500/20 rounded-lg transition-all"
                            data-testid={`delete-vendita-${v.id}`}
                          >
                            <Trash2 className="w-5 h-5" />
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

export default App;
