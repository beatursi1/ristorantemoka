// menu.js
// ================= VARIABLI GLOBALI =================
// Single source-of-truth per il carrello: variabile interna + window.carrello getter/setter
let carrello = (Array.isArray(window.carrello) ? window.carrello : (window.carrello || []));
try {
  Object.defineProperty(window, 'carrello', {
    configurable: true,
    enumerable: true,
    get: function() { return carrello; },
    set: function(v) { carrello = v; }
  });
} catch (e) {
  // se defineProperty non è permesso, cade back al valore semplice (meno ideale ma compatibile)
  window.carrello = carrello;
}

let parametriUrl = {};
window.arrivaDaCameriere = false; // <<< AGGIUNTA: flag globale
// Event delegations moved to menu-events.js
if (window.EventDelegator && typeof window.EventDelegator.init === 'function') {
  try { window.EventDelegator.init(); } catch (e) { console.error('EventDelegator.init error', e); }
} else {
  // Se menu-events.js verrà   caricato dopo menu.js, segnaliamo che l'init è pendente:
  window._menu_events_init_pending = true;
}
// Modals logic moved to menu-modals.js
if (window.MenuModals && typeof window.MenuModals.init === 'function') {
  try { window.MenuModals.init(); } catch (e) { console.error('MenuModals.init error', e); }
} else {
  // Se menu-modals.js verrà   caricato dopo menu.js, segnaliamo che l'init è pendente:
  window._menu_modals_init_pending = true;
}
// Legge parametri dalla URL e registra automaticamente il cliente
function mostraErroreSessioneScaduta() {
    // Pulisce tutto il localStorage legato a sessioni precedenti
    Object.keys(localStorage).filter(k => k.startsWith('cliente_')).forEach(k => localStorage.removeItem(k));

    // Nasconde header e contenuto
    document.querySelector('.header') && (document.querySelector('.header').style.display = 'none');
    document.querySelector('#carrello') && (document.querySelector('#carrello').style.display = 'none');

    // Mostra messaggio a schermo intero
    document.body.innerHTML = `
        <div style="
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            min-height:100vh; background:#f0f4f8; padding:30px; text-align:center; font-family:'Segoe UI',sans-serif;">
            <div style="font-size:3rem; margin-bottom:16px;">📷</div>
            <h2 style="color:#2c3e50; font-weight:800; margin-bottom:12px;">Sessione non valida</h2>
            <p style="color:#64748b; font-size:1rem; max-width:320px; line-height:1.5;">
                Questo link non è più attivo.<br>
                Inquadra il <strong>QR code sul tuo tavolo</strong> per accedere al menu.
            </p>
        </div>`;
}

async function leggereParametriUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    
    parametriUrl.sessione = urlParams.get('sessione') || null;
    parametriUrl.tavolo = urlParams.get('tavolo') || null;
    parametriUrl.cliente = urlParams.get('cliente') || null;
    parametriUrl.sessione_cliente_id = urlParams.get('sessione_cliente_id') || urlParams.get('session_id') || null;
    parametriUrl.session_id = parametriUrl.sessione_cliente_id;
    window.arrivaDaCameriere = urlParams.get('cameriere') === '1';
