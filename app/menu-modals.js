// menu-modals.js
(function () {
  'use strict';

  // ====== FUNZIONI MODAL (estratte da menu.js) ======

  // Apri modal "miei ordini" (versione corretta anti-freeze)
  function apriModalMieiOrdini() {
    const modalEl = document.getElementById('modal-miei-ordini');
    if (!modalEl) return;

    // Se il modal è già visibile, non fare nulla (evita di creare troppi backdrop grigi)
    if (modalEl.classList.contains('show')) return;

    if (!modalEl.dataset.accessibilityBound) {
      modalEl.addEventListener('show.bs.modal', () => {
        try {
          const active = document.activeElement;
          if (active && modalEl.contains(active)) {
            (document.body || document.documentElement).focus();
            if (document.activeElement === active) active.blur();
          }
        } catch (e) { console.warn('Errore focus:', e); }
      });

      modalEl.addEventListener('shown.bs.modal', () => {
        const closeBtn = modalEl.querySelector('.btn-close');
        if (closeBtn) closeBtn.focus();
      });

      modalEl.addEventListener('hide.bs.modal', () => {
        try {
          const active = document.activeElement;
          if (active && modalEl.contains(active)) {
            (document.body || document.documentElement).focus();
            if (document.activeElement === active) active.blur();
          }
        } catch (e) { console.warn('Errore blur:', e); }
      });

      modalEl.dataset.accessibilityBound = '1';
    }

    // Usa getOrCreateInstance per gestire correttamente la sessione del modal
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
  }

  // Apre modal bevande (bind idempotenti e logica di preselect)
  function apriModalBevande() {
    try {
      let modalEl = document.getElementById('modal-bevande');
      if (!modalEl) {
        const tmpl = document.getElementById('tmpl-modal-bevande');
        if (tmpl && tmpl.innerHTML && tmpl.innerHTML.trim()) {
          const container = document.createElement('div');
          container.innerHTML = tmpl.innerHTML.trim();
          modalEl = container.firstElementChild;
          if (modalEl) document.body.appendChild(modalEl);
        }
      }

      if (!modalEl) {
        console.error('apriModalBevande: impossibile trovare o ripristinare #modal-bevande');
        return;
      }

      try {
        if (!modalEl.dataset.boundBevande) {
          modalEl.addEventListener('shown.bs.modal', async () => {
            try {
              if (!parametriUrl || typeof parametriUrl !== 'object') parametriUrl = {};
              const qs = new URLSearchParams(window.location.search);
              if (!parametriUrl.sessione) parametriUrl.sessione = qs.get('sessione') || null;
              if (!parametriUrl.tavolo) parametriUrl.tavolo = qs.get('tavolo') || null;
              if (!parametriUrl.cliente) parametriUrl.cliente = qs.get('cliente') || null;
              if (!parametriUrl.sessione_cliente_id) parametriUrl.sessione_cliente_id = qs.get('sessione_cliente_id') || parametriUrl.sessione_cliente_id || null;
              if (!parametriUrl.session_id) parametriUrl.session_id = parametriUrl.sessione_cliente_id || parametriUrl.session_id || null;
            } catch (e) { /* ignore */ }

                                // Preselect pendingBevanda se presente (con fallback su prezzo/nome)
                    try {
                        const pending = modalEl.dataset.pendingBevanda;
                        const sel = document.getElementById('select-bevanda');
                        if (pending && sel) {
                            let matched = false;
                            const optByValue = Array.from(sel.options).find(o => o.value === String(pending));
                            if (optByValue) {
                                sel.value = optByValue.value;
                                matched = true;
                            } else {
                                const pendingStr = String(pending).toLowerCase();
                                const optByText = Array.from(sel.options).find(o => (o.text || '').toLowerCase().includes(pendingStr));
                                if (optByText) {
                                    sel.value = optByText.value;
                                    matched = true;
                                }
                            }

                            if (!matched) {
                                // fallback: se il caller ha passato prezzo/nome, mostriamo il totale usando questi dati
                                const pendingPrezzo = modalEl.dataset.pendingPrezzo || null;
                                const pendingNome = modalEl.dataset.pendingNome || null;
                                const qtyEl = document.getElementById('quantita-bevanda');
                                const out = document.getElementById('prezzo-totale-bevanda');
                                try {
                                    const qty = Math.max(1, parseInt(qtyEl?.textContent || '1', 10) || 1);
                                    if (pendingPrezzo && out) {
                                        out.textContent = (Number(String(pendingPrezzo).replace(',', '.')) * qty).toFixed(2);
                                    }
                                    // se vuoi, possiamo impostare il testo del select a una voce temporanea o mostrare il nome in altro elemento
                                    if (pendingNome) {
                                        // opzionale: imposta un elemento che mostri il nome selezionato (se presente)
                                        // esempio: document.getElementById('select-bevanda-label')?.textContent = pendingNome;
                                    }
                                } catch (e) { /* ignore */ }
                            }

                            // pulizia dei flag pending per evitare riutili accidentali
                            try { delete modalEl.dataset.pendingBevanda; delete modalEl.dataset.pendingPrezzo; delete modalEl.dataset.pendingNome; } catch(e){}
                        }
                    } catch (e) { console.warn('Errore nel preselect della bevanda pending:', e); }

            try { toggleSelettorePartecipanti(); } catch (e) { /* ignore */ }

            try {
              const current = modalEl.querySelector('input[name="condivisione-bevanda"]:checked')?.value || document.querySelector('input[name="condivisione-bevanda"]:checked')?.value;
              if (current === 'gruppo') {
                if (typeof popolaSelettorePartecipanti === 'function') await popolaSelettorePartecipanti();
                else { try { popolaSelettorePartecipanti(); } catch(e) {} }
              }
            } catch (e) { /* ignore */ }

            try {
              function tryFocus(el) {
                if (!el || typeof el.focus !== 'function') return;
                try { el.focus({ preventScroll: true }); } catch (err) { try { el.focus(); } catch(e){/*ignore*/} }
              }
              const closeBtn = modalEl.querySelector('.btn-close');
              if (closeBtn) {
                const deadline = Date.now() + 600;
                const checkAndFocus = () => {
                  let cur = closeBtn;
                  let visible = true;
                  while (cur) {
                    try { if (cur.getAttribute && cur.getAttribute('aria-hidden') === 'true') { visible = false; break; } } catch(e){/*ignore*/}
                    cur = cur.parentElement;
                  }
                  if (visible || Date.now() > deadline) {
                    tryFocus(closeBtn);
                  } else {
                    requestAnimationFrame(checkAndFocus);
                  }
                };
                requestAnimationFrame(checkAndFocus);
              }
            } catch(e){ /* ignore focus errors */ }
          });

          try {
            modalEl.querySelectorAll('input[name="condivisione-bevanda"]').forEach(r => {
              if (!r.dataset.boundChange) {
                r.addEventListener('change', () => toggleSelettorePartecipanti());
                r.dataset.boundChange = '1';
              }
            });
          } catch(e){ /* ignore */ }

          try {
            if (!modalEl.dataset.boundHideFocus) {
              modalEl.addEventListener('hide.bs.modal', () => {
                try {
                  const active = document.activeElement;
                  if (active && modalEl.contains(active)) {
                    try { (document.body || document.documentElement).focus(); } catch(e){}
                    try { if (document.activeElement === active) active.blur(); } catch(e){}
                  }
                } catch(e){}
              });
              modalEl.dataset.boundHideFocus = '1';
            }
          } catch(e){ /* ignore */ }

          modalEl.dataset.boundBevande = '1';
        }
      } catch (e) {
        console.warn('apriModalBevande: errore durante il binding dei listener:', e);
      }

      try {
        const active = document.activeElement;
        if (active) {
          try { if (active && (modalEl.contains(active) || active.matches && active.matches('#modal-bevande *'))) active.blur(); } catch(e){ try { active.blur(); } catch(e){} }
        }
      } catch(e){/*ignore*/}

      const bsModal = new bootstrap.Modal(modalEl);
      requestAnimationFrame(() => {
        try { bsModal.show(); } catch(e) { console.error('Errore mostrando modal-bevande:', e); }
      });
    } catch (err) {
      console.error('apriModalBevande: errore inatteso', err);
    }
  }

  // Aggiungi bevanda dal modal al carrello
  function aggiungiBevandaAlCarrello() {
    if (!parametriUrl.cliente) {
      alert('Errore: cliente non specificato');
      return;
    }

    const select = document.getElementById('select-bevanda');
    const bevandaTesto = select.options[select.selectedIndex].text;

    const matchNome = bevandaTesto.match(/^([^-€]+)/);
    const matchPrezzo = bevandaTesto.match(/€(\d+\.?\d*)/);

    const nomeBevanda = matchNome ? matchNome[0].trim() : bevandaTesto;
    const prezzoBevanda = matchPrezzo ? parseFloat(matchPrezzo[1]) : 0;
    const bevandaId = select.value;

    const tipoCondivisione = (document.querySelector('input[name="condivisione-bevanda"]:checked') || {}).value || 'personale';

// Raccoglie partecipanti se condivisione parziale
    let partecipanti = [];
    let nomeCondivisione = nomeBevanda;
    if (tipoCondivisione === 'tavolo') {
        nomeCondivisione = `${nomeBevanda} (per tutto il tavolo)`;
    } else if (tipoCondivisione === 'parziale' || tipoCondivisione === 'gruppo') {
        const boxes = Array.from(document.querySelectorAll('#partecipanti-list .partecipante-checkbox'));
        partecipanti = boxes.filter(b => b.checked).map(b => ({
            lettera: b.value,
            nome: b.closest('label')?.querySelector('span.small')?.textContent?.trim() || `Cliente ${b.value}`
        }));
        const nomiPartecipanti = partecipanti
            .filter(p => p.lettera !== parametriUrl.cliente)
            .map(p => `${p.nome} (${p.lettera})`)
            .join(', ');
        nomeCondivisione = nomiPartecipanti
            ? `${nomeBevanda} — condivisa con ${nomiPartecipanti}`
            : nomeBevanda;
    }

    carrello.push({
      id: bevandaId,
      nome: nomeCondivisione,
      prezzo: prezzoBevanda,
      cliente: parametriUrl.cliente,
      timestamp: new Date().toISOString(),
      tipo: 'bevanda',
      condivisione: tipoCondivisione === 'gruppo' ? 'parziale' : tipoCondivisione,
      partecipanti: partecipanti
    });

    try {
      const modalEl = document.getElementById('modal-bevande');
      const active = document.activeElement;
      if (active && modalEl && modalEl.contains(active)) {
        try { active.blur(); } catch (e) { /* ignore */ }
      }
    } catch (e) { /* ignore safe */ }

    try {
      const modalEl = document.getElementById('modal-bevande');
      const instance = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
      if (instance) {
        setTimeout(() => {
          try { instance.hide(); } catch (e) { /* ignore */ }
        }, 20);
      }
    } catch (e) { /* ignore */ }

    aggiornareCarrelloUI();
    mostraNotifica(`${nomeBevanda} aggiunta al carrello`);
  }

  // Fetch clienti del tavolo/sessione (usata per popolaSelettorePartecipanti)
  async function fetchClientiTavolo() {
    try {
      if (!parametriUrl || typeof parametriUrl !== 'object') parametriUrl = {};

      const qs = new URLSearchParams(window.location.search);
      if (!parametriUrl.sessione) parametriUrl.sessione = qs.get('sessione') || null;
      if (!parametriUrl.tavolo) parametriUrl.tavolo = qs.get('tavolo') || null;
      if (!parametriUrl.cliente) parametriUrl.cliente = qs.get('cliente') || null;
      if (!parametriUrl.sessione_cliente_id) parametriUrl.sessione_cliente_id = qs.get('sessione_cliente_id') || parametriUrl.sessione_cliente_id || null;
      if (!parametriUrl.session_id) parametriUrl.session_id = parametriUrl.sessione_cliente_id || parametriUrl.session_id || null;

      if (!parametriUrl.tavolo && parametriUrl.sessione) {
        try {
          const saved = localStorage.getItem('cliente_sessione_' + parametriUrl.sessione);
          if (saved) {
            const obj = JSON.parse(saved);
            if (obj && obj.tavolo) parametriUrl.tavolo = String(obj.tavolo);
          }
        } catch (e) { /* ignore */ }
      }

      const tavoloId = parametriUrl.tavolo ? parseInt(parametriUrl.tavolo) : null;
      const sessioneToken = parametriUrl.sessione || null;

      if (!tavoloId && !sessioneToken) {
        console.warn('fetchClientiTavolo: parametri tavolo/sessione mancanti dopo tentativi di recupero');
        return [];
      }

      try {
        console.group('fetchClientiTavolo -> diagnostic log');
        console.log('parametriUrl (prima fetch):', parametriUrl);
        console.log('tavoloId:', tavoloId, 'sessioneToken:', sessioneToken);
        const bodyToSend = { tavolo_id: tavoloId, sessione: sessioneToken };
        console.log('API endpoint:', '../api/clienti/get-clienti-registrati.php');
        console.log('Request body (JSON):', bodyToSend);
        console.groupEnd();
      } catch (e) {
        console.warn('Errore logging diagnostico prima della fetch:', e);
      }

      const form = new URLSearchParams();
      form.append('tavolo_id', String(tavoloId));
      form.append('tavolo', String(tavoloId));
      form.append('sessione', sessioneToken || '');
      form.append('session', sessioneToken || '');

      let resp = await fetch('../api/clienti/get-clienti-registrati.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString(),
        cache: 'no-store',
        credentials: 'same-origin'
      });

      console.group('fetchClientiTavolo -> response');
      console.log('HTTP status:', resp.status, resp.statusText);
      console.log('response content-type:', resp.headers.get('content-type'));

      let text;
      try {
        text = await resp.text();
        console.log('raw response text:', text);
      } catch (e) {
        console.warn('fetchClientiTavolo: errore leggendo response text:', e);
        text = null;
      }

      let data;
      try {
        data = text ? JSON.parse(text) : null;
      } catch (e) {
        console.warn('fetchClientiTavolo: risposta non JSON, uso raw text come errore');
        data = { success: false, error: text || 'response-not-json' };
      }

      console.log('fetchClientiTavolo parsed response:', data);
      console.groupEnd();

      if (!resp.ok) {
        console.warn('fetchClientiTavolo HTTP non OK', resp.status);
        return [];
      }

      let clientiRaw = [];

      if (data && data.success === false && String(data.error || '').toLowerCase().includes('parametro tavolo')) {
        console.warn('fetchClientiTavolo: server ha segnalato "Parametro tavolo mancante" — uso fallback da localStorage');
        try { parametriUrl._clienti_from_local = true; } catch(e) { window.parametriUrl = window.parametriUrl || {}; window.parametriUrl._clienti_from_local = true; }

        try {
          const fallback = [];
          Object.keys(localStorage).forEach(k => {
            if (/cliente_sessione_|cliente_tavolo_/.test(k)) {
              try {
                const v = JSON.parse(localStorage.getItem(k));
                if (v && v.lettera) {
                  fallback.push({
                    lettera: v.lettera,
                    nome: v.nome || (`Cliente ${v.lettera}`),
                    sessione_cliente_id: v.sessione_cliente_id || v.session_id || null
                  });
                }
              } catch(e) { /* ignore parse error */ }
            }
          });
          if (fallback.length) {
            const seen = new Set();
            clientiRaw = fallback.filter(c => {
              if (!c.lettera) return false;
              if (seen.has(c.lettera)) return false;
              seen.add(c.lettera);
              return true;
            });
            console.log('fetchClientiTavolo fallback clientiRaw:', clientiRaw);
          } else {
            return [];
          }
        } catch (e) {
          console.warn('Errore costruzione fallback localStorage:', e);
          return [];
        }
      } else {
        try { parametriUrl._clienti_from_local = false; } catch(e) {}
        if (Array.isArray(data)) clientiRaw = data;
        else if (data && typeof data === 'object') {
          if (Array.isArray(data.clienti)) clientiRaw = data.clienti;
          else if (Array.isArray(data.data)) clientiRaw = data.data;
          else if (Array.isArray(data.results)) clientiRaw = data.results;
          else if (Array.isArray(data.clienti_registrati)) clientiRaw = data.clienti_registrati;
          else if (data.clienti && typeof data.clienti === 'object') clientiRaw = data.clienti;
          else clientiRaw = data;
        } else {
          return [];
        }
      }

      const clienti = [];

      const deriveLetterFromName = (nome) => {
        if (!nome) return null;
        let m = nome.match(/\(([A-Z])\)/);
        if (m && m[1]) return m[1];
        m = nome.trim().match(/(?:\s|^)([A-Z])$/);
        if (m && m[1]) return m[1];
        m = nome.match(/-\s*([A-Z])\s*$/);
        if (m && m[1]) return m[1];
        return null;
      };

      if (!Array.isArray(clientiRaw) && typeof clientiRaw === 'object') {
        Object.entries(clientiRaw).forEach(([k, info]) => {
          const letteraCandidate = (typeof k === 'string' && /^[A-Z]$/.test(k)) ? k : null;
          const nome = info && (info.nome || info.nome_cliente || info.nomeCompleto) ? (info.nome || info.nome_cliente || info.nomeCompleto) : null;
          const sessioneId = info && (info.id || info.sessione_cliente_id || info.session_id) ? (info.id || info.sessione_cliente_id || info.session_id) : null;
          let lettera = letteraCandidate || (info && (info.lettera || info.letter)) || null;

          if (!lettera) {
            lettera = deriveLetterFromName(nome);
          }

          if (!lettera && sessioneId && (String(sessioneId) === String(parametriUrl.sessione_cliente_id) || String(sessioneId) === String(parametriUrl.session_id))) {
            lettera = parametriUrl.cliente || null;
          }

          clienti.push({
            lettera: lettera,
            nome: nome || (`Cliente ${lettera || ''}`),
            sessione_cliente_id: sessioneId || null
          });
        });
      } else if (Array.isArray(clientiRaw)) {
        clientiRaw.forEach(item => {
          const nome = item.nome || item.nome_cliente || item.nomeCompleto || null;
          let lettera = item.lettera || item.letter || null;
          const sessioneId = item.id || item.sessione_cliente_id || item.session_id || null;

          if (!lettera) {
            if (sessioneId && parametriUrl && (String(sessioneId) === String(parametriUrl.sessione_cliente_id) || String(sessioneId) === String(parametriUrl.session_id))) {
              lettera = parametriUrl.cliente || null;
            }
            if (!lettera) {
              lettera = deriveLetterFromName(nome);
            }
            if (!lettera && sessioneId) {
              try {
                Object.keys(localStorage).forEach(k => {
                  try {
                    const v = JSON.parse(localStorage.getItem(k));
                    if (v && (v.sessione_cliente_id == sessioneId || v.session_id == sessioneId)) {
                      if (v.lettera) lettera = v.lettera;
                    }
                  } catch(e) { /* ignore parse error */ }
                });
              } catch(e) { /* ignore localStorage errors */ }
            }
          }

          clienti.push({
            lettera: lettera,
            nome: nome || (`Cliente ${lettera || ''}`),
            sessione_cliente_id: sessioneId || null
          });
        });
      }

      const map = {};
      const res = [];

      if (parametriUrl.cliente) {
        const currLetter = String(parametriUrl.cliente);
        const foundCurr = clienti.find(c => String(c.lettera) === currLetter);
        if (foundCurr) {
          map[currLetter] = true;
          res.push(foundCurr);
        } else {
          map[currLetter] = true;
          res.push({ lettera: currLetter, nome: `Cliente ${currLetter}`, sessione_cliente_id: parametriUrl.sessione_cliente_id || null });
        }
      }

      clienti.forEach(c => {
        if (c.lettera && !map[c.lettera]) {
          map[c.lettera] = true;
          res.push(c);
        } else if (!c.lettera) {
          const key = `noletter-${c.sessione_cliente_id || c.nome || Math.random()}`;
          if (!map[key]) {
            map[key] = true;
            res.push(c);
          }
        }
      });

      console.log('fetchClientiTavolo parsed clienti:', res);
      return res;
    } catch (err) {
      console.warn('Errore fetchClientiTavolo:', err);
      return [];
    }
  }

  // Popola la lista di partecipanti nel modal (#partecipanti-list)
  async function popolaSelettorePartecipanti() {
    let modalEl = document.querySelector('#modal-bevande.modal.show') || document.getElementById('modal-bevande');
    if (!modalEl) modalEl = document.getElementById('modal-bevande');
    let container = modalEl ? modalEl.querySelector('#partecipanti-list') : document.getElementById('partecipanti-list');
    if (!container) return;

    if (modalEl && modalEl.dataset.popolando === '1') return;
    try { if (modalEl) modalEl.dataset.popolando = '1'; } catch(e){}

    try { container.innerHTML = ''; } catch(e) {
      while (container.firstChild) container.removeChild(container.firstChild);
    }

    const clienti = await fetchClientiTavolo();

    try {
      const alertElId = 'partecipanti-fallback-alert';
      const existing = modalEl ? modalEl.querySelector('#' + alertElId) : document.getElementById(alertElId);
      if (parametriUrl && parametriUrl._clienti_from_local) {
        if (!existing && container.parentNode) {
          const info = document.createElement('div');
          info.id = alertElId;
          info.className = 'alert alert-warning small d-flex justify-content-between align-items-center';
          info.style.marginBottom = '8px';
          info.innerHTML = '<div>Elenco ricostruito localmente (offline).</div>';

          const actions = document.createElement('div');
          actions.style.whiteSpace = 'nowrap';
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'btn btn-sm btn-outline-primary ms-2';
          btn.id = 'btn-aggiorna-partecipanti';
          btn.textContent = 'Aggiorna da server';
          actions.appendChild(btn);

          info.appendChild(actions);
          container.parentNode.insertBefore(info, container);

          btn.addEventListener('click', async (ev) => {
            try {
              btn.disabled = true;
              btn.textContent = 'Controllo...';
              const tav = parametriUrl && parametriUrl.tavolo ? parametriUrl.tavolo : (new URLSearchParams(window.location.search)).get('tavolo');
              if (!tav) {
                alert('Tavolo non disponibile per aggiornamento.');
                btn.disabled = false;
                btn.textContent = 'Aggiorna da server';
                return;
              }
              const url = `../api/clienti/get-clienti-registrati.php?tavolo_id=${encodeURIComponent(tav)}`;
              const r = await fetch(url, { method: 'GET', cache: 'no-store', credentials: 'same-origin' });
              const txt = await r.text();
              let dataResp = null;
              try { dataResp = txt ? JSON.parse(txt) : null; } catch(e) { dataResp = null; }
              if (r.ok && dataResp && dataResp.success) {
                try { parametriUrl._clienti_from_local = false; } catch(e) {}
                await popolaSelettorePartecipanti();
                btn.textContent = 'Aggiornato';
                setTimeout(() => { btn.disabled = false; btn.textContent = 'Aggiorna da server'; }, 1500);
              } else {
                const errMsg = dataResp && dataResp.error ? dataResp.error : txt || 'Risposta non valida dal server';
                alert('Aggiornamento dal server non riuscito: ' + errMsg);
                btn.disabled = false;
                btn.textContent = 'Aggiorna da server';
              }
            } catch (err) {
              console.error('Errore aggiornamento partecipanti da server:', err);
              alert('Errore di connessione: ' + (err.message || err));
              btn.disabled = false;
              btn.textContent = 'Aggiorna da server';
            }
          });

        } else if (existing) {
          existing.style.display = 'flex';
        }
      } else if (existing) {
        existing.style.display = 'none';
      }
    } catch(e) { console.warn('Errore mostra avviso fallback partecipanti:', e); }

    if (!clienti || clienti.length === 0) {
      if (parametriUrl.cliente) {
        const el = document.createElement('label');
        el.className = 'form-check form-check-inline';
        el.innerHTML = `<input class="form-check-input" type="checkbox" checked disabled value="${parametriUrl.cliente}"> <span class="badge bg-primary">${parametriUrl.cliente}</span> ${parametriUrl.cliente}`;
        container.appendChild(el);
      } else {
        container.innerHTML = '<div class="small text-muted">Nessun altro cliente registrato al tavolo.</div>';
      }
      updateGruppoSummary();
      try { if (modalEl) delete modalEl.dataset.popolando; } catch(e){}
      return;
    }    // Rimosso: la label "Condiviso con:" è stata spostata nel modal "Miei ordini"
    try { /* no-op: label gestita nelle righe del modal "Miei ordini" */ } catch (e) { /* ignore */ }
    const seen = new Set();
    for (const c of clienti) {
      if (!c) continue;
      const lett = c.lettera ? String(c.lettera) : null;
      if (!lett) {
        const labelEmpty = document.createElement('div');
        labelEmpty.className = 'small text-muted';
        labelEmpty.textContent = c.nome || 'Cliente sconosciuto';
        container.appendChild(labelEmpty);
        continue;
      }
      if (seen.has(lett)) continue;
      seen.add(lett);

      const label = document.createElement('label');
      label.className = 'form-check form-check-inline align-items-center';
      label.style.marginRight = '8px';

      const input = document.createElement('input');
      input.type = 'checkbox';
      input.className = 'form-check-input partecipante-checkbox';
      input.value = lett;
      input.dataset.sessioneClienteId = c.sessione_cliente_id || '';

      if (parametriUrl.cliente && String(parametriUrl.cliente) === String(lett)) {
        input.checked = true;
        input.disabled = true;
      }

      const badge = document.createElement('span');
      badge.className = 'badge bg-secondary ms-1 me-1';
      badge.textContent = lett;

      const nomeSpan = document.createElement('span');
      nomeSpan.className = 'small';
      nomeSpan.textContent = ' ' + (c.nome || (`Cliente ${lett}`));

      label.appendChild(input);
      label.appendChild(badge);
      label.appendChild(nomeSpan);

      container.appendChild(label);
    }

    container.querySelectorAll('.partecipante-checkbox').forEach(cb => {
      if (!cb.dataset.boundChange) {
        cb.addEventListener('change', updateGruppoSummary);
        cb.dataset.boundChange = '1';
      }
    });

    updateGruppoSummary();
    try { if (modalEl) delete modalEl.dataset.popolando; } catch(e){}
  }

  function updateGruppoSummary() {
    const modalEl = document.getElementById('modal-bevande');
    const summaryEl = modalEl ? modalEl.querySelector('#gruppo-selezione-txt') : document.getElementById('gruppo-selezione-txt');
    if (!summaryEl) return;

    const checkboxSelector = modalEl ? '#partecipanti-list .partecipante-checkbox' : '#partecipanti-list .partecipante-checkbox';
    const nodes = modalEl ? Array.from(modalEl.querySelectorAll(checkboxSelector)) : Array.from(document.querySelectorAll(checkboxSelector));

    const checked = nodes.filter(cb => cb.checked).map(cb => cb.value);
    const altri = checked.filter(l => !(parametriUrl.cliente && String(l) === String(parametriUrl.cliente)));

    if (!altri.length) {
      summaryEl.textContent = '(seleziona i partecipanti)';
    } else if (altri.length <= 3) {
      summaryEl.textContent = ' ' + altri.join(', ');
    } else {
      summaryEl.textContent = ` ${altri.length} partecipanti`;
    }
  }

  function toggleSelettorePartecipanti() {
    const modalEl = document.getElementById('modal-bevande');
    const selected = modalEl ? modalEl.querySelector('input[name="condivisione-bevanda"]:checked')?.value : document.querySelector('input[name="condivisione-bevanda"]:checked')?.value;
    const sel = modalEl ? modalEl.querySelector('#selettore-partecipanti') : document.getElementById('selettore-partecipanti');
    if (!sel) return;
    if (selected === 'gruppo') {
      sel.style.display = 'block';
      popolaSelettorePartecipanti();
    } else {
      sel.style.display = 'none';
      const err = modalEl ? modalEl.querySelector('#selettore-error') : document.getElementById('selettore-error');
      if (err) err.style.display = 'none';
    }
  }

  // caricareOrdiniPrecedenti & mostraStoricoOrdini
  async function caricareOrdiniPrecedenti() {
    try {
      const header = document.querySelector('.header .container .row');
      if (header) {
        if (!document.getElementById('btn-visualizza-ordini')) {
          const pulsanteStorico = document.createElement('div');
          pulsanteStorico.className = 'text-center mt-3';

          const btn = document.createElement('button');
          btn.id = 'btn-visualizza-ordini';
          btn.type = 'button';
          btn.className = 'btn btn-outline-info btn-sm';
          btn.addEventListener('click', mostraStoricoOrdini);
          btn.innerHTML = `<i class="fas fa-history me-2"></i>Visualizza i tuoi ordini`;

          pulsanteStorico.appendChild(btn);
          header.parentNode.insertBefore(pulsanteStorico, header.nextSibling);
        }
      }
    } catch (error) {
      console.log('Errore caricamento storico:', error);
    }
  }

  async function mostraStoricoOrdini() {
    const elLoading = document.getElementById('mio-ordini-loading');
    const elVuoto = document.getElementById('mio-ordini-vuoto');
    const elCont = document.getElementById('mio-ordini-contenitore');

    if (elLoading) elLoading.classList.remove('d-none');
    if (elVuoto) elVuoto.classList.add('d-none');
    if (elCont) { elCont.classList.add('d-none'); elCont.innerHTML = ''; }

    // --- 1. RECUPERO SESSIONE PROXY (CAMERIERE) ---
    const proxyData = JSON.parse(localStorage.getItem('cameriere_proxy_order') || '{}');
    
    // Fallback: se siamo sul tablet cameriere, usiamo i dati proxy se l'URL è vuoto
    if (!parametriUrl.sessione_cliente_id && proxyData.sessione_cliente_id) {
        window.parametriUrl = Object.assign(window.parametriUrl || {}, {
            tavolo: proxyData.tavolo,
            sessione: proxyData.sessione,
            sessione_cliente_id: proxyData.sessione_cliente_id,
            cliente: proxyData.cliente
        });
    }

    const tavoloId = parametriUrl.tavolo;
    const sessioneToken = parametriUrl.sessione;
    const sessioneClienteId = parametriUrl.sessione_cliente_id;

    if (!tavoloId || !sessioneToken || !sessioneClienteId) {
      if (elLoading) elLoading.classList.add('d-none');
      if (elVuoto) {
        elVuoto.classList.remove('d-none');
        const txt = document.getElementById('mio-ordini-vuoto-txt');
        if (txt) txt.textContent = 'Sessione non identificata. Seleziona un cliente dal monitor.';
      }
      return;
    }

    // --- 2. FIX NOME CLIENTE LIVE (Sincronizzazione immediata) ---
    fetchClientiTavolo().then(clienti => {
       const io = clienti.find(c => String(c.sessione_cliente_id) === String(sessioneClienteId));
       if (io && io.nome) {
           // Aggiorna tutti i possibili punti dove appare il nome nell'interfaccia
           document.querySelectorAll('.header h1, .header h2, #nome-cliente-display, .welcome-msg b, .welcome-text')
                   .forEach(el => el.textContent = io.nome);
           
           // Salva nel localStorage locale (per Anx sul suo cellulare)
           const storageKey = 'cliente_sessione_' + sessioneToken;
           let localData = JSON.parse(localStorage.getItem(storageKey) || '{}');
           localData.nome = io.nome;
           localStorage.setItem(storageKey, JSON.stringify(localData));
       }
    });

    const apiUrl = '../api/ordini/get-ordini-cliente.php';
    const payload = { tavolo_id: tavoloId, sessione: sessioneToken, sessione_cliente_id: sessioneClienteId };

    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cache: 'no-store',
        credentials: 'same-origin'
      });
      if (!res.ok) throw new Error('Risposta non OK dal server: ' + res.status);
      const data = await res.json();

      if (elLoading) elLoading.classList.add('d-none');

      if (!data.success) {
        if (elVuoto) {
          elVuoto.classList.remove('d-none');
          const txt = document.getElementById('mio-ordini-vuoto-txt');
          if (txt) txt.textContent = data.error || 'Errore nel recupero ordini';
        }
        apriModalMieiOrdini();
        return;
      }

      const ordini = data.ordini || [];
      if (!ordini.length) {
        if (elVuoto) {
          elVuoto.classList.remove('d-none');
          const txt = document.getElementById('mio-ordini-vuoto-txt');
          if (txt) txt.textContent = 'Nessun ordine precedente per questa sessione cliente.';
        }
        apriModalMieiOrdini();
        return;
      }

      elCont.innerHTML = '';

      for (const o of ordini) {
		        // Se TUTTE le righe dell'ordine sono annullate (stato 4), saltiamo l'ordine
        const tutteAnnullate = o.righe && o.righe.length > 0 && o.righe.every(r => parseInt(r.stato) === 4);
        if (tutteAnnullate) continue;   
        const card = document.createElement('div');
        card.className = 'card mb-3';

        const header = document.createElement('div');
        header.className = 'card-header d-flex justify-content-between align-items-center';

        const leftHeader = document.createElement('div');
        const badgeOrdine = document.createElement('span');
        badgeOrdine.className = 'badge bg-primary me-2';
        badgeOrdine.textContent = 'Ordine #' + (o.id !== undefined ? String(o.id) : '');
        leftHeader.appendChild(badgeOrdine);

        const smallDate = document.createElement('small');
        smallDate.className = 'text-muted';
        let dataOrdineFormattata = o.creato_il || '';
        if (o.creato_il) {
          const dt = new Date(String(o.creato_il).replace(' ', 'T'));
          if (!isNaN(dt.getTime())) {
            const gg = String(dt.getDate()).padStart(2, '0');
            const mm = String(dt.getMonth() + 1).padStart(2, '0');
            const aaaa = dt.getFullYear();
            dataOrdineFormattata = `${gg}/${mm}/${aaaa}`;
          }
        }
        smallDate.textContent = dataOrdineFormattata;
        leftHeader.appendChild(smallDate);

        header.appendChild(leftHeader);

        const rightHeader = document.createElement('div');
        rightHeader.className = 'text-end';
        const statoDiv = document.createElement('div');
        const statoLabel = document.createElement('span');
        statoLabel.className = 'small text-muted me-2';
        statoLabel.textContent = 'Stato';
        const badgeStato = document.createElement('span');
        const stato = o.stato || 'sconosciuto';
        let badgeClass = 'bg-secondary';
        if (String(stato).toLowerCase().includes('pronto')) badgeClass = 'bg-success';
        else if (String(stato).toLowerCase().includes('prepar')) badgeClass = 'bg-warning text-dark';
        else if (String(stato).toLowerCase().includes('consegn')) badgeClass = 'bg-info text-dark';
        else if (String(stato).toLowerCase().includes('annul')) badgeClass = 'bg-danger';
        badgeStato.className = 'badge ' + badgeClass;
        badgeStato.textContent = String(stato);

        const totaleDiv = document.createElement('div');
        totaleDiv.className = 'mt-1 small text-muted';
        totaleDiv.textContent = 'Totale';

        const totaleVal = document.createElement('div');
        totaleVal.className = 'fw-bold text-primary';
        // Calcoliamo il totale solo dei piatti NON annullati
        const totaleEffettivo = (o.righe || []).reduce((acc, r) => {
            return parseInt(r.stato) !== 4 ? acc + (parseFloat(r.totale_riga) || 0) : acc;
        }, 0);
        totaleVal.textContent = totaleEffettivo.toFixed(2) + '€';
        statoDiv.appendChild(statoLabel);
        statoDiv.appendChild(badgeStato);
        rightHeader.appendChild(statoDiv);
        rightHeader.appendChild(totaleDiv);
        rightHeader.appendChild(totaleVal);

        header.appendChild(rightHeader);
        card.appendChild(header);

        const body = document.createElement('div');
        body.className = 'card-body p-3';

        if (Array.isArray(o.righe) && o.righe.length) {
          // Ricalcoliamo il totale dell'ordine escludendo gli stornati (stato 4)
          const totaleEffettivo = o.righe.reduce((acc, curr) => {
            return parseInt(curr.stato) === 4 ? acc : acc + (parseFloat(curr.totale_riga) || 0);
          }, 0);
          
          // Aggiorniamo la visualizzazione del totale nella card dell'ordine
          const totOrdineEl = card.querySelector('.fw-bold');
          if (totOrdineEl) totOrdineEl.textContent = totaleEffettivo.toFixed(2) + '€';

          for (const r of o.righe) {
            const rStato = parseInt(r.stato);
            const nome = r.nome_display || r.nome || r.descrizione || 'Voce';
            const quantita = r.quantita !== undefined ? r.quantita : (r.q || 1);
            const prezzoUnit = r.prezzo_unitario !== undefined ? Number(r.prezzo_unitario) : (r.prezzo ? Number(r.prezzo) : 0);
            const totaleRiga = rStato === 4 ? 0 : (r.totale_riga !== undefined ? Number(r.totale_riga) : (prezzoUnit * quantita));
            const tipo = r.tipo || '';
            const condivisione = r.condivisione || '';

            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-start py-2 border-bottom';

            const left = document.createElement('div');
            const nomeDiv = document.createElement('div');
            nomeDiv.className = 'fw-semibold';
            if (rStato === 4) {
                nomeDiv.className += ' text-decoration-line-through text-muted';
                nomeDiv.style.opacity = '0.6';
            }
            nomeDiv.textContent = String(nome);

            const metaDiv = document.createElement('div');
            metaDiv.className = 'small text-muted';
            metaDiv.textContent = (tipo + ' ' + condivisione).trim();

            left.appendChild(nomeDiv);
            left.appendChild(metaDiv);

            // LOGICA STATI (UNICA) - CAMERIERE VS CLIENTE
            const isCameriere = window.isCameriere === true;

            if (rStato === 4) {
              const badgeAnnullato = document.createElement('span');
              badgeAnnullato.className = 'badge bg-danger ms-2';
              badgeAnnullato.style.fontSize = '0.6rem';
              badgeAnnullato.textContent = 'ANNULLATO';
              nomeDiv.appendChild(badgeAnnullato);
            } 
            // Il Cliente vede "Annulla" solo se stato 0
            // Il Cameriere vede "Storna" se stato 0, 1 o 2
            else if (rStato === 0 || (isCameriere && rStato < 3)) {
              const btnAnnulla = document.createElement('button');
              // Se è un cameriere e il piatto è già in cucina (stato > 0), il tasto è più "serio"
              const isStornoVero = isCameriere && rStato > 0;
              
              btnAnnulla.className = isStornoVero ? 'btn btn-sm btn-danger py-0 px-2 mt-1 d-block' : 'btn btn-sm btn-outline-danger py-0 px-2 mt-1 d-block';
              btnAnnulla.style.fontSize = '0.7rem';
              btnAnnulla.innerHTML = isStornoVero ? '<i class="fas fa-ban me-1"></i>STORNA' : '<i class="fas fa-times me-1"></i>Annulla';
              
              btnAnnulla.onclick = async (e) => {
                e.stopPropagation();
                btnAnnulla.disabled = true;
                btnAnnulla.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                try {
                    const resp = await fetch('../api/ordini/get-stato-riga.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ riga_id: r.id })
                    });
                    const data = await resp.json();

                    if (!data.success) {
                        // Riga già eliminata — ricarica lista
                        await mostraStoricoOrdini();
                        return;
                    }

                    const statoAttuale = data.stato;

                    if (isCameriere) {
                        // Cameriere: stato 0 → elimina, stato 1-2 → storno, stato 3 → bloccato
                        if (statoAttuale === 3) {
                            alert('🍽️ Il piatto è già stato consegnato — impossibile stornare.');
                            await mostraStoricoOrdini();
                        } else if (statoAttuale === 0) {
                            if (confirm('Vuoi eliminare questo piatto? È ancora in attesa.')) {
                                annullaPiattoCliente(r.id, 'cameriere');
                            } else {
                                btnAnnulla.disabled = false;
                                btnAnnulla.innerHTML = '<i class="fas fa-times me-1"></i>Annulla';
                            }
                        } else {
                            // stato 1 o 2 — storno visibile nel conto
                            if (confirm('Stornare questo piatto? Resterà nel conto a €0 come voce stornata.')) {
                                annullaPiattoCliente(r.id, 'cameriere');
                            } else {
                                btnAnnulla.disabled = false;
                                btnAnnulla.innerHTML = '<i class="fas fa-ban me-1"></i>STORNA';
                            }
                        }
                    } else {
                        // Cliente: solo stato 0 permesso
                        if (statoAttuale === 0) {
                            if (confirm('Vuoi annullare questo piatto? È ancora in attesa in cucina.')) {
                                annullaPiattoCliente(r.id, 'cliente');
                            } else {
                                btnAnnulla.disabled = false;
                                btnAnnulla.innerHTML = '<i class="fas fa-times me-1"></i>Annulla';
                            }
                        } else if (statoAttuale === 1) {
                            alert('⚠️ Il cuoco ha già iniziato la preparazione — non è più possibile annullare.');
                            await mostraStoricoOrdini();
                        } else if (statoAttuale === 2) {
                            alert('✅ Il piatto è già pronto — non è possibile annullare.');
                            await mostraStoricoOrdini();
                        } else if (statoAttuale === 3) {
                            alert('🍽️ Il piatto è già stato consegnato — non è possibile annullare.');
                            await mostraStoricoOrdini();
                        } else {
                            btnAnnulla.disabled = false;
                            btnAnnulla.innerHTML = '<i class="fas fa-times me-1"></i>Annulla';
                        }
                    }
                } catch(err) {
                    console.error('Errore verifica stato riga:', err);
                    alert('Errore di connessione — riprova.');
                    btnAnnulla.disabled = false;
                    btnAnnulla.innerHTML = isCameriere
                        ? '<i class="fas fa-ban me-1"></i>STORNA'
                        : '<i class="fas fa-times me-1"></i>Annulla';
                }
              };
              left.appendChild(btnAnnulla);
            }

            // ---- BEGIN: render partecipanti (se presenti) ----
            try {
              if (Array.isArray(r.partecipanti) && r.partecipanti.length > 0) {
                const partWrap = document.createElement('div');
                partWrap.className = 'ordine-partecipanti mt-1';

                // Label "Condiviso con:" (chiaro e idempotente per ogni riga ordine)
                try {
                  const lbl = document.createElement('div');
                  lbl.className = 'ordine-partecipanti-label small text-muted mb-1';
                  lbl.textContent = 'Condiviso con:';
                  lbl.style.fontWeight = '600';
                  lbl.style.fontSize = '0.9em';
                  lbl.style.marginBottom = '6px';
                  partWrap.appendChild(lbl);
                } catch (e) { /* ignore label creation errors */ }

                for (const p of r.partecipanti) {
                  const span = document.createElement('span');
                  span.className = 'partecipante-badge badge bg-light text-dark me-1';
                  span.style.fontWeight = '600';
                  span.style.fontSize = '0.9em';
                  span.textContent = (typeof p === 'string') ? p : (p.lettera || p.sessione_cliente_id || String(p));
                  partWrap.appendChild(span);
                }
                left.appendChild(partWrap);
              }
            } catch (e) {
              // non bloccare il rendering se qualcosa va storto
              console.warn('Errore rendering partecipanti riga ordine:', e);
            }
            // ---- END: render partecipanti ----

            const right = document.createElement('div');
            right.className = 'text-end';
            const infoPrezzo = document.createElement('div');
            infoPrezzo.className = 'small text-muted';
            infoPrezzo.textContent = `${quantita} × ${prezzoUnit.toFixed(2)}€`;
            const totDiv = document.createElement('div');
            totDiv.className = 'fw-bold';
            totDiv.textContent = totaleRiga.toFixed(2) + '€';

            right.appendChild(infoPrezzo);
            right.appendChild(totDiv);

            row.appendChild(left);
            row.appendChild(right);
            body.appendChild(row);
          }
        } else {
          const noneDiv = document.createElement('div');
          noneDiv.className = 'text-muted small py-2';
          noneDiv.textContent = 'Nessuna riga trovata per questo ordine';
          body.appendChild(noneDiv);
        }

        card.appendChild(body);
        elCont.appendChild(card);
      }

      elCont.classList.remove('d-none');
      apriModalMieiOrdini();

    } catch (err) {
      if (elLoading) elLoading.classList.add('d-none');
      if (elVuoto) {
        elVuoto.classList.remove('d-none');
        const txt = document.getElementById('mio-ordini-vuoto-txt');
        if (txt) txt.textContent = 'Errore durante il recupero dello storico ordini (controlla la connessione).';
      }
      console.error('Errore mostraStoricoOrdini:', err);
    }
  }
  async function annullaPiattoCliente(rigaId, ruolo = 'cliente') {
    const msg = ruolo === 'cameriere' ? "STORNANDO questo piatto sparirà dalla cucina. Confermi?" : "Vuoi davvero annullare questo piatto?";
    if (!confirm(msg)) return;
    try {
      const resp = await fetch('../api/ordini/storna-piatto.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ riga_id: rigaId, ruolo: ruolo })
      });
      const res = await resp.json();
      if (res.success) {
        // Usiamo await per assicurarci che i dati siano ricaricati prima di qualsiasi altra azione
        await mostraStoricoOrdini(); 
      } else {
        alert(res.error || "Errore durante l'annullamento");
      }
    } catch (e) {
      console.error(e);
      alert("Errore di comunicazione con il server.");
    }
  }

  // Bind handlers for modal bevande (portato in funzione per init)
  function bindModalBevandeHandlers() {
    const modalEl = document.getElementById('modal-bevande');
    if (!modalEl) return;

    if (!modalEl.dataset.boundBevande) {
      modalEl.addEventListener('shown.bs.modal', async () => {
        try {
          if (!parametriUrl || typeof parametriUrl !== 'object') parametriUrl = {};

          const qs = new URLSearchParams(window.location.search);
          if (!parametriUrl.sessione) parametriUrl.sessione = qs.get('sessione') || null;
          if (!parametriUrl.tavolo) parametriUrl.tavolo = qs.get('tavolo') || null;
          if (!parametriUrl.cliente) parametriUrl.cliente = qs.get('cliente') || null;
          if (!parametriUrl.sessione_cliente_id) parametriUrl.sessione_cliente_id = qs.get('sessione_cliente_id') || parametriUrl.sessione_cliente_id || null;
          if (!parametriUrl.session_id) parametriUrl.session_id = parametriUrl.sessione_cliente_id || parametriUrl.session_id || null;

          if (!parametriUrl.tavolo && parametriUrl.sessione) {
            try {
              const saved = localStorage.getItem('cliente_sessione_' + parametriUrl.sessione);
              if (saved) {
                const obj = JSON.parse(saved);
                if (obj && obj.tavolo) parametriUrl.tavolo = String(obj.tavolo);
              }
            } catch (e) { /* ignore */ }
          }
        } catch (e) {
          console.warn('Errore popolamento parametriUrl nel shown.bs.modal:', e);
        }

                try {
          const pending = modalEl.dataset.pendingBevanda;
          const sel = document.getElementById('select-bevanda');
          if (pending && sel) {
            let matched = false;
            const optByValue = Array.from(sel.options).find(o => o.value === String(pending));
            if (optByValue) {
              sel.value = optByValue.value;
              matched = true;
            } else {
              const pendingStr = String(pending).toLowerCase();
              const optByText = Array.from(sel.options).find(o => (o.text || '').toLowerCase().includes(pendingStr));
              if (optByText) {
                sel.value = optByText.value;
                matched = true;
              }
            }

            if (!matched) {
              const pendingPrezzo = modalEl.dataset.pendingPrezzo || null;
              const pendingNome = modalEl.dataset.pendingNome || null;
              const qtyEl = document.getElementById('quantita-bevanda');
              const out = document.getElementById('prezzo-totale-bevanda');
              try {
                const qty = Math.max(1, parseInt(qtyEl?.textContent || '1', 10) || 1);
                if (pendingPrezzo && out) {
                  out.textContent = (Number(String(pendingPrezzo).replace(',', '.')) * qty).toFixed(2);
                }
                // opzionale: mostra pendingNome in UI se necessario
              } catch (e) { /* ignore */ }
            }

            // rimuovi i flag pending per non riutilizzarli accidentalmente
            try { delete modalEl.dataset.pendingBevanda; delete modalEl.dataset.pendingPrezzo; delete modalEl.dataset.pendingNome; } catch(e){}
          }
        } catch (e) {
          console.warn('Errore nel preselect della bevanda pending:', e);
        }

        try {
          toggleSelettorePartecipanti();
        } catch (e) {
          console.warn('Errore toggleSelettorePartecipanti():', e);
        }

        try {
          const current = modalEl.querySelector('input[name="condivisione-bevanda"]:checked')?.value || document.querySelector('input[name="condivisione-bevanda"]:checked')?.value;
          if (current === 'gruppo') {
            if (typeof popolaSelettorePartecipanti === 'function') {
              await popolaSelettorePartecipanti();
            } else {
              try { popolaSelettorePartecipanti(); } catch(e) { /* ignore */ }
            }
          }
        } catch (e) {
          console.warn('Errore forzando popolaSelettorePartecipanti:', e);
        }

        try {
          function tryFocus(el) {
            if (!el || typeof el.focus !== 'function') return;
            try { el.focus({ preventScroll: true }); } catch (err) { try { el.focus(); } catch (e) { /* ignore */ } }
          }
          const closeBtn = modalEl.querySelector('.btn-close');
          if (closeBtn) {
            const waitAndFocus = (el) => {
              function isVisibleForA11y(node) {
                let cur = node;
                while (cur) {
                  try {
                    if (cur.getAttribute && cur.getAttribute('aria-hidden') === 'true') return false;
                  } catch (e) { /* ignore */ }
                  cur = cur.parentElement;
                }
                return true;
              }
              if (isVisibleForA11y(el)) {
                tryFocus(el);
                return;
              }
              const mo = new MutationObserver(function () {
                if (isVisibleForA11y(el)) {
                  try { mo.disconnect(); } catch (e) {}
                  tryFocus(el);
                }
              });
              try {
                mo.observe(document.documentElement || document.body, { attributes: true, subtree: true, attributeFilter: ['aria-hidden'] });
                setTimeout(function () { try { mo.disconnect(); } catch (e) {} tryFocus(el); }, 500);
              } catch (e) {
                setTimeout(function () { tryFocus(el); }, 50);
              }
            };
            waitAndFocus(closeBtn);
          }
        } catch (e) { /* ignore focus errors */ }
      });

      try {
        if (modalEl) {
          modalEl.querySelectorAll('input[name="condivisione-bevanda"]').forEach(r => {
            if (!r.dataset.boundChange) {
              r.addEventListener('change', () => {
                toggleSelettorePartecipanti();
              });
              r.dataset.boundChange = '1';
            }
          });
        }
      } catch (e) { /* ignore */ }

      modalEl.dataset.boundBevande = '1';
    }
  }

  // API: esporre init/destroy e funzioni globali per compatibilità
  function init() {
    try {
      bindModalBevandeHandlers();
      // caricareOrdiniPrecedenti veniva invocata da inizializzareUI; rimane safe qui per idempotenza
      try { caricareOrdiniPrecedenti(); } catch(e) { /* ignore */ }
    } catch (e) { console.error('MenuModals.init error', e); }
  }

  function destroy() {
    // Non tentiamo di rimuovere ogni listener profondo; le dataset flags evitano duplicazioni.
    // Rimuovi dataset che bloccano ri-binding forzato.
    try {
      const modalEl = document.getElementById('modal-bevande');
      if (modalEl) {
        delete modalEl.dataset.boundBevande;
        delete modalEl.dataset.boundHideFocus;
        delete modalEl.dataset.boundChange;
        delete modalEl.dataset.popolando;
      }
      const mio = document.getElementById('modal-miei-ordini');
      if (mio) delete mio.dataset.accessibilityBound;
    } catch (e) { console.warn('MenuModals.destroy warning', e); }
  }

  // Esponiamo API e funzioni principali sia su window.MenuModals che su window (compatibilità)
  window.MenuModals = window.MenuModals || {};
  window.MenuModals.init = init;
  window.MenuModals.destroy = destroy;

  // Espongo anche le funzioni utilizzate globalmente prima del refactor per compatibilità
  window.apriModalMieiOrdini = apriModalMieiOrdini;
  window.apriModalBevande = apriModalBevande;
  window.aggiungiBevandaAlCarrello = aggiungiBevandaAlCarrello;
  window.popolaSelettorePartecipanti = popolaSelettorePartecipanti;
  window.toggleSelettorePartecipanti = toggleSelettorePartecipanti;
  window.fetchClientiTavolo = fetchClientiTavolo;
  window.caricareOrdiniPrecedenti = caricareOrdiniPrecedenti;
  window.mostraStoricoOrdini = mostraStoricoOrdini;
  window.updateGruppoSummary = updateGruppoSummary;

  // Auto-init se pending
  try {
    if (window._menu_modals_init_pending) {
      try { init(); } catch (e) { console.error('auto-init MenuModals failed', e); }
      delete window._menu_modals_init_pending;
    }
  } catch (e) { /* ignore */ }
})();

