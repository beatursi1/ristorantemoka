// menu-events.js
(function () {
  // EventDelegator: centralizza delegazioni eventi (aggiungi bevanda, invia ordine ecc.)
  // Esponiamo window.EventDelegator con init() e destroy() per controllo e test.
  var bevandeHandler = null;
  var inviaHandler = null;
  var orderHandler = null;
  var capture = true;

  function instalarHandlers() {
    if (bevandeHandler || inviaHandler || orderHandler) return; // già installati

    bevandeHandler = function (e) {
      try {
        var target = e.target || e.srcElement;
        var btn = (typeof target.closest === 'function') ? target.closest('#btn-modal-aggiungi-bevanda') : (target.id === 'btn-modal-aggiungi-bevanda' ? target : null);
        if (!btn) return;
        if (typeof window.aggiungiBevandaAlCarrello === 'function') {
          try { window.aggiungiBevandaAlCarrello(); } catch (err) { console.error('errore in aggiungiBevandaAlCarrello:', err); }
        } else {
          console.warn('aggiungiBevandaAlCarrello non definita al momento del click');
        }
      } catch (err) {
        console.error('errore delegazione click #btn-modal-aggiungi-bevanda:', err);
      }
    };

    inviaHandler = function (e) {
      try {
        var target = e.target || e.srcElement;
        var btn = (typeof target.closest === 'function') ? target.closest('#btn-invia-ordine') : (target.id === 'btn-invia-ordine' ? target : null);
        if (!btn) return;
        try { if (btn.disabled) return; } catch (_) {}
        if (typeof window.inviaOrdine === 'function') {
          try { window.inviaOrdine(); } catch (err) { console.error('errore in inviaOrdine:', err); }
        } else {
          console.warn('inviaOrdine non definita al momento del click');
        }
      } catch (err) {
        console.error('errore delegazione click #btn-invia-ordine:', err);
      }
    };

    // Delegazione per pulsanti "Aggiungi" (.btn-ordina)
    orderHandler = function (e) {
      try {
        var target = e.target || e.srcElement;
        var btn = (typeof target.closest === 'function') ? target.closest('.btn-ordina') : (target.classList && target.classList.contains('btn-ordina') ? target : null);
        if (!btn) return;

        // Ignora pulsanti che non sono quelli dei piatti
        if (btn.id === 'btn-modal-aggiungi-bevanda' || btn.id === 'btn-apri-bevande-unico') return;

        // Leggi data-* se presenti
        var id = btn.getAttribute('data-id') || (btn.dataset && btn.dataset.id) || null;
        var nome = btn.getAttribute('data-nome') || (btn.dataset && btn.dataset.nome) || null;
        var prezzoAttr = btn.getAttribute('data-prezzo') || (btn.dataset && (btn.dataset.prezzo || btn.dataset.price)) || null;
        var prezzo = prezzoAttr ? parseFloat(String(prezzoAttr).replace(',', '.')) : NaN;

        // Fallback dal DOM se missing
        if ((!nome || nome === '') || isNaN(prezzo)) {
          try {
            // cerca il contenitore del piatto provando più selettori (lista bootstrap o wrapper personalizzato)
            var item = (typeof btn.closest === 'function') ? btn.closest('.piatto-item, .list-group-item') : null;
            if (item) {
              if (!nome) {
                var t = item.querySelector('h5') || item.querySelector('.fw-bold') || item.querySelector('div');
                if (t) nome = (t.textContent || t.innerText || '').trim();
              }
              if (isNaN(prezzo)) {
                // Prima prova selettori specifici che normalmente contengono il prezzo
                var p = item.querySelector('.text-success, .prezzo, .fw-semibold');
                // Se il risultato non contiene il simbolo €, cerchiamo un elemento interno il cui testo contenga "€"
                if (!p || !(/€/.test(p.textContent || ''))) {
                  var candidates = Array.from(item.querySelectorAll('*'));
                  p = candidates.find(function(el){
                    try { return /€\s*[\d.,]+/.test(el.textContent || ''); } catch(e){ return false; }
                  }) || null;
                }
                if (p) {
                  var m = (p.textContent || p.innerText || '').match(/€\s*([\d.,]+)/);
                  if (m && m[1]) {
                    prezzo = parseFloat(m[1].replace(',', '.'));
                  }
                }
              }
            }
          } catch (err) { /* ignore fallback parse errors */ }
        }

        if (isNaN(prezzo)) prezzo = 0;

        if (typeof window.aggiungereAlCarrello === 'function') {
          try {
            window.aggiungereAlCarrello(id, nome || '', prezzo);
          } catch (err) {
            console.error('errore chiamando aggiungereAlCarrello:', err);
          }
        } else {
          console.warn('aggiungereAlCarrello non definita al momento del click (btn-ordina)');
        }
      } catch (err) {
        console.error('errore delegazione click .btn-ordina:', err);
      }
    };

    document.body.addEventListener('click', bevandeHandler, capture);
    document.body.addEventListener('click', inviaHandler, capture);
    document.body.addEventListener('click', orderHandler, capture);
  }

  function removeHandlers() {
    try {
      if (bevandeHandler) document.body.removeEventListener('click', bevandeHandler, capture);
      if (inviaHandler) document.body.removeEventListener('click', inviaHandler, capture);
      if (orderHandler) document.body.removeEventListener('click', orderHandler, capture);
    } catch (e) {
      console.warn('EventDelegator destroy error', e);
    }
    bevandeHandler = null;
    inviaHandler = null;
    orderHandler = null;
  }

  // Espongo l'API sul global per compatibilità con codice esistente
  window.EventDelegator = window.EventDelegator || {
    init: function () {
      try { instalarHandlers(); } catch (e) { console.error('EventDelegator.init error', e); }
    },
    destroy: function () {
      try { removeHandlers(); } catch (e) { console.error('EventDelegator.destroy error', e); }
    }
  };

  // Se un caller precedente ha segnato che l'init era pending, eseguilo ora.
  try {
    if (window._menu_events_init_pending) {
      try { window.EventDelegator.init(); } catch (e) { console.error('auto-init EventDelegator failed', e); }
      delete window._menu_events_init_pending;
    }
  } catch (e) { /* ignore */ }
})();