// Imposta flag cameriere per logica pulsante "Annulla"/"Storna"
window.isCameriere = window.arrivaDaCameriere;
    // Verifica sessione: se il token c'è ma il tavolo non è più occupato, blocca tutto
    if (parametriUrl.sessione) {
        try {
            const resp = await fetch('../api/tavoli/sessione-info.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sessione_token: parametriUrl.sessione })
            });
            const info = await resp.json();
            if (info.success) {
                // Sessione trovata: verifica che il tavolo sia ancora occupato
                // Il parametro cameriere=1 nell'URL � l'unico indicatore affidabile
                // che si tratta di un cameriere � NON la presenza di cliente+sessione_cliente_id
                // (quei parametri possono esserci anche in URL vecchi del cliente)
                const arrivaDaCam = window.arrivaDaCameriere; // gi� impostato sopra da urlParams.get('cameriere') === '1'
                if (info.stato !== 'attiva' && !arrivaDaCam) {
                    mostraErroreSessioneScaduta();
                    return;
                }
                parametriUrl.tavolo = info.tavolo_id ? String(info.tavolo_id) : parametriUrl.tavolo;
                parametriUrl.tavolo_numero = info.tavolo_numero || null;
            } else {
                // Token non trovato nel DB: sessione inesistente
                mostraErroreSessioneScaduta();
                return;
            }
        } catch (e) { console.error('Errore sessione-info:', e); }
    } else if (!parametriUrl.tavolo && !parametriUrl.cliente) {
        // Nessun parametro valido: blocca
        mostraErroreSessioneScaduta();
        return;
    }

    // Aggiorna visualizzazione numero tavolo (es. "8")
    const tavoloNumeroEl = document.getElementById('tavolo-numero');
    if (tavoloNumeroEl) {
        tavoloNumeroEl.textContent = parametriUrl.tavolo_numero || parametriUrl.tavolo || '--';
    }

    const chiaveStorage = parametriUrl.sessione ? 'cliente_sessione_' + parametriUrl.sessione : (parametriUrl.tavolo ? 'cliente_tavolo_' + parametriUrl.tavolo : null);

    // Se l'URL ha gi� cliente + sessione_cliente_id validi, usali direttamente
    // Questo copre sia il cameriere che il cliente che torna su una pagina gi� aperta
    if (parametriUrl.cliente && parametriUrl.sessione_cliente_id && chiaveStorage) {
        const dati = {
            lettera:             parametriUrl.cliente,
            sessione_cliente_id: parametriUrl.sessione_cliente_id,
            timestamp:           Date.now()
        };
        // Salva in localStorage per i prossimi accessi
        localStorage.setItem(chiaveStorage, JSON.stringify(dati));
        // Salva in sessionStorage con la chiave di home.html per coerenza
        if (parametriUrl.sessione) {
            const ssKey = 'moka_cliente_' + parametriUrl.sessione;
            try {
                const ssEsistente = sessionStorage.getItem(ssKey);
                if (!ssEsistente) {
                    sessionStorage.setItem(ssKey, JSON.stringify({
                        tavolo_id:    parametriUrl.tavolo,
                        lettera:      parametriUrl.cliente,
                        sessione_cli: parametriUrl.sessione_cliente_id,
                        nome:         ''
                    }));
                }
            } catch(e) { /* ignore */ }
        }

        const cc = document.getElementById('cliente-corrente-letter') || document.getElementById('cliente-corrente');
        if (cc) cc.textContent = dati.lettera;
        const ccc = document.getElementById('cliente-corrente-container');
        if (ccc) ccc.style.display = 'inline';

        if (window.arrivaDaCameriere) return; // cameriere: stop qui
        // cliente: continua per caricare il nome reale dallo storage locale
        const saved = localStorage.getItem(chiaveStorage);
        if (saved) {
            try {
                const parsed = JSON.parse(saved);
                const ccn = document.getElementById('cliente-corrente-nome');
                if (ccn && parsed.nome) ccn.textContent = parsed.nome;
            } catch(e) {}
        }
        return;
    }

    // Se arrivo dal cameriere, salvo i parametri e salto la registrazione
    if (window.arrivaDaCameriere && chiaveStorage) {
        const dati = {
            lettera: parametriUrl.cliente,
            session_id: parametriUrl.sessione_cliente_id,
            sessione_cliente_id: parametriUrl.sessione_cliente_id,
            timestamp: Date.now()
        };
        localStorage.setItem(chiaveStorage, JSON.stringify(dati));
        
        const cc = document.getElementById('cliente-corrente-letter') || document.getElementById('cliente-corrente');
        if (cc) cc.textContent = dati.lettera;
        const ccc = document.getElementById('cliente-corrente-container');
        if (ccc) ccc.style.display = 'inline';
        return;
    }

    // Cerca prima in sessionStorage con la chiave usata da home.html
    // home.html salva con chiave 'moka_cliente_TOKEN' in sessionStorage
    const ssKey = parametriUrl.sessione ? 'moka_cliente_' + parametriUrl.sessione : null;
    if (ssKey) {
        const ssDati = sessionStorage.getItem(ssKey);
        if (ssDati) {
            try {
                const dati = JSON.parse(ssDati);
                if (dati.lettera && dati.sessione_cli) {
                    parametriUrl.cliente              = dati.lettera;
                    parametriUrl.sessione_cliente_id  = dati.sessione_cli;
                    parametriUrl.session_id           = dati.sessione_cli;

                    const cc = document.getElementById('cliente-corrente-letter') || document.getElementById('cliente-corrente');
                    if (cc) cc.textContent = dati.lettera;
                    const ccn = document.getElementById('cliente-corrente-nome');
                    if (ccn && dati.nome) ccn.textContent = dati.nome;
                    const ccc = document.getElementById('cliente-corrente-container');
                    if (ccc) ccc.style.display = 'inline';

                    // Sincronizza anche in localStorage per i refresh successivi
                    if (chiaveStorage) {
                        localStorage.setItem(chiaveStorage, JSON.stringify({
                            lettera:             dati.lettera,
                            sessione_cliente_id: dati.sessione_cli,
                            nome:                dati.nome || '',
                            timestamp:           Date.now()
                        }));
                    }
                    return;
                }
            } catch(e) {
                sessionStorage.removeItem(ssKey);
            }
        }
    }

    // Fallback: cerca in localStorage (refresh successivi o accesso diretto a menu.html)
    const urlHaParametri = !!(parametriUrl.sessione || (parametriUrl.tavolo && parametriUrl.cliente));
    if (chiaveStorage && urlHaParametri) {
        const saved = localStorage.getItem(chiaveStorage);
        if (saved) {
            try {
                const dati = JSON.parse(saved);
                if (Date.now() - dati.timestamp < 3600000) {
                    parametriUrl.cliente = dati.lettera;
                    parametriUrl.sessione_cliente_id = dati.sessione_cliente_id || dati.session_id;

                    const cc = document.getElementById('cliente-corrente-letter') || document.getElementById('cliente-corrente');
                    if (cc) cc.textContent = dati.lettera;
                    const ccn = document.getElementById('cliente-corrente-nome');
                    if (ccn && dati.nome) ccn.textContent = dati.nome;
                    const ccc = document.getElementById('cliente-corrente-container');
                    if (ccc) ccc.style.display = 'inline';
                    return;
                }
            } catch(e) {
                localStorage.removeItem(chiaveStorage);
            }
        }
    }

    await registraClienteAutomaticamente(chiaveStorage);
}
            
/* NEW: risolve nome cliente se mancante (best-effort) */
window.risolviNomeClienteAutomatico = async function() {
  try {
    const sess = parametriUrl.sessione || new URLSearchParams(window.location.search).get('sessione');
    const key = sess ? 'cliente_sessione_' + sess : null;
    if (!key) return;

    const saved = JSON.parse(localStorage.getItem(key) || '{}');
    const updateUI = (nome) => {
        document.querySelectorAll('.header h1, .header h2, #nome-cliente-display, .welcome-msg b, .welcome-text, #cliente-corrente-nome')
                .forEach(el => { el.textContent = nome; });
    };

    if (saved.nome) updateUI(saved.nome);

    const clienti = await fetchClientiTavolo();
    const io = clienti.find(c => String(c.sessione_cliente_id) === String(parametriUrl.sessione_cliente_id));
    
    if (io && io.nome) {
      saved.nome = io.nome;
      localStorage.setItem(key, JSON.stringify(saved));
      updateUI(io.nome);
    }
  } catch(e) { console.warn('risolviNomeClienteAutomatico fallito:', e); }
};

async function registraClienteAutomaticamente(chiaveStorage) {
    try {
        // Blocco definitivo: senza token sessione valido non si registra nulla
        if (!parametriUrl.sessione) {
            console.warn('Nessun token sessione — registrazione bloccata.');
            return;
        }
        const tavoloIdPerRegistrazione = parseInt(parametriUrl.tavolo);
        if (isNaN(tavoloIdPerRegistrazione)) {
            console.warn('Nessun tavolo_id valido per la registrazione cliente.');
            return;
        }

        const response = await fetch('../api/clienti/registra-cliente.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tavolo_id: tavoloIdPerRegistrazione,
                nome: '',
                device_id: navigator.userAgent + '_' + screen.width + 'x' + screen.height
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            parametriUrl.cliente = data.lettera;
            parametriUrl.sessione_cliente_id = data.session_id;
            parametriUrl.session_id = data.session_id;

            const cc = document.getElementById('cliente-corrente-letter') || document.getElementById('cliente-corrente');
            if (cc) cc.textContent = data.lettera;
            const ccc = document.getElementById('cliente-corrente-container');
            if (ccc) ccc.style.display = 'inline';

            if (chiaveStorage) {
                const toSave = {
                    lettera: data.lettera,
                    sessione_cliente_id: data.session_id,
                    timestamp: Date.now()
                };
                localStorage.setItem(chiaveStorage, JSON.stringify(toSave));
            }
            mostraNotifica(`Benvenuto Cliente ${data.lettera}!`);
        } else {
            alert('Errore: ' + data.error);
        }
    } catch (error) {
        console.error('ERRORE registrazione:', error);
    }
}

