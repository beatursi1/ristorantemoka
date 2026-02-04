// cart-ui.js
(function () {
  'use strict';

  // Restituisce colore associato a lettera cliente
  function getColoreCliente(lettera) {
    const colori = {
      'A': '#3498db', 'B': '#2ecc71', 'C': '#e74c3c',
      'D': '#f39c12', 'E': '#9b59b6', 'F': '#1abc9c',
      'G': '#7f8c8d', 'H': '#34495e', 'I': '#d35400',
      'J': '#16a085', 'K': '#8e44ad', 'L': '#2c3e50'
    };
    return colori[lettera] || '#95a5a6';
  }

  // Mostra notifica temporanea (stessa UI del file originale)
  function mostraNotifica(messaggio) {
    try {
      const notifica = document.createElement('div');
      notifica.className = 'alert alert-success position-fixed';
      notifica.style.cssText = `
        top: 20px; right: 20px; z-index: 9999;
        animation: fadeInOut 3s ease-in-out;
      `;
      notifica.innerHTML = `<i class="fas fa-check-circle me-2"></i>${messaggio}`;

      document.body.appendChild(notifica);

      setTimeout(() => {
        try { notifica.remove(); } catch (e) { /* ignore */ }
      }, 3000);
    } catch (e) {
      console.warn('CartUI.mostraNotifica errore:', e);
    }
  }

  // Aggiorna la UI del carrello leggendo il "carrello" globale (proxy)
  function aggiornareCarrelloUI() {
    try {
      const totaleArticoli = Array.isArray(window.carrello) ? window.carrello.length : 0;
      const totalePrezzo = (Array.isArray(window.carrello) ? window.carrello.reduce((sum, item) => sum + (Number(item.prezzo) || 0), 0) : 0);

      const elTotArt = document.getElementById('totale-articoli');
      const elTotPrez = document.getElementById('totale-prezzo');
      if (elTotArt) elTotArt.textContent = totaleArticoli;
      if (elTotPrez) elTotPrez.textContent = Number(totalePrezzo).toFixed(2);

      const container = document.getElementById('carrello-contenuto');
      if (!container) return;

      // Svuota in modo sicuro
      while (container.firstChild) container.removeChild(container.firstChild);

      if (!Array.isArray(window.carrello) || window.carrello.length === 0) {
        const p = document.createElement('p');
        p.className = 'text-muted mb-0';
        p.innerHTML = '<em>Carrello vuoto</em>';
        container.appendChild(p);
      } else {
        window.carrello.forEach((item, index) => {
          try {
            const row = document.createElement('div');
            row.className = 'carrello-item d-flex justify-content-between align-items-center py-1';

            const left = document.createElement('div');
            const nomeEl = document.createElement('span');
            nomeEl.textContent = item.nome || '';
            left.appendChild(nomeEl);

            const badge = document.createElement('span');
            badge.className = 'cliente-label';
            badge.style.background = getColoreCliente(item.cliente);
            badge.textContent = item.cliente || '';
            badge.style.marginLeft = '8px';
            left.appendChild(badge);

            const right = document.createElement('div');
            right.className = 'd-flex align-items-center';

            const prezzoEl = document.createElement('span');
            prezzoEl.className = 'fw-bold me-3';
            prezzoEl.textContent = '€' + Number(item.prezzo || 0).toFixed(2);
            right.appendChild(prezzoEl);

            const btnRimuovi = document.createElement('button');
            btnRimuovi.className = 'btn-rimuovi btn btn-sm btn-outline-danger';
            btnRimuovi.type = 'button';
            btnRimuovi.setAttribute('aria-label', 'Rimuovi elemento dal carrello');
            btnRimuovi.style.marginLeft = '8px';
            btnRimuovi.addEventListener('click', function () {
              try {
                if (typeof window.rimuovereDalCarrello === 'function') {
                  window.rimuovereDalCarrello(index);
                } else if (Array.isArray(window.carrello)) {
                  window.carrello.splice(index, 1);
                  aggiornareCarrelloUI();
                }
              } catch (e) { console.warn('Errore rimuovi item:', e); }
            });
            const icon = document.createElement('i');
            icon.className = 'fas fa-times';
            btnRimuovi.appendChild(icon);
            right.appendChild(btnRimuovi);

            row.appendChild(left);
            row.appendChild(right);
            container.appendChild(row);
          } catch (e) { /* non fermare il rendering per un singolo elemento */ }
        });
      }

      const carrelloEl = document.getElementById('carrello');
      if (carrelloEl) carrelloEl.style.display = (totaleArticoli > 0) ? 'block' : 'none';
    } catch (e) {
      console.warn('CartUI.aggiornareCarrelloUI errore:', e);
    }
  }

  // Espongo un'API globale semplice (no module loader richiesto)
  window.CartUI = window.CartUI || {};
  window.CartUI.getColoreCliente = getColoreCliente;
  window.CartUI.mostraNotifica = mostraNotifica;
  window.CartUI.aggiornareCarrelloUI = aggiornareCarrelloUI;

  // inizializzazione opzionale: aggiorna UI al caricamento se carrello già popolato
  try {
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
      setTimeout(() => { try { aggiornareCarrelloUI(); } catch(e){} }, 0);
    } else {
      document.addEventListener('DOMContentLoaded', () => { try { aggiornareCarrelloUI(); } catch(e){} });
    }
  } catch (e) {}
})();