// ===== Controlli quantità / aggiungi bevanda con quantità =====
(function () {
  // Parse prezzo dall'opzione: preferisci data-prezzo, altrimenti cerca "€X,XX" nel testo
  function parsePriceFromOption(opt) {
    try {
      if (!opt) return 0;
      if (opt.dataset && opt.dataset.prezzo) return Number(String(opt.dataset.prezzo).replace(',', '.')) || 0;
      const txt = (opt.textContent || opt.innerText || '');
      const m = txt.match(/€\s*([\d\.,]+)/);
      if (m && m[1]) return Number(m[1].replace(',', '.')) || 0;
    } catch (e) { /* ignore */ }
    return 0;
  }

  function getSelectedOption() {
    return document.getElementById('select-bevanda')?.options?.[document.getElementById('select-bevanda').selectedIndex] || null;
  }

  function updatePrezzoTotaleBevanda() {
    const opt = getSelectedOption();
    const qEl = document.getElementById('quantita-bevanda');
    const out = document.getElementById('prezzo-totale-bevanda');
    if (!qEl || !out) return;
    const qty = Math.max(1, parseInt(qEl.textContent, 10) || 1);
    const prezzoUnit = parsePriceFromOption(opt);
    out.textContent = (prezzoUnit * qty).toFixed(2);
  }

  function changeQuantita(delta) {
    const qEl = document.getElementById('quantita-bevanda');
    if (!qEl) return;
    let q = parseInt(qEl.textContent, 10) || 1;
    q = Math.max(1, q + delta);
    qEl.textContent = q;
    updatePrezzoTotaleBevanda();
  }

  try {
    // bind idempotente
    const sel = document.getElementById('select-bevanda');
    const btnMinus = document.getElementById('btn-bevanda-meno');
    const btnPlus = document.getElementById('btn-bevanda-piu');
    const addBtn = document.getElementById('btn-modal-aggiungi-bevanda') || document.getElementById('btn-salva-bevanda');

    if (btnMinus && !btnMinus.dataset.boundCont) {
      btnMinus.addEventListener('click', () => changeQuantita(-1));
      btnMinus.dataset.boundCont = '1';
    }
    if (btnPlus && !btnPlus.dataset.boundCont) {
      btnPlus.addEventListener('click', () => changeQuantita(1));
      btnPlus.dataset.boundCont = '1';
    }
    if (sel && !sel.dataset.boundCont) {
      sel.addEventListener('change', updatePrezzoTotaleBevanda);
      sel.dataset.boundCont = '1';
    }

    // inizializza visuale prezzo
    updatePrezzoTotaleBevanda();

    // handler "Aggiungi al carrello" che rispetta quantità e condivisione
    if (addBtn && !addBtn.dataset.boundQtyHandler) {
      addBtn.addEventListener('click', () => {
        try {
          const opt = getSelectedOption();
          if (!opt) {
            alert('Seleziona una bevanda.');
            return;
          }
          const id = String(opt.value || '');
          // preferisci data-nome se presente, altrimenti testo senza prezzo
          let nome = opt.dataset.nome || opt.getAttribute('data-nome') || opt.textContent || ('Bevanda ' + id);
          // rimuovi la parte " - €..." dal testo se necessario
          nome = nome.replace(/\s*-\s*€[\d\.,]+\s*$/, '').trim();

          const prezzoUnit = parsePriceFromOption(opt);
          const qty = Math.max(1, parseInt(document.getElementById('quantita-bevanda')?.textContent || '1', 10));

          // determina modalità di condivisione
const sharing = document.querySelector('input[name="condivisione-bevanda"]:checked')?.value || 'personale';
          let partecipanti = [];
          if (sharing === 'gruppo' || sharing === 'parziale') {
            const boxes = Array.from(document.querySelectorAll('#partecipanti-list .partecipante-checkbox, #checkbox-partecipanti input[type="checkbox"], #checkbox-partecipanti .partecipante-checkbox'));
            partecipanti = boxes.filter(b => b.checked).map(b => ({
                lettera: b.value,
                nome: b.closest('label')?.querySelector('span.small')?.textContent?.trim() || `Cliente ${b.value}`
            }));
          }
          const nomiPartecipanti = partecipanti
            .filter(p => p.lettera !== (window.parametriUrl?.cliente || ''))
            .map(p => `${p.nome} (${p.lettera})`)
            .join(', ');
          nome = (sharing === 'tavolo')
            ? `${nome} (per tutto il tavolo)`
            : (nomiPartecipanti ? `${nome} — condivisa con ${nomiPartecipanti}` : nome);

          // prepara item da aggiungere (manteniamo compatibilità con struttura esistente)
          const ts = new Date().toISOString();

          const item = {
            id: id,
            nome: nome,
            prezzo: Number(prezzoUnit),     // prezzo unitario
            quantita: Number(qty),
            cliente: (sharing === 'tavolo' ? 'tavolo' : (sharing === 'personale' ? (window.parametriUrl && window.parametriUrl.cliente ? window.parametriUrl.cliente : '') : 'gruppo')),
            partecipanti: partecipanti,
            timestamp: ts
          };

          // usa API centrale per aggiungere (compatibile con AppState / state-shim)
          try {
            if (typeof window.addToCarrello === 'function') {
              window.addToCarrello(item);
            } else if (typeof window.AppState === 'object' && typeof window.AppState.addToCarrello === 'function') {
              window.AppState.addToCarrello(item);
            } else {
              // fallback locale: assicurati che esista l'array e push
              window.carrello = window.carrello || [];
              window.carrello.push(item);
            }
          } catch (e) {
            // se qualcosa fallisce, prova fallback diretto
            try { window.carrello = window.carrello || []; window.carrello.push(item); } catch (e2) { console.warn('Aggiunta item carrello fallita', e, e2); }
          }

          // aggiorna UI carrello usando la funzione esistente (se disponibile)
          try {
            if (typeof aggiornareCarrelloUI === 'function') {
              aggiornareCarrelloUI();
            } else if (typeof updateCartUI === 'function') {
              // fallback possibile
              updateCartUI();
            }
          } catch (e) { console.warn('aggiornareCarrelloUI error', e); }

          // notifica utente
          try {
            if (typeof mostraNotifica === 'function') {
              mostraNotifica(`${qty} × ${nome} aggiunta al carrello`);
            } else {
              // fallback semplice
              console.info(`${qty} × ${nome} aggiunta al carrello`);
            }
          } catch (e) { /* ignore */ }

        } catch (e) {
          console.error('Errore aggiunta bevanda con quantità:', e);
          alert('Errore durante l\'aggiunta della bevanda al carrello.');
        }
      });

      addBtn.dataset.boundQtyHandler = '1';
    }
  } catch (e) {
    console.warn('bind contatore bevande init failed', e);
  }
})();