// Carica menu dall'API
async function caricareMenu() {
    try {
        const response = await fetch('../api/menu/menu.php');
        const data = await response.json();
        
        if (data && data.success) {
            mostrareMenu(data.data);
        } else {
            try { document.getElementById('menu').innerHTML = '<div class="alert alert-danger">Errore nel caricamento del menu</div>'; } catch(e){}
        }
    } catch (error) {
        try { document.getElementById('menu').innerHTML = '<div class="alert alert-danger">Errore di connessione</div>'; } catch(e){}
    }
}

// Inizializza UI dopo caricamento dati
function inizializzareUI() {
    try {
        // Nascondi loading
        const loadingEl = document.getElementById('loading');
        if (loadingEl) loadingEl.style.display = 'none';
        const menuEl = document.getElementById('menu');
        if (menuEl) menuEl.style.display = 'block';
        
                // Setup pulsante bevande
        try { setupPulsanteBevande(); } catch(e){}
        // Carica ordini precedenti
        try { caricareOrdiniPrecedenti(); } catch(e){}

        // Pulsanti home: verde (cliente) o rosso (cameriere)
        // Eseguito qui perch� window.arrivaDaCameriere � gi� impostato da leggereParametriUrl()
        try {
            const btnHomeCliente   = document.getElementById('btn-home-cliente');
            const btnHomeCameriere = document.getElementById('btn-home-back');

            if (window.arrivaDaCameriere) {
                // Cameriere: solo icona rossa ? dashboard
                if (btnHomeCliente)   btnHomeCliente.classList.add('d-none');
                if (btnHomeCameriere) {
                    btnHomeCameriere.classList.remove('d-none');
                    if (!btnHomeCameriere.dataset.boundClick) {
                        btnHomeCameriere.onclick = () => {
                            window.location.href = '../camerieri/dashboard.php';
                        };
                        btnHomeCameriere.dataset.boundClick = '1';
                    }
                }
            } else {
                // Cliente: solo icona verde ? home
                if (btnHomeCameriere) btnHomeCameriere.classList.add('d-none');
                if (btnHomeCliente && !btnHomeCliente.dataset.boundClick) {
                    btnHomeCliente.classList.remove('d-none');
                    btnHomeCliente.addEventListener('click', () => {
                        const p  = new URLSearchParams(window.location.search);
                        const hp = new URLSearchParams();
                        if (p.get('tavolo'))              hp.set('tavolo',              p.get('tavolo'));
                        if (p.get('cliente'))             hp.set('cliente',             p.get('cliente'));
                        if (p.get('sessione_cliente_id')) hp.set('sessione_cliente_id', p.get('sessione_cliente_id'));
                        if (p.get('sessione'))            hp.set('sessione',            p.get('sessione'));
                        window.location.href = 'home.html?' + hp.toString();
                    });
                    btnHomeCliente.dataset.boundClick = '1';
                }
            }
        } catch(e) { console.warn('Errore setup pulsanti home:', e); }

        // Iniezione automatica pulsante bevande rimossa per nuovo flusso

// Pulsante cameriere nell'header: visibile solo se arriva da cameriere
        if (window.arrivaDaCameriere) {
            try {
                // Mostra il pulsante ESCI già presente nell'header
                const btnHeader = document.getElementById('btn-home-back');
                if (btnHeader) {
                    btnHeader.classList.remove('d-none');
                    btnHeader.onclick = () => { window.location.href = '../camerieri/dashboard.php'; };
                }
                // Nascondi la riga extra sotto i bottoni (non serve più)
                const rigaExtra = document.getElementById('back-to-cameriere-row');
                if (rigaExtra) rigaExtra.style.display = 'none';
            } catch(e) { console.warn('Errore setup btn cameriere:', e); }
        }
        // Ensure persistent idempotent bindings for header buttons (in case they were created elsewhere)
        try {
            // bind Ordina bevande button rimosso
            // bind Visualizza ordini button if present
            const bOrd = document.getElementById('btn-visualizza-ordini');
            if (bOrd && !bOrd.dataset.boundClick) {
                bOrd.addEventListener('click', () => { try { mostraStoricoOrdini(); } catch(e) { console.error('mostraStoricoOrdini error', e); } });
                bOrd.dataset.boundClick = '1';
            }
        } catch(e) { /* ignore */ }

    } catch(e) {
        console.error('inizializzareUI error', e);
    }
}
// Inizializzazione della pagina (restore dopo refactor)
window.onload = async function() {
    // 1. Legge parametri dalla URL
    try { await leggereParametriUrl(); } catch(e) { console.error('errore leggereParametriUrl onload', e); }

    // Best-effort: risolvi il nome cliente dal server/localStorage se manca
    try { if (typeof window.risolviNomeClienteAutomatico === 'function') await window.risolviNomeClienteAutomatico(); } catch(e){ console.warn('risolviNomeClienteAutomatico onload failed', e); }

    // 2. Carica menu
    try { await caricareMenu(); } catch(e) { console.error('errore caricareMenu onload', e); }

    // 3. Inizializza UI
    try { inizializzareUI(); } catch(e) { console.error('errore inizializzareUI onload', e); }
};
function setupPulsanteBevande() {
    // Aggiungi pulsante bevande solo nella categoria Bevande
    const menuContainer = document.getElementById('menu');
    const categorie = menuContainer ? menuContainer.querySelectorAll('.card') : [];
    
    categorie.forEach(categoria => {
        const header = categoria.querySelector('.card-header');
        if (header && (header.textContent.includes('Bevande') || header.textContent.includes('bevande'))) {
            // Controlla se il pulsante esiste già  
            const existingButton = categoria.querySelector('.btn-aggiungi-bevande');
            if (existingButton) {
                // Se esiste già  , rimuovilo per evitare duplicati
                existingButton.parentNode.remove();
            }
            
                        // Aggiungi pulsante per aggiungere bevande
            // Il pulsante "Aggiungi altre bevande" è stato rimosso intenzionalmente per evitare
            // conflitti/avvisi ARIA. Se in futuro vuoi riattivarlo, reinserisci qui un button che
            // chiami apriModalBevande(), oppure usa la funzione restore preventiva dell'app.
            // (Nessun elemento viene inserito in questa posizione.)
        }
    });
}

// ================= GESTIONE MENU E CARRELLO =================
// ---------- Nuovo sistema: macrocategorie e dettagli per sottocategorie ----------

/*
  Helper: crea e ritorna un elemento DOM per un singolo piatto (con pulsante Aggiungi / Ordina bevande).
  Riusiamo lo stesso rendering che avevi in precedenza.
*/
function creaElementoPiatto(piatto) {
    const item = document.createElement('div');
    item.className = 'piatto-item';

    const info = document.createElement('div');
    info.style.flex = '1';

    const titolo = document.createElement('h5');
    titolo.className = 'mb-1';
    titolo.textContent = piatto.nome || 'Voce';

    const descr = document.createElement('p');
    descr.className = 'text-muted mb-1 small';
    descr.textContent = piatto.descrizione || '';

    const meta = document.createElement('div');
    meta.className = 'd-flex align-items-center';

    const prezzoSpan = document.createElement('span');
    prezzoSpan.className = 'text-success fw-bold';
    const prezzoVal = (typeof piatto.prezzo === 'number') ? piatto.prezzo : parseFloat(piatto.prezzo) || 0;
    prezzoSpan.textContent = `€${prezzoVal.toFixed(2)}`;
    meta.appendChild(prezzoSpan);

    if (piatto.tempo_preparazione) {
        const badgeTime = document.createElement('span');
        badgeTime.className = 'badge bg-secondary ms-2';
        const iconTime = document.createElement('i');
        iconTime.className = 'fas fa-clock me-1';
        badgeTime.appendChild(iconTime);
        badgeTime.appendChild(document.createTextNode(String(piatto.tempo_preparazione) + ' min'));
        meta.appendChild(badgeTime);
    }
    if (piatto.punti_fedelta) {
        const badgePunti = document.createElement('span');
        badgePunti.className = 'badge bg-warning ms-2';
        const iconStar = document.createElement('i');
        iconStar.className = 'fas fa-star me-1';
        badgePunti.appendChild(iconStar);
        badgePunti.appendChild(document.createTextNode(String(piatto.punti_fedelta) + ' punti'));
        meta.appendChild(badgePunti);
    }

    info.appendChild(titolo);
    info.appendChild(descr);
    info.appendChild(meta);

    const right = document.createElement('div');

    const isBevande = (piatto.categoria && String(piatto.categoria).toLowerCase().includes('bevande')) || (piatto.tipo && String(piatto.tipo).toLowerCase().includes('bevanda'));

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn-ordina';
    try {
      if (piatto && typeof piatto.id !== 'undefined') btn.dataset.id = String(piatto.id);
      if (piatto && typeof piatto.nome !== 'undefined') btn.dataset.nome = String(piatto.nome);
      const _prezzoVal_for_dataset = (typeof prezzoVal !== 'undefined' && !isNaN(prezzoVal)) ? prezzoVal : ((typeof piatto.prezzo === 'number') ? piatto.prezzo : parseFloat(piatto.prezzo) || 0);
      btn.dataset.prezzo = String(_prezzoVal_for_dataset);
    } catch(e){/* ignore dataset errors */}

    if (isBevande) {
        btn.innerHTML = `<i class="fas fa-wine-glass me-1"></i>Ordina`;
        btn.addEventListener('click', () => {
            const modalEl = document.getElementById('modal-bevande');
            const val = String(piatto.id);
            if (modalEl) {
              modalEl.dataset.pendingBevanda = val;
              try {
                if (typeof prezzoVal !== 'undefined' && !isNaN(prezzoVal)) modalEl.dataset.pendingPrezzo = String(prezzoVal);
                if (piatto && piatto.nome) modalEl.dataset.pendingNome = String(piatto.nome);
              } catch (e) { /* ignore dataset errors */ }
            }
            const sel = document.getElementById('select-bevanda');
            if (sel) {
                let opt = Array.from(sel.options).find(o => o.value === val);
                if (!opt && piatto.nome) {
                    const nomeLower = String(piatto.nome).toLowerCase();
                    opt = Array.from(sel.options).find(o => (o.text || '').toLowerCase().includes(nomeLower));
                }
                if (opt) sel.value = opt.value;
            }
            apriModalBevande();
        });
    } else {
        btn.innerHTML = `<i class="fas fa-plus me-1"></i>Aggiungi`;
        btn.addEventListener('click', () => {
            if (typeof aggiungereAlCarrello === 'function') {
                const id = piatto.id;
                const nome = piatto.nome || '';
                const prezzo = (typeof piatto.prezzo === 'number') ? piatto.prezzo : parseFloat(piatto.prezzo) || 0;
                aggiungereAlCarrello(id, nome, prezzo);
            } else {
                alert('Funzione aggiungereAlCarrello non disponibile');
            }
        });
    }

    right.appendChild(btn);
    item.appendChild(info);
    item.appendChild(right);

    return item;
}

/*
  Renderizza una lista di piatti dentro un container (array di oggetti piatto).
*/
function renderListaPiatti(container, piatti) {
    if (!container) return;
    if (!Array.isArray(piatti) || piatti.length === 0) {
        const none = document.createElement('div');
        none.className = 'text-muted small p-2';
        none.textContent = 'Nessun piatto in questa sottocategoria';
        container.appendChild(none);
        return;
    }
    piatti.forEach(p => {
        try {
            const el = creaElementoPiatto(p);
            container.appendChild(el);
        } catch(e) { console.warn('Errore render piatto', e); }
    });
}

/*
  Mostra la lista delle macrocategorie (pagina principale).
  macros: array di oggetti { nome, descrizione?, sottocategorie?: [...], count?: number, id? }
*/
function mostrareMacrocategorie(macros) {
    const menu = document.getElementById('menu');
    if (!menu) return;
    menu.innerHTML = '';

    // Header/Back area: aggiunge un wrapper per il pulsante di ritorno (invisibile nella vista macro)
    const topBar = document.createElement('div');
    topBar.className = 'd-flex align-items-center mb-3';
    const backBtn = document.getElementById('btn-back-to-main-categorie') || document.createElement('button');
    backBtn.id = 'btn-back-to-main-categorie';
    backBtn.type = 'button';
    backBtn.className = 'btn btn-outline-secondary btn-sm me-3 d-none';
    backBtn.textContent = '← Menu principale';
    backBtn.addEventListener('click', () => {
        // quando visibile, riporta alle macrocategorie (qui non dovrebbe essere visibile)
        mostrareMacrocategorie(window._menu_macros || macros);
    });
    topBar.appendChild(backBtn);

    const title = document.createElement('h4');
    title.textContent = 'Categorie';
    title.style.margin = 0;
    topBar.appendChild(title);
    menu.appendChild(topBar);

    // Cards per ogni macro
    const grid = document.createElement('div');
    grid.className = 'macrocategorie-grid';
    macros.forEach((m, idx) => {
        const card = document.createElement('div');
        card.className = 'card mb-3';

        const header = document.createElement('div');
        header.className = 'card-header d-flex justify-content-between align-items-center';
        const nome = document.createElement('div');
        nome.textContent = m.nome || m.titolo || `Categoria ${idx+1}`;
        header.appendChild(nome);

        const openArea = document.createElement('div');
        const openBtn = document.createElement('button');
        openBtn.type = 'button';
        openBtn.className = 'btn btn-outline-primary btn-sm';
        openBtn.textContent = 'Apri';
        openBtn.addEventListener('click', () => {
  try {
    const macroParam = encodeURIComponent(m.nome || m.titolo || String(idx));
    window.location.href = './app/menu-categoria.html?macro=' + macroParam;
  } catch (e) {
    // fallback: mostra la vista nella stessa pagina se qualcosa va storto
    try { mostrareCategoriaView(m); } catch (_) { console.warn('Impossibile navigare o mostrare categoria', e); }
  }
});
        openArea.appendChild(openBtn);
        header.appendChild(openArea);

        card.appendChild(header);

        const body = document.createElement('div');
        body.className = 'card-body small text-muted';
        const descr = document.createElement('div');
        descr.textContent = m.descrizione || m.note || (Array.isArray(m.sottocategorie) ? `${m.sottocategorie.length} sottocategorie` : '');
        body.appendChild(descr);
        card.appendChild(body);

        grid.appendChild(card);
    });

    menu.appendChild(grid);
}

/*
  Mostra la vista per una singola macro: elenca le sottocategorie e le relative pietanze
  macro: oggetto con proprietà   sottocategorie (array) o categories (array) o children
*/
function mostrareCategoriaView(macro) {
    const menu = document.getElementById('menu');
    if (!menu) return;
    menu.innerHTML = '';

    // Topbar con pulsante torni al menu macros
    const topBar = document.createElement('div');
    topBar.className = 'd-flex align-items-center mb-3';

    const backBtn = document.getElementById('btn-back-to-main-categorie') || document.createElement('button');
    backBtn.id = 'btn-back-to-main-categorie';
    backBtn.type = 'button';
    backBtn.className = 'btn btn-outline-secondary btn-sm me-3';
    backBtn.textContent = '← Torna alle categorie';
    backBtn.addEventListener('click', () => {
        mostrareMacrocategorie(window._menu_macros || []);
    });
    topBar.appendChild(backBtn);

    const title = document.createElement('h4');
    title.textContent = macro.nome || macro.titolo || 'Categoria';
    title.style.margin = 0;
    topBar.appendChild(title);

    menu.appendChild(topBar);

    // Trova le sottocategorie
    const subs = macro.sottocategorie || macro.subcategories || macro.children || macro.categorie || macro.categories || [];

    // Se la macro contiene direttamente piatti (senza sottocategorie), mostriamo i piatti direttamente
    if (Array.isArray(macro.piatti) && macro.piatti.length) {
        const container = document.createElement('div');
        container.className = 'list-piatti';
        renderListaPiatti(container, macro.piatti);
        menu.appendChild(container);
        return;
    }

    if (!Array.isArray(subs) || subs.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'text-muted';
        empty.textContent = 'Nessuna sottocategoria disponibile';
        menu.appendChild(empty);
        return;
    }

    // Per ogni sottocategoria: card con header e lista piatti (se presenti)
    subs.forEach(sub => {
        const card = document.createElement('div');
        card.className = 'card mb-3';

        const header = document.createElement('div');
        header.className = 'card-header';
        header.textContent = sub.nome || sub.titolo || 'Sottocategoria';
        card.appendChild(header);

        const body = document.createElement('div');
        body.className = 'card-body p-2';
        // Mostriamo i piatti appartenenti alla sottocategoria
        const piatti = sub.piatti || sub.items || sub.piatti_lista || sub.dishes || [];
        renderListaPiatti(body, piatti);

        card.appendChild(body);
        menu.appendChild(card);
    });
}

/*
  Wrapper intelligente: decide se la risposta API è già   macrocategorie oppure se raggruppare per campo `macro`.
  Accetta la stessa struttura che aveva `mostrareMenu` prima (compatibilità  ).
*/
function mostrareMenu(categorie) {
    const menu = document.getElementById('menu');
    if (!menu) return;
    menu.innerHTML = '';

    if (!Array.isArray(categorie)) {
        menu.textContent = 'Menu non disponibile';
        return;
    }

    // Costruiamo le macrocategorie UNA SOLA VOLTA (evitiamo assegnazioni ripetute a window._menu_macros)
    let macros = null;

    // 1) Se gli elementi hanno già  sottocategorie (struttura a due livelli), trattali come macrocategorie
    const looksLikeMacros = categorie.every(c => c && (Array.isArray(c.sottocategorie) || Array.isArray(c.subcategories) || Array.isArray(c.children) || Array.isArray(c.categorie)));
    if (looksLikeMacros) {
        macros = categorie.slice();
    } else {
        // 2) Se le categorie hanno un campo `macro` (string), raggruppa le categorie sotto le macrocategorie
        const hasMacroField = categorie.some(c => c && typeof c.macro === 'string' && c.macro.trim().length > 0);
        if (hasMacroField) {
            const map = {};
            categorie.forEach(c => {
                const macroName = (c && c.macro) ? String(c.macro).trim() : 'Altro';
                map[macroName] = map[macroName] || { nome: macroName, sottocategorie: [] };
                map[macroName].sottocategorie.push(c);
            });
            macros = Object.keys(map).map(k => map[k]);
        } else {
            // 3) Fallback: lista piatta di categorie -> raggruppa sotto macro "Menu"
            const wrapperMacro = { nome: 'Menu', sottocategorie: [] };
            categorie.forEach(c => {
                const sub = {
                    nome: c.nome || c.titolo || 'Categoria',
                    piatti: Array.isArray(c.piatti) ? c.piatti : (c.items || [])
                };
                wrapperMacro.sottocategorie.push(sub);
            });
            macros = [ wrapperMacro ];
        }
    }

    // Imposta la variabile globale UNA SOLA VOLTA e renderizza la vista macro
    try {
        window._menu_macros = macros;
        if (typeof mostrareMacrocategorie === 'function') {
            mostrareMacrocategorie(macros);
        } else {
            // difensivo: se la funzione non è ancora definita, mostra qualcosa di sensato
            menu.innerHTML = '<div class="alert alert-info">Menu pronto - attendere caricamento renderer...</div>';
            // opzionale: altre strategie fallback qui (MutationObserver, setInterval, ecc.)
        }
    } catch (e) {
        console.warn('Errore preparing macros view', e);
        // fallback: mostra le categorie in forma semplice
        menu.innerHTML = '<div class="alert alert-warning">Impossibile mostrare la vista macrocategorie</div>';
    }
}
// ---------- Fine nuovo sistema ----------

