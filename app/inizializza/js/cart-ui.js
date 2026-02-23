/**
 * CART-UI.JS - Versione Definitiva Corretta
 */
(function () {
  'use strict';

  // 1. Colori per i badge clienti
  function getColoreCliente(lettera) {
    const colori = {
      'A': '#3498db', 'B': '#2ecc71', 'C': '#e74c3c',
      'D': '#f39c12', 'E': '#9b59b6', 'F': '#1abc9c',
      'G': '#7f8c8d', 'H': '#34495e', 'I': '#d35400',
      'J': '#16a085', 'K': '#8e44ad', 'L': '#2c3e50'
    };
    return colori[lettera] || '#95a5a6';
  }

  // 2. Sistema di notifiche globale
  function mostraNotifica(messaggio) {
    try {
      const vecchia = document.querySelector('.cart-notifica-temp');
      if (vecchia) vecchia.remove();

      const n = document.createElement('div');
      n.className = 'alert alert-success position-fixed cart-notifica-temp';
      n.style.cssText = 'top:20px;right:20px;z-index:9999;box-shadow:0 4px 15px rgba(0,0,0,0.2);animation:fadeInOut 3s ease-in-out;';
      n.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + messaggio;
      document.body.appendChild(n);
      setTimeout(function() { if(n && n.parentNode) n.remove(); }, 3000);
    } catch (e) { console.warn('Errore notifica:', e); }
  }

  // 3. Aggiornamento interfaccia carrello e numerini pietanze
  function aggiornareCarrelloUI() {
    try {
      const carrello = Array.isArray(window.carrello) ? window.carrello : [];
      
      // Aggiorna totali barra carrello
      const elArt = document.getElementById('totale-articoli');
      const elPrez = document.getElementById('totale-prezzo');
      if (elArt) elArt.textContent = carrello.length;
      if (elPrez) {
        const totale = carrello.reduce(function(acc, item) {
          return acc + (parseFloat(item.prezzo) * (parseInt(item.quantita) || 1));
        }, 0);
        elPrez.textContent = totale.toFixed(2);
      }

      // --- AGGIORNA NUMERINI SULLE PIETANZE (id="qty-ID") ---
      document.querySelectorAll('.qty-indicator').forEach(function(el) { el.textContent = '0'; });
      const conteggi = {};
      carrello.forEach(function(item) {
        const id = String(item.id);
        conteggi[id] = (conteggi[id] || 0) + (parseInt(item.quantita) || 1);
      });
      for (let id in conteggi) {
        const indicator = document.getElementById('qty-' + id);
        if (indicator) indicator.textContent = conteggi[id];
      }

      // Aggiorna contenuto lista (per il modale riepilogo)
      const container = document.getElementById('carrello-contenuto');
      if (container) {
        container.innerHTML = '';
        if (carrello.length === 0) {
          container.innerHTML = '<p class="text-muted mb-0"><em>Carrello vuoto</em></p>';
        } else {
          carrello.forEach(function(item, index) {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';
            row.innerHTML = 
              '<div class="d-flex align-items-center">' +
                '<span class="badge me-2" style="background:' + getColoreCliente(item.cliente) + ' !important">' + item.cliente + '</span>' +
                '<span style="font-size:0.9rem">' + (parseInt(item.quantita) > 1 ? item.quantita + 'x ' : '') + item.nome + '</span>' +
              '</div>' +
              '<div class="d-flex align-items-center">' +
                '<span class="fw-bold me-2">€' + (parseFloat(item.prezzo) * (parseInt(item.quantita) || 1)).toFixed(2) + '</span>' +
                '<button class="btn btn-sm text-danger p-0" onclick="rimuovereDalCarrello(' + index + ')"><i class="fas fa-times-circle"></i></button>' +
              '</div>';
            container.appendChild(row);
          });
        }
      }

      // Mostra/Nascondi barra carrello
      const carEl = document.getElementById('carrello');
      if (carEl) carEl.style.display = (carrello.length > 0) ? 'block' : 'none';

    } catch (e) { console.error('CartUI Error:', e); }
  }

  // Esposizione funzioni per altri file
  window.mostraNotifica = mostraNotifica;
  window.aggiornareCarrelloUI = aggiornareCarrelloUI;
  window.CartUI = {
    getColoreCliente: getColoreCliente,
    mostraNotifica: mostraNotifica,
    aggiornareCarrelloUI: aggiornareCarrelloUI
  };

  // Esecuzione immediata
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', aggiornareCarrelloUI);
  } else {
    aggiornareCarrelloUI();
  }
})();