function aggiungereAlCarrello(id, nome, prezzo) {
    if (!window.carrello) window.carrello = [];
    
    // Recupero lettera cliente ultra-robusto
    let c = (window.parametriUrl && window.parametriUrl.cliente) ? window.parametriUrl.cliente : null;
    if (!c || c === '?' || c === '-') {
        const badge = document.getElementById('cliente-corrente-letter');
        if (badge) c = badge.textContent.trim();
    }

    window.carrello.push({
        id: id,
        nome: nome,
        prezzo: parseFloat(prezzo),
        cliente: (c && c !== '-' && c !== '?') ? c : '?',
        timestamp: new Date().toISOString(),
        quantita: 1
    });
    
    if (window.CartUI && window.CartUI.aggiornareCarrelloUI) {
        window.CartUI.aggiornareCarrelloUI();
        window.CartUI.mostraNotifica(`${nome} aggiunto`);
    }
}

function rimuovereDalCarrello(idOrIndex) {
    if (!window.carrello || window.carrello.length === 0) return;

    let idx = -1;
    const stringId = String(idOrIndex);
    
    // Se l'indice passato è piccolo (< di carrello.length), lo trattiamo come indice (dalla modale)
    if (typeof idOrIndex === 'number' && idOrIndex >= 0 && idOrIndex < window.carrello.length) {
        idx = idOrIndex;
    } else {
        // Altrimenti è un ID piatto (dal tasto meno del menu), cerchiamo l'ultima occorrenza
        idx = window.carrello.map(item => String(item.id)).lastIndexOf(stringId);
    }

    if (idx > -1) {
        window.carrello.splice(idx, 1);
        if (window.CartUI && window.CartUI.aggiornareCarrelloUI) {
            window.CartUI.aggiornareCarrelloUI();
        }
    }
}

function svuotaCarrello() {
    if (!window.carrello || window.carrello.length === 0) return;
    if (confirm("Vuoi svuotare tutto il carrello?")) {
        window.carrello = [];
        if (window.CartUI && window.CartUI.aggiornareCarrelloUI) {
            window.CartUI.aggiornareCarrelloUI();
        }
    }
}

// Funzione di delega per compatibilità
function aggiornareCarrelloUI() {
    if (window.CartUI && window.CartUI.aggiornareCarrelloUI) {
        window.CartUI.aggiornareCarrelloUI();
    }
}
function escapeHtml(str) {
if (str === null || str === undefined) return '';
return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
// ================= GESTIONE BEVANDE =================
function apriModalBevande() {
    try {
        // Se il modal non è presente nel DOM, ripristiniamolo dal template (se esiste).
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

        // Bind minimi e idempotenti se non ancora applicati
        try {
            if (!modalEl.dataset.boundBevande) {
                // shown handler: imposteremo preselect / popolamento e focus in modo sicuro più sotto
                modalEl.addEventListener('shown.bs.modal', async () => {
                    try {
                        // Assicuriamoci che parametriUrl sia presente
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
                                // fallback: usa pendingPrezzo/pendingNome per aggiornare il totale del modal
                                const pendingPrezzo = modalEl.dataset.pendingPrezzo || null;
                                const pendingNome = modalEl.dataset.pendingNome || null;
                                const qtyEl = document.getElementById('quantita-bevanda');
                                const out = document.getElementById('prezzo-totale-bevanda');
                                try {
                                    const qty = Math.max(1, parseInt(qtyEl?.textContent || '1', 10) || 1);
                                    if (pendingPrezzo && out) {
                                        out.textContent = (Number(String(pendingPrezzo).replace(',', '.')) * qty).toFixed(2);
                                    }
                                    // opzionale: mostra il nome in un elemento dedicato se lo desideri
                                    // es: document.getElementById('select-bevanda-label')?.textContent = pendingNome || '';
                                } catch (e) { /* ignore */ }
                            }

                            // pulizia flag pending
                            try { delete modalEl.dataset.pendingBevanda; delete modalEl.dataset.pendingPrezzo; delete modalEl.dataset.pendingNome; } catch(e){}
                        }
                    } catch (e) { console.warn('Errore nel preselect della bevanda pending:', e); }

                    // Aggiorna selettore partecipanti (toggle/popola) in modo idempotente
                    try { toggleSelettorePartecipanti(); } catch (e) { /* ignore */ }

                    try {
                        const current = modalEl.querySelector('input[name="condivisione-bevanda"]:checked')?.value || document.querySelector('input[name="condivisione-bevanda"]:checked')?.value;
                        if (current === 'gruppo') {
                            if (typeof popolaSelettorePartecipanti === 'function') await popolaSelettorePartecipanti();
                            else { try { popolaSelettorePartecipanti(); } catch(e) {} }
                        }
                    } catch (e) { /* ignore */ }

                    // Focus sul bottone di chiusura in modo sicuro:
                    try {
                        function tryFocus(el) {
                            if (!el || typeof el.focus !== 'function') return;
                            try { el.focus({ preventScroll: true }); } catch (err) { try { el.focus(); } catch(e){/*ignore*/} }
                        }
                        const closeBtn = modalEl.querySelector('.btn-close');
                        if (closeBtn) {
                            // aspetta che l'albero sia accessibile (nessun antenato con aria-hidden="true")
                            const deadline = Date.now() + 600; // timeout massimo
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

                // radio change binding scoping al modal per evitare duplicazioni
                try {
                    modalEl.querySelectorAll('input[name="condivisione-bevanda"]').forEach(r => {
                        if (!r.dataset.boundChange) {
                            r.addEventListener('change', () => toggleSelettorePartecipanti());
                            r.dataset.boundChange = '1';
                        }
                    });
                } catch(e){ /* ignore */ }

                // Prima che il modal venga nascosto, se un discendente è attualmente focalizzato,
                // spostiamo il focus fuori (body) per evitare che aria-hidden venga bloccato.
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

        // Prima di mostrare: rimuoviamo il focus da qualsiasi elemento attivo (specialmente se è dentro il modal)
        try {
            const active = document.activeElement;
            if (active) {
                // se il focus è dentro il modal o comunque potrebbe restare su un elemento che poi sarà nascosto,
                // blur immediatamente; requestAnimationFrame per lasciare al browser il tempo di processare.
                try { if (active && (modalEl.contains(active) || active.matches && active.matches('#modal-bevande *'))) active.blur(); } catch(e){ try { active.blur(); } catch(e){} }
            }
        } catch(e){/*ignore*/}

        // Mostriamo il modal nel prossimo frame per dare tempo al blur di essere applicato
        const bsModal = new bootstrap.Modal(modalEl);
        requestAnimationFrame(() => {
            try { bsModal.show(); } catch(e) { console.error('Errore mostrando modal-bevande:', e); }
        });
    } catch (err) {
        console.error('apriModalBevande: errore inatteso', err);
    }
}
function aggiungiBevandaAlCarrello() {
    if (!parametriUrl.cliente) {
        alert('Errore: cliente non specificato');
        return;
    }
    
    const select = document.getElementById('select-bevanda');
    const bevandaTesto = select.options[select.selectedIndex].text;
    
    // Estrai nome e prezzo
    const matchNome = bevandaTesto.match(/^([^-€]+)/);
    const matchPrezzo = bevandaTesto.match(/€(\d+\.?\d*)/);
    
    const nomeBevanda = matchNome ? matchNome[0].trim() : bevandaTesto;
    const prezzoBevanda = matchPrezzo ? parseFloat(matchPrezzo[1]) : 0;
    const bevandaId = select.value;
    
const valoriAmmessi = ['personale', 'tavolo', 'parziale'];
const tipoCondivisione = (() => {
    const val = (document.querySelector('input[name="condivisione-bevanda"]:checked') || {}).value || 'personale';
    return valoriAmmessi.includes(val) ? val : 'personale';
})();
    
    // Raccogli lista partecipanti se condivisione === 'gruppo'
    let partecipanti = [];
    if (tipoCondivisione === 'gruppo') {
        try {
            const modalEl = document.getElementById('modal-bevande');
            const container = modalEl ? modalEl.querySelector('#partecipanti-list') : document.getElementById('partecipanti-list');
            if (container) {
                partecipanti = Array.from(container.querySelectorAll('.partecipante-checkbox'))
                    .filter(cb => cb.checked)
                    .map(cb => ({ lettera: cb.value, sessione_cliente_id: cb.dataset.sessioneClienteId || null }));
            }
        } catch(e) {
            partecipanti = [];
        }
    }

    // Aggiungi al carrello (includiamo partecipanti per la condivisione)
    carrello.push({
        id: bevandaId,
        nome: `${nomeBevanda} ${tipoCondivisione === 'tavolo' ? '(per tavolo)' : ''}`,
        prezzo: prezzoBevanda,
        cliente: parametriUrl.cliente,
        timestamp: new Date().toISOString(),
        tipo: 'bevanda',
        condivisione: tipoCondivisione,
        partecipanti: partecipanti
    });
    
    // Prima di chiudere il modal: rimuoviamo il focus dagli elementi attivi all'interno del modal
    try {
        const modalEl = document.getElementById('modal-bevande');
        const active = document.activeElement;
        if (active && modalEl && modalEl.contains(active)) {
            try { active.blur(); } catch (e) { /* ignore */ }
            // garantiamo che il browser abbia applicato il blur prima di chiamare hide()
            // una breve micro-wait riduce la probabilità che bootstrap imposti aria-hidden
        }
    } catch (e) { /* ignore safe */ }

    // Chiudi modal (con un micro-delay per essere più ¹ sicuri che il blur sia stato processato)
    try {
        const modalEl = document.getElementById('modal-bevande');
        const instance = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
        if (instance) {
            // small delay to allow blur to take effect before bootstrap manipola aria-hidden
            setTimeout(() => {
                try { instance.hide(); } catch (e) { /* ignore */ }
            }, 20);
        }
    } catch (e) { /* ignore */ }
    
    // Aggiorna UI
    aggiornareCarrelloUI();
    mostraNotifica(`${nomeBevanda} aggiunta al carrello`);
}

// ================= INVIO ORDINE (MODAL VERSION) =================

/**
 * Apre il modale di riepilogo per mostrare le pietanze selezionate
 */
window.apriRiepilogoCarrello = function() {
    if (carrello.length === 0) {
        mostraNotifica("Il carrello è vuoto!");
        return;
    }

    const listaCont = document.getElementById('riepilogo-lista-piatti');
    const totaleCont = document.getElementById('riepilogo-totale');
    if (!listaCont || !totaleCont) return;

    listaCont.innerHTML = '';
    let totale = 0;

    carrello.forEach((item, index) => {
        totale += item.prezzo;
        const row = document.createElement('div');
        row.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';
        // Recuperiamo il nome attuale dal badge per visualizzarlo nel riepilogo
        const nomeReale = document.getElementById('cliente-corrente-nome') ? document.getElementById('cliente-corrente-nome').textContent.trim() : '';
        const etichettaCliente = (nomeReale && nomeReale !== item.cliente) ? `${nomeReale} (${item.cliente})` : `Cliente ${item.cliente}`;

        row.innerHTML = `
            <div style="flex:1">
                <div class="fw-bold text-dark">${item.nome}</div>
                <small class="text-muted">Per: ${etichettaCliente}</small>
            </div>
            <div class="d-flex align-items-center">
                <span class="me-3 fw-bold text-success">€${item.prezzo.toFixed(2)}</span>
                <button class="btn btn-sm btn-outline-danger border-0 p-2" onclick="rimuovereDalCarrello(${index}); apriRiepilogoCarrello();">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;
        listaCont.appendChild(row);
    });

    totaleCont.textContent = totale.toFixed(2);

    const modalEl = document.getElementById('modal-riepilogo-carrello');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
};

/**
 * Il pulsante INVIA ora apre semplicemente il riepilogo
 */
async function inviaOrdine() {
    apriRiepilogoCarrello();
}

/**
 * Chiamata dal tasto "CONFERMA E INVIA" dentro il modale
 */
window.confermaEInviaOrdine = async function() {
    const modalEl = document.getElementById('modal-riepilogo-carrello');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();
    
    await eseguiInviaOrdineAPI();
};

/**
 * Esegue l'invio tecnico al server (Ex inviaOrdine)
 */
async function eseguiInviaOrdineAPI() {
    if (carrello.length === 0) return;
    
    // Disabilita il pulsante e mostra spinner
    const btnInvia = document.getElementById('btn-invia-ordine');
    let prevHtml = null;
    if (btnInvia) {
        if (btnInvia.disabled) return; // già   in invio
        prevHtml = btnInvia.innerHTML;
        btnInvia.disabled = true;
        btnInvia.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Invio...';
    }        
		// Prepara dati per l'API (assicuriamoci di avere sessione_id valido)
if (!parametriUrl.sessione_id) {
    // Proviamo a risolvere l'ID di sessione dal token (se presente)
    if (parametriUrl.sessione) {
        try {
            const resp = await fetch('../api/tavoli/sessione-info.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sessione_token: parametriUrl.sessione }),
                cache: 'no-store',
                credentials: 'same-origin'
            });
            if (resp.ok) {
                const info = await resp.json();
                if (info && info.success) {
                    // assegniamo possibilmente diversi nomi restituiti dall'API
                    parametriUrl.sessione_id = info.sessione_id || info.sessione_cliente_id || info.session_id || null;
                    if (!parametriUrl.sessione_id && info.data && (info.data.sessione_id || info.data.sessione_cliente_id || info.data.session_id)) {
                        parametriUrl.sessione_id = info.data.sessione_id || info.data.sessione_cliente_id || info.data.session_id;
                    }
                } else {
                    console.warn('sessione-info non ha trovato la sessione:', info);
                }
            } else {
                console.warn('sessione-info HTTP error:', resp.status);
            }
        } catch (e) {
            console.warn('Errore risoluzione sessione-info:', e);
        }
    }
}

// Costruiamo ordineData includendo sessione_id (se disponibile) e reincludiamo sessione_token nel body
// Usa SEMPRE window.carrello direttamente
const sourceCart = Array.isArray(window.carrello) ? window.carrello.slice() : [];

console.log('sourceCart PRIMA del mapping:', JSON.stringify(sourceCart, null, 2));

const ordine = (Array.isArray(sourceCart) ? sourceCart : []).map(item => ({
    id: item.id,
    quantita: Number(item.quantita || item.qty || 1),
    cliente: item.cliente || parametriUrl.cliente,
    nome: item.nome,
    prezzo: item.prezzo,
    tipo: item.tipo || 'piatto',
    condivisione: item.condivisione || 'personale',
    partecipanti: Array.isArray(item.partecipanti)
      ? item.partecipanti
      : (item.partecipanti ? [item.partecipanti] : [])
}));

// Se non ci sono articoli, blocchiamo l'invio immediatamente
if (!Array.isArray(ordine) || ordine.length === 0) {
    alert('Il carrello è vuoto. Aggiungi almeno un articolo prima di inviare.');
    // Se il pulsante era stato disabilitato, riabilitiamolo e ripristiniamone il testo
    if (typeof btnInvia !== 'undefined' && btnInvia) {
        btnInvia.disabled = false;
        if (typeof prevHtml !== 'undefined' && prevHtml !== null) btnInvia.innerHTML = prevHtml;
    }
    return;
}

const ordineData = {
    // sessione_id richiesto dal server: preferiamo parametriUrl.sessione_id risolto
    sessione_id: parametriUrl.sessione_id || parametriUrl.sessione_cliente_id || parametriUrl.session_id || null,
    // session_id forzato numerico se possibile (compatibilità  )
    session_id: (typeof parametriUrl.sessione_id === 'number' && !isNaN(parametriUrl.sessione_id))
                ? parametriUrl.sessione_id
                : (parametriUrl.sessione_id ? Number(parametriUrl.sessione_id) : (parametriUrl.session_id && !isNaN(Number(parametriUrl.session_id)) ? Number(parametriUrl.session_id) : null)),
    sessione_cliente_id: parametriUrl.sessione_cliente_id || parametriUrl.session_id || null,
    // reincludiamo il token nel body perchè l'API sembra ancora richiederlo
    sessione_token: parametriUrl.sessione || null,
    tavolo_id: parametriUrl.tavolo ? parseInt(parametriUrl.tavolo) : null,
    ordine: ordine
};
try {
        // Invia all'API usando Authorization header per la sessione (se presente)
        const headers = { 'Content-Type': 'application/json' };
        if (parametriUrl.sessione) headers['Authorization'] = 'Bearer ' + parametriUrl.sessione;

        const response = await fetch('../api/ordini/crea-ordine.php', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(ordineData),
            cache: 'no-store',
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (result.success) {
            mostraNotifica('Ordine registrato con successo!');
            
            // Reset carrello
            carrello = [];
            aggiornareCarrelloUI();
            
        } else {
            alert('ERRORE nell\'invio dell\'ordine: ' + (result.error || 'Errore sconosciuto'));
        }
        
    } catch (error) {
        alert('ERRORE di connessione: ' + error.message);
    } finally {
        // Riabilita il pulsante e ripristina testo (safe guard)
        try {
            if (typeof btnInvia !== 'undefined' && btnInvia) {
                btnInvia.disabled = false;
                if (typeof prevHtml !== 'undefined' && prevHtml !== null) {
                    try { btnInvia.innerHTML = prevHtml; } catch(e){}
                }
            }
        } catch(e){}
    }
}

// ================= STILI DINAMICI =================
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInOut {
        0% { opacity: 0; transform: translateY(-20px); }
        10% { opacity: 1; transform: translateY(0); }
        90% { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(-20px); }
    }
`;
document.head.appendChild(style);

// Fix globale: se un elemento diventa aria-hidden="true" e contiene l'elemento con focus,
// rimuoviamo/trasferiamo il focus immediatamente per evitare warning ARIA.
(function () {
  try {
    if (!('MutationObserver' in window)) return;
    const onAttr = function (mutations) {
      try {
        mutations.forEach(m => {
          if (m.type !== 'attributes') return;
          if (m.attributeName !== 'aria-hidden') return;
          const target = m.target;
          try {
            if (!target) return;
            const val = target.getAttribute && target.getAttribute('aria-hidden');
            if (String(val) !== 'true') return;
            const active = document.activeElement;
            if (!active) return;
            // se l'elemento attivo è dentro il target (o è il target), rimuoviamo focus
            if (target.contains(active)) {
              try {
                // prima blur diretto
                active.blur && active.blur();
              } catch (e) {}
              try {
                // spostiamo il focus su body in modo sicuro (per assistive tech)
                (document.body || document.documentElement).focus && (document.body || document.documentElement).focus();
              } catch (e) {}
            }
          } catch (e) { /* ignore per singola mutation */ }
        });
      } catch (e) { /* ignore */ }
    };

    const mo = new MutationObserver(onAttr);
    mo.observe(document.documentElement || document.body, { attributes: true, subtree: true, attributeFilter: ['aria-hidden'] });

    // export for debug / eventuale disattivazione
    try { window._ariaHiddenBlurObserver = mo; } catch (e) {}
  } catch (e) {
    // non bloccante
    console.warn('aria-hidden observer init failed', e);
  }
})();