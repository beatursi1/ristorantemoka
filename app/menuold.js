// menu.js
// ================= VARIABLI GLOBALI =================
let carrello = []; // {id, nome, prezzo, cliente}
let parametriUrl = {};
window.arrivaDaCameriere = false; // <<< AGGIUNTA: flag globale
// Delegazione robusta per il pulsante "Aggiungi al carrello" del modal bevande.
// Usa closest() e viene installata una sola volta (guardata da _bevandeDelegateInstalled).
if (!window._bevandeDelegateInstalled) {
  window._bevandeDelegateInstalled = true;
  document.body.addEventListener('click', function (e) {
    try {
      var target = e.target || e.srcElement;
      var btn = (typeof target.closest === 'function') ? target.closest('#btn-modal-aggiungi-bevanda') : (target.id === 'btn-modal-aggiungi-bevanda' ? target : null);
      if (!btn) return;
      if (typeof aggiungiBevandaAlCarrello === 'function') {
        try {
          aggiungiBevandaAlCarrello();
        } catch (err) {
          console.error('errore in aggiungiBevandaAlCarrello:', err);
        }
      } else {
        console.warn('aggiungiBevandaAlCarrello non definita al momento del click');
      }
    } catch (err) {
      console.error('errore delegazione click #btn-modal-aggiungi-bevanda:', err);
    }
  }, true); // capture phase: più robusto contro stopPropagation su bubbling
}
// Delegazione robusta per il pulsante "Invia alla cucina".
// Installa una sola volta (_inviaDelegateInstalled) e intercetta click anche su elementi figli.
if (!window._inviaDelegateInstalled) {
  window._inviaDelegateInstalled = true;
  document.body.addEventListener('click', function (e) {
    try {
      var target = e.target || e.srcElement;
      var btn = (typeof target.closest === 'function') ? target.closest('#btn-invia-ordine') : (target.id === 'btn-invia-ordine' ? target : null);
      if (!btn) return;

      // Se il bottone è disabilitato, non tentare l'invio
      try {
        if (btn.disabled) { return; }
      } catch (ignore) {}

      if (typeof inviaOrdine === 'function') {
        try {
          inviaOrdine();
        } catch (err) {
          console.error('errore in inviaOrdine:', err);
        }
      } else {
        console.warn('inviaOrdine non definita al momento del click');
      }
    } catch (err) {
      console.error('errore delegazione click #btn-invia-ordine:', err);
    }
  }, true); // capture phase per essere robusti rispetto a stopPropagation
}
// Helper: apri modal "miei ordini" in modo accessibile (pre-blur, focus al shown, blur al hide)
function apriModalMieiOrdini() {
    const modalEl = document.getElementById('modal-miei-ordini');
    if (!modalEl) return;

    // Assicuriamoci di non aggiungere più volte gli stessi listener
    if (!modalEl.dataset.accessibilityBound) {
        // Prima che il modal venga mostrato, assicuriamoci che nessun elemento interno sia attualmente focalizzato
        modalEl.addEventListener('show.bs.modal', () => {
            try {
                const active = document.activeElement;
                if (active && modalEl.contains(active)) {
                    // Sposta provvisoriamente il focus su body per evitare che un discendente resti focalizzato
                    (document.body || document.documentElement).focus();
                    if (document.activeElement === active) active.blur();
                }
            } catch (e) {
                console.warn('Errore durante la rimozione del focus precedente:', e);
            }
        });

               // Quando il modal è completamente aperto, sposta il focus sul bottone di chiusura (in modo sicuro)
        modalEl.addEventListener('shown.bs.modal', () => {
            const closeBtn = modalEl.querySelector('.btn-close');

            // helper locale per mettere il focus in modo sicuro
            function tryFocus(el) {
                if (!el || typeof el.focus !== 'function') return;
                try {
                    el.focus({ preventScroll: true });
                } catch (e) {
                    try { el.focus(); } catch (err) { /* ignore */ }
                }
            }

            if (closeBtn) {
                // aspetta che l'albero non abbia aria-hidden="true" su un antenato prima di focusare
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
                    // osserva le modifiche degli attributi aria-hidden e riprova
                    const mo = new MutationObserver(function (mutations) {
                        if (isVisibleForA11y(el)) {
                            try { mo.disconnect(); } catch (e) {}
                            tryFocus(el);
                        }
                    });
                    try {
                        mo.observe(document.documentElement || document.body, { attributes: true, subtree: true, attributeFilter: ['aria-hidden'] });
                        // fallback timeout: se nulla cambia entriamo comunque dopo un breve delay
                        setTimeout(function () { try { mo.disconnect(); } catch (e) {} tryFocus(el); }, 500);
                    } catch (e) {
                        // se l'observer fallisce, facciamo un semplice delay
                        setTimeout(function () { tryFocus(el); }, 50);
                    }
                };
                waitAndFocus(closeBtn);
            }
        });

        // Prima che il modal venga nascosto, rimuoviamo il focus da eventuali discendenti per evitare warning
        modalEl.addEventListener('hide.bs.modal', () => {
            try {
                const active = document.activeElement;
                if (active && modalEl.contains(active)) {
                    (document.body || document.documentElement).focus();
                    if (document.activeElement === active) active.blur();
                }
            } catch (e) {
                console.warn('Errore durante il blur al hide:', e);
            }
        });

        modalEl.dataset.accessibilityBound = '1';
    }

    const bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();
}

// ================= INIZIALIZZAZIONE =================
window.onload = async function() {
    // 1. Legge parametri dalla URL
    await leggereParametriUrl();
    
    // 2. Carica menu
    await caricareMenu();
    
    // 3. Inizializza UI
    inizializzareUI();
};

// Legge parametri dalla URL e registra automaticamente il cliente
async function leggereParametriUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    
    const sessioneToken = urlParams.get('sessione');
    const tavoloParam = urlParams.get('tavolo');
    const clienteDaCameriere = urlParams.get('cliente');              // es. "B"
    const sessioneClienteId = urlParams.get('sessione_cliente_id');   // id da sessioni_clienti

    // Flag: arrivo dal cameriere se ho cliente o sessione_cliente_id  <<< AGGIUNTA
    window.arrivaDaCameriere = !!clienteDaCameriere || !!sessioneClienteId;

    // Base: proviamo a usare prima la sessione, altrimenti tavolo
    parametriUrl = {
    sessione: sessioneToken || null,
    tavolo: tavoloParam || null,
    cliente: null,
    session_id: null,             // compatibilità interna (vecchio nome)
    sessione_cliente_id: sessioneClienteId || null, // nuovo campo esplicito dal parametro URL
    tavolo_numero: null
};
    // Se arrivo dal cameriere con un cliente già noto (cliente + sessione_cliente_id),
    // imposto direttamente questi parametri e salto la registrazione automatica.
    if (clienteDaCameriere && sessioneClienteId) {
    parametriUrl.cliente = clienteDaCameriere;
    parametriUrl.session_id = sessioneClienteId;            // compatibilità interna
    parametriUrl.sessione_cliente_id = sessioneClienteId;   // nuovo campo esplicito

    // Aggiorna subito la UI cliente corrente
    document.getElementById('cliente-corrente').textContent = clienteDaCameriere;
    document.getElementById('cliente-corrente-container').style.display = 'inline';

    // Salva anche in un oggetto temporaneo per essere persistito dopo la creazione di chiaveStorage
    parametriUrl._cliente_prefill = {
        lettera: clienteDaCameriere,
        session_id: sessioneClienteId,
        sessione_cliente_id: sessioneClienteId,
        timestamp: Date.now()
    };
}

    // Se abbiamo un token di sessione ma non il tavolo, chiediamo al server
    if (parametriUrl.sessione && !parametriUrl.tavolo) {
        try {
            const resp = await fetch('../api/tavoli/sessione-info.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ sessione_token: parametriUrl.sessione })
            });

            const info = await resp.json();
            if (info.success) {
                parametriUrl.tavolo = info.tavolo_id ? String(info.tavolo_id) : null;
                parametriUrl.tavolo_numero = info.tavolo_numero || null;
            } else {
                console.warn('Sessione non valida o non trovata:', info.error);
            }
        } catch (e) {
            console.error('Errore richiesta sessione-info:', e);
        }
    }

    // Aggiorna visualizzazione numero tavolo
    if (parametriUrl.tavolo_numero) {
        document.getElementById('tavolo-numero').textContent = parametriUrl.tavolo_numero;
    } else if (parametriUrl.tavolo) {
        // Fallback: se abbiamo solo l'ID tavolo, mostra quello
        document.getElementById('tavolo-numero').textContent = parametriUrl.tavolo;
    } else {
        document.getElementById('tavolo-numero').textContent = '--';
    }
    
    // Nascondi temporaneamente il cliente corrente
    document.getElementById('cliente-corrente-container').style.display = 'none';
    
    // Chiave per localStorage: se abbiamo la sessione, usiamo quella; altrimenti il tavolo
    let chiaveStorage = null;
    if (parametriUrl.sessione) {
        chiaveStorage = 'cliente_sessione_' + parametriUrl.sessione;
    } else if (parametriUrl.tavolo) {
        chiaveStorage = 'cliente_tavolo_' + parametriUrl.tavolo;
    }

    // 1) Se abbiamo già un cliente precompilato dal cameriere (cliente + sessione_cliente_id),
    //    usiamo quello e salviamo in localStorage, poi usciamo.
    if (parametriUrl._cliente_prefill && chiaveStorage) {
        const dati = parametriUrl._cliente_prefill;

        parametriUrl.cliente = dati.lettera;
        parametriUrl.session_id = dati.session_id || null;

        document.getElementById('cliente-corrente').textContent = dati.lettera;
        document.getElementById('cliente-corrente-container').style.display = 'inline';

        localStorage.setItem(chiaveStorage, JSON.stringify(dati));
        return;
    }

    // 2) Prova a recuperare da localStorage
    if (chiaveStorage) {
    const saved = localStorage.getItem(chiaveStorage);
    if (saved) {
        const dati = JSON.parse(saved);
        if (Date.now() - dati.timestamp < 3600000) { // Entro 1 ora
            parametriUrl.cliente = dati.lettera;
            parametriUrl.session_id = dati.session_id || null;
            // Impostiamo anche il campo esplicito sessione_cliente_id (fallback su session_id se mancante)
            parametriUrl.sessione_cliente_id = dati.sessione_cliente_id || dati.session_id || null;

            document.getElementById('cliente-corrente').textContent = dati.lettera;
            document.getElementById('cliente-corrente-container').style.display = 'inline';
            return;
        }
    }
}
    
    // 3) Se arrivo qui, devo registrare automaticamente il cliente
    await registraClienteAutomaticamente(chiaveStorage);
}
            
async function registraClienteAutomaticamente(chiaveStorage) {
try {
    // Ora dovremmo avere parametriUrl.tavolo impostato anche se siamo partiti da sessione
    let tavoloIdPerRegistrazione = null;

    if (parametriUrl.tavolo) {
        tavoloIdPerRegistrazione = parseInt(parametriUrl.tavolo);
    } else {
        console.warn('Nessun tavolo_id disponibile per la registrazione cliente.');
        document.getElementById('cliente-corrente').textContent = '--';
        return;
    }

    const response = await fetch('../api/clienti/registra-cliente.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            tavolo_id: tavoloIdPerRegistrazione,
            nome: '',
            device_id: navigator.userAgent + '_' + navigator.hardwareConcurrency + '_' + screen.width + 'x' + screen.height
        })
    });
    
    const data = await response.json();
    
    if (data.success) {
    parametriUrl.cliente = data.lettera;
    parametriUrl.session_id = data.session_id || null;              // compatibilità interna
    parametriUrl.sessione_cliente_id = data.session_id || null;     // nuovo campo esplicito

    document.getElementById('cliente-corrente').textContent = data.lettera;
    document.getElementById('cliente-corrente-container').style.display = 'inline';

    // Salva in localStorage (per sessione o per tavolo, a seconda di chiaveStorage)
    if (!chiaveStorage) {
        if (parametriUrl.sessione) {
            chiaveStorage = 'cliente_sessione_' + parametriUrl.sessione;
        } else if (parametriUrl.tavolo) {
            chiaveStorage = 'cliente_tavolo_' + parametriUrl.tavolo;
        }
    }

    if (chiaveStorage) {
        localStorage.setItem(chiaveStorage, JSON.stringify({
            lettera: data.lettera,
            session_id: data.session_id || null,
            sessione_cliente_id: data.session_id || null,
            timestamp: Date.now()
        }));
    }

    mostraNotifica(`Benvenuto Cliente ${data.lettera}!`);
} else {
        document.getElementById('cliente-corrente').textContent = 'ERR';
        alert('Errore: ' + data.error);
    }
} catch (error) {
    console.error('Errore registrazione:', error);
    document.getElementById('cliente-corrente').textContent = 'OFF';
}
}
// Carica menu dall'API
async function caricareMenu() {
    try {
        const response = await fetch('../api/menu/menu.php');
        const data = await response.json();
        
        if (data.success) {
            mostrareMenu(data.data);
        } else {
            document.getElementById('menu').innerHTML = 
                '<div class="alert alert-danger">Errore nel caricamento del menu</div>';
        }
    } catch (error) {
        document.getElementById('menu').innerHTML = 
            '<div class="alert alert-danger">Errore di connessione</div>';
    }
}

// Inizializza UI dopo caricamento dati
function inizializzareUI() {
    // Nascondi loading
    document.getElementById('loading').style.display = 'none';
    document.getElementById('menu').style.display = 'block';
    
    // Setup pulsante bevande
    setupPulsanteBevande();
    // Carica ordini precedenti
    caricareOrdiniPrecedenti();

    // AGGIUNTA: mostra pulsante "Torna alla Home cameriere" se arrivo dal cameriere
    if (window.arrivaDaCameriere) {
        const backContainer = document.getElementById('back-to-cameriere-container');
        const backBtn = document.getElementById('btn-back-to-cameriere');
        if (backContainer && backBtn) {
            backContainer.style.display = 'block';
            backBtn.addEventListener('click', () => {
                window.close();
                setTimeout(() => {
                    if (!window.closed) {
                        alert('Puoi chiudere questa finestra per tornare alla Home cameriere.');
                    }
                }, 200);
            });
        }
    }
    // FINE AGGIUNTA
}

function setupPulsanteBevande() {
    // Aggiungi pulsante bevande solo nella categoria Bevande
    const menuContainer = document.getElementById('menu');
    const categorie = menuContainer.querySelectorAll('.card');
    
    categorie.forEach(categoria => {
        const header = categoria.querySelector('.card-header');
        if (header.textContent.includes('Bevande') || header.textContent.includes('bevande')) {
            // Controlla se il pulsante esiste già
            const existingButton = categoria.querySelector('.btn-aggiungi-bevande');
            if (existingButton) {
                // Se esiste già, rimuovilo per evitare duplicati
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
function mostrareMenu(categorie) {
const menu = document.getElementById('menu');
menu.innerHTML = ''; // pulisci prima

if (!Array.isArray(categorie)) {
    menu.textContent = 'Menu non disponibile';
    return;
}

categorie.forEach(categoria => {
    if (!Array.isArray(categoria.piatti) || categoria.piatti.length === 0) return;

    // Card principale
    const card = document.createElement('div');
    card.className = 'card';

    // Header
    const header = document.createElement('div');
    header.className = 'card-header';
    header.textContent = categoria.nome || 'Categoria';
    card.appendChild(header);

    // Body
    const body = document.createElement('div');
    body.className = 'card-body p-0';

    categoria.piatti.forEach(piatto => {
        // Item container
        const item = document.createElement('div');
        item.className = 'piatto-item';

        // Left column (info)
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
    // icona (statica) come elemento, poi testo sicuro come textContent
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

        // Right column (bottone)
const right = document.createElement('div');

// Controlla se è categoria bevande
const isBevande = categoria.nome && (categoria.nome.toLowerCase().includes('bevande') || categoria.nome.toLowerCase().includes('drink'));

const btn = document.createElement('button');
btn.className = 'btn-ordina';

// Se bevande, pulsante diverso
if (isBevande) {
    btn.innerHTML = `<i class="fas fa-wine-glass me-1"></i>Ordina`;
    btn.addEventListener('click', () => {
        // Imposta pendingBevanda sul modal: lo useremo quando il modal verrà mostrato
        const modalEl = document.getElementById('modal-bevande');
        const val = String(piatto.id);
        if (modalEl) modalEl.dataset.pendingBevanda = val;

        // Tentiamo subito una pre-selezione: prima per value, poi per match sul testo (fallback)
        const sel = document.getElementById('select-bevanda');
        if (sel) {
            // cerca per value
            let opt = Array.from(sel.options).find(o => o.value === val);
            // fallback: cerca nell'etichetta un match col nome del piatto (case-insensitive)
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
        body.appendChild(item);
    });

    card.appendChild(body);
    menu.appendChild(card);
});
}

function aggiungereAlCarrello(id, nome, prezzo) {
    if (!parametriUrl.cliente) {
        alert('Errore: cliente non specificato');
        return;
    }
    
    // Aggiungi al carrello
    carrello.push({
        id: id,
        nome: nome,
        prezzo: prezzo,
        cliente: parametriUrl.cliente,
        timestamp: new Date().toISOString()
    });
    
    // Aggiorna UI
    aggiornareCarrelloUI();
    
    // Notifica
    mostraNotifica(`${nome} aggiunto al carrello`);
}

function rimuovereDalCarrello(index) {
    carrello.splice(index, 1);
    aggiornareCarrelloUI();
}

function svuotaCarrello() {
    if (carrello.length === 0) return;
    
    if (confirm(`Vuoi svuotare tutto il carrello? (${carrello.length} articoli)`)) {
        carrello = [];
        aggiornareCarrelloUI();
    }
}

function aggiornareCarrelloUI() {
    const totaleArticoli = carrello.length;
    const totalePrezzo = carrello.reduce((sum, item) => sum + item.prezzo, 0);
    
    // Aggiorna totali
    document.getElementById('totale-articoli').textContent = totaleArticoli;
    document.getElementById('totale-prezzo').textContent = totalePrezzo.toFixed(2);
    
    // Aggiorna contenuto carrello
    const container = document.getElementById('carrello-contenuto');

    // Svuota in modo sicuro il contenitore
    while (container.firstChild) container.removeChild(container.firstChild);

    if (carrello.length === 0) {
        const p = document.createElement('p');
        p.className = 'text-muted mb-0';
        p.innerHTML = '<em>Carrello vuoto</em>';
        container.appendChild(p);
    } else {
        carrello.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'carrello-item';

            const left = document.createElement('div');
            // nome (testo sicuro)
            const nomeEl = document.createElement('span');
            nomeEl.textContent = item.nome;
            left.appendChild(nomeEl);

            // badge cliente
            const badge = document.createElement('span');
            badge.className = 'cliente-label';
            badge.style.background = getColoreCliente(item.cliente);
            badge.textContent = item.cliente;
            badge.style.marginLeft = '8px';
            left.appendChild(badge);

            const right = document.createElement('div');
            right.className = 'd-flex align-items-center';

            const prezzoEl = document.createElement('span');
            prezzoEl.className = 'fw-bold me-3';
            prezzoEl.textContent = '€' + Number(item.prezzo).toFixed(2);
            right.appendChild(prezzoEl);

            const btnRimuovi = document.createElement('button');
            btnRimuovi.className = 'btn-rimuovi';
            btnRimuovi.type = 'button';
            btnRimuovi.addEventListener('click', () => {
                rimuovereDalCarrello(index);
            });
            const icon = document.createElement('i');
            icon.className = 'fas fa-times';
            btnRimuovi.appendChild(icon);
            right.appendChild(btnRimuovi);

            row.appendChild(left);
            row.appendChild(right);

            container.appendChild(row);
        });
    }
    
    // Mostra/nascondi carrello
    if (totaleArticoli > 0) {
        document.getElementById('carrello').style.display = 'block';
    } else {
        document.getElementById('carrello').style.display = 'none';
    }
}

function getColoreCliente(lettera) {
    const colori = {
        'A': '#3498db', 'B': '#2ecc71', 'C': '#e74c3c',
        'D': '#f39c12', 'E': '#9b59b6', 'F': '#1abc9c',
        'G': '#7f8c8d', 'H': '#34495e', 'I': '#d35400',
        'J': '#16a085', 'K': '#8e44ad', 'L': '#2c3e50'
    };
    return colori[lettera] || '#95a5a6';
}

function mostraNotifica(messaggio) {
    // Creazione notifica temporanea
    const notifica = document.createElement('div');
    notifica.className = 'alert alert-success position-fixed';
    notifica.style.cssText = `
        top: 20px; right: 20px; z-index: 9999;
        animation: fadeInOut 3s ease-in-out;
    `;
    notifica.innerHTML = `<i class="fas fa-check-circle me-2"></i>${messaggio}`;
    
    document.body.appendChild(notifica);
    

    setTimeout(() => {
        notifica.remove();
    }, 3000);
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

                    // Preselect pendingBevanda se presente
                    try {
                        const pending = modalEl.dataset.pendingBevanda;
                        const sel = document.getElementById('select-bevanda');
                        if (pending && sel) {
                            const optByValue = Array.from(sel.options).find(o => o.value === String(pending));
                            if (optByValue) sel.value = optByValue.value;
                            else {
                                const pendingStr = String(pending).toLowerCase();
                                const optByText = Array.from(sel.options).find(o => (o.text || '').toLowerCase().includes(pendingStr));
                                if (optByText) sel.value = optByText.value;
                            }
                            delete modalEl.dataset.pendingBevanda;
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
    
    const tipoCondivisione = (document.querySelector('input[name="condivisione-bevanda"]:checked') || {}).value || 'personale';
    
    // Aggiungi al carrello
    carrello.push({
        id: bevandaId,
        nome: `${nomeBevanda} ${tipoCondivisione === 'tavolo' ? '(per tavolo)' : ''}`,
        prezzo: prezzoBevanda,
        cliente: parametriUrl.cliente,
        timestamp: new Date().toISOString(),
        tipo: 'bevanda',
        condivisione: tipoCondivisione
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

    // Chiudi modal (con un micro-delay per essere più sicuri che il blur sia stato processato)
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

// ================= INVIO ORDINE =================
async function inviaOrdine() {
    if (carrello.length === 0) {
        alert('Il carrello è vuoto!');
        return;
    }
    
    if (!parametriUrl.cliente) {
        alert('Errore: cliente non specificato!');
        return;
    }
    
    // Riepilogo ordine
    let riepilogo = 'RIEPILOGO ORDINE:\n\n';
    
    carrello.forEach(item => {
        riepilogo += `• ${item.nome}: €${item.prezzo.toFixed(2)}\n`;
    });
    
    const totale = carrello.reduce((sum, item) => sum + item.prezzo, 0);
    riepilogo += `\nTOTALE: €${totale.toFixed(2)}`;
    
    if (!confirm(riepilogo + '\n\nConfermi l\'invio dell\'ordine alla cucina?')) {
        return;
    }
    
    // Disabilita il pulsante per evitare invii doppi e mostra spinner
    const btnInvia = document.getElementById('btn-invia-ordine');
    let prevHtml = null;
    if (btnInvia) {
        if (btnInvia.disabled) return; // già in invio
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
// Prima creiamo l'array ordine separatamente per poterlo verificare
const ordine = carrello.map(item => ({
    id: item.id,
    quantita: 1,
    cliente: item.cliente || parametriUrl.cliente,
    nome: item.nome,
    prezzo: item.prezzo
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
    // session_id forzato numerico se possibile (compatibilità)
    session_id: (typeof parametriUrl.sessione_id === 'number' && !isNaN(parametriUrl.sessione_id))
                ? parametriUrl.sessione_id
                : (parametriUrl.sessione_id ? Number(parametriUrl.sessione_id) : (parametriUrl.session_id && !isNaN(Number(parametriUrl.session_id)) ? Number(parametriUrl.session_id) : null)),
    sessione_cliente_id: parametriUrl.sessione_cliente_id || parametriUrl.session_id || null,
    // reincludiamo il token nel body perché l'API sembra ancora richiederlo
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
        // Riabilita il pulsante e ripristina testo
        if (btnInvia) {
            btnInvia.disabled = false;
            if (prevHtml !== null) btnInvia.innerHTML = prevHtml;
        }
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

    // export per debug / eventuale disattivazione
    try { window._ariaHiddenBlurObserver = mo; } catch (e) {}
  } catch (e) {
    // non bloccante
    console.warn('aria-hidden observer init failed', e);
  }
})();
// ================= VISUALIZZA ORDINI PRECEDENTI =================
async function caricareOrdiniPrecedenti() {
    try {
            // Qui chiameremo un'API che restituisce gli ordini del cliente
    // Aggiungiamo il pulsante in modo idempotente (no duplicati)
    const header = document.querySelector('.header .container .row');
    if (header) {
        // Controlla esistenza tramite id univoco; se già presente non creare nulla
        if (!document.getElementById('btn-visualizza-ordini')) {
            const pulsanteStorico = document.createElement('div');
            pulsanteStorico.className = 'text-center mt-3';

            const btn = document.createElement('button');
            btn.id = 'btn-visualizza-ordini';
            btn.type = 'button';
            btn.className = 'btn btn-outline-info btn-sm';
            // Usa addEventListener per collegare la funzione (più affidabile di onclick inline)
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

    // Mostra loading e nascondi contenuto precedente
    if (elLoading) elLoading.classList.remove('d-none');
    if (elVuoto) elVuoto.classList.add('d-none');
    if (elCont) {
        elCont.classList.add('d-none');
        elCont.innerHTML = '';
    }

    // Parametri essenziali
    const tavoloId = parametriUrl.tavolo ? parseInt(parametriUrl.tavolo) : null;
    const sessioneToken = parametriUrl.sessione || null;
    const sessioneClienteId = parametriUrl.sessione_cliente_id || parametriUrl.session_id || null;

    if (!tavoloId || !sessioneToken || !sessioneClienteId) {
        if (elLoading) elLoading.classList.add('d-none');
        if (elVuoto) {
            elVuoto.classList.remove('d-none');
            const txt = document.getElementById('mio-ordini-vuoto-txt');
            if (txt) txt.textContent = 'Impossibile recuperare lo storico: parametri mancanti (tavolo/sessione/sessione_cliente_id).';
        }
        return;
    }

        const apiUrl = '../api/ordini/get-ordini-cliente.php';
    const payload = {
        tavolo_id: tavoloId,
        sessione: sessioneToken,
        sessione_cliente_id: sessioneClienteId
    };

    try {
        const res = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
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
            // Apri comunque il modal per mostrare il messaggio in modo accessibile
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

                // Costruisci DOM sicuro con card professionali (no innerHTML da dati server)
        elCont.innerHTML = ''; // svuota contenitore in modo sicuro

        for (const o of ordini) {
            // Card container
            const card = document.createElement('div');
            card.className = 'card mb-3';

            // Header
            const header = document.createElement('div');
            header.className = 'card-header d-flex justify-content-between align-items-center';

            const leftHeader = document.createElement('div');
            const badgeOrdine = document.createElement('span');
            badgeOrdine.className = 'badge bg-primary me-2';
            badgeOrdine.textContent = 'Ordine #' + (o.id !== undefined ? String(o.id) : '');
            leftHeader.appendChild(badgeOrdine);

            const smallDate = document.createElement('small');
            smallDate.className = 'text-muted';
            // format data in modo sicuro (escapeHtml è per stringhe con possibile HTML)
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
            // sceglie classe badge in base allo stato (come prima)
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
            totaleVal.className = 'fw-bold';
            totaleVal.textContent = Number(o.totale || 0).toFixed(2) + '€';

            statoDiv.appendChild(statoLabel);
            statoDiv.appendChild(badgeStato);
            rightHeader.appendChild(statoDiv);
            rightHeader.appendChild(totaleDiv);
            rightHeader.appendChild(totaleVal);

            header.appendChild(rightHeader);
            card.appendChild(header);

            // Body
            const body = document.createElement('div');
            body.className = 'card-body p-3';

            if (Array.isArray(o.righe) && o.righe.length) {
                for (const r of o.righe) {
                    const nome = r.nome || r.descrizione || 'Voce';
                    const quantita = r.quantita !== undefined ? r.quantita : (r.q || 1);
                    const prezzoUnit = r.prezzo_unitario !== undefined ? Number(r.prezzo_unitario) : (r.prezzo ? Number(r.prezzo) : 0);
                    const totaleRiga = r.totale_riga !== undefined ? Number(r.totale_riga) : (prezzoUnit * quantita);
                    const tipo = r.tipo || '';
                    const condivisione = r.condivisione || '';

                    const row = document.createElement('div');
                    row.className = 'd-flex justify-content-between align-items-start py-2';

                    const left = document.createElement('div');
                    const nomeDiv = document.createElement('div');
                    nomeDiv.className = 'fw-semibold';
                    nomeDiv.textContent = String(nome); // usa textContent per evitare esecuzione HTML
                    const metaDiv = document.createElement('div');
                    metaDiv.className = 'small text-muted';
                    metaDiv.textContent = (tipo + ' ' + condivisione).trim();

                    left.appendChild(nomeDiv);
                    left.appendChild(metaDiv);

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
        // Apri modal in modo accessibile
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
// ======= INIZIATIVE: gestione partecipanti per "Per me e per..." =======

// Recupera i clienti realmente registrati al tavolo/sessione.
// Restituisce array di oggetti { lettera, nome, sessione_cliente_id }
async function fetchClientiTavolo() {
    try {
        // Assicuriamoci che parametriUrl esista e proviamo a popolarlo da querystring / localStorage
        if (!parametriUrl || typeof parametriUrl !== 'object') parametriUrl = {};

        const qs = new URLSearchParams(window.location.search);
        // Popola campi mancanti da query string (utile quando il modal viene aperto prima dell'inizializzazione)
        if (!parametriUrl.sessione) parametriUrl.sessione = qs.get('sessione') || null;
        if (!parametriUrl.tavolo) parametriUrl.tavolo = qs.get('tavolo') || null;
        if (!parametriUrl.cliente) parametriUrl.cliente = qs.get('cliente') || null;
        if (!parametriUrl.sessione_cliente_id) parametriUrl.sessione_cliente_id = qs.get('sessione_cliente_id') || parametriUrl.sessione_cliente_id || null;
        // Mantieni compatibilità con nomi legacy
        if (!parametriUrl.session_id) parametriUrl.session_id = parametriUrl.sessione_cliente_id || parametriUrl.session_id || null;

        // Se manca tavolo ma abbiamo sessione, proviamo a risolvere tramite localStorage (se presente)
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

        // Se ancora mancanti, logghiamo e ritorniamo array vuoto (evita chiamate API non valide)
        if (!tavoloId && !sessioneToken) {
            console.warn('fetchClientiTavolo: parametri tavolo/sessione mancanti dopo tentativi di recupero');
            return [];
        }

        // DIAGNOSTICA: log dettagliato del payload e dello stato parametri prima di chiamare l'API
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

        // Invio compatibile con backend legacy: form-urlencoded e logging dettagliato
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

// Log diagnostico della risposta raw per capire cosa il server vede
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

        // Proviamo a estrarre i clienti da più possibili proprietà
let clientiRaw = [];

// Se il server ha risposto con errore specifico e non possiamo cambiarlo client-side,
// usiamo un fallback locale costruito da localStorage (se presente).
if (data && data.success === false && String(data.error || '').toLowerCase().includes('parametro tavolo')) {
    console.warn('fetchClientiTavolo: server ha segnalato "Parametro tavolo mancante" — uso fallback da localStorage');

    // Segnaliamo che stiamo usando fallback locale (utile per la UI)
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
            // Rimuoviamo eventuali duplicati e ordiniamo in modo semplice
            const seen = new Set();
            clientiRaw = fallback.filter(c => {
                if (!c.lettera) return false;
                if (seen.has(c.lettera)) return false;
                seen.add(c.lettera);
                return true;
            });
            console.log('fetchClientiTavolo fallback clientiRaw:', clientiRaw);
        } else {
            // Nessun dato locale: ritorniamo array vuoto
            return [];
        }
    } catch (e) {
        console.warn('Errore costruzione fallback localStorage:', e);
        return [];
    }
} else {
    // Reset flag (se non usiamo fallback)
    try { parametriUrl._clienti_from_local = false; } catch(e) { /* ignore */ }

    // Caso normale: estraiamo dalla risposta API
    if (Array.isArray(data)) clientiRaw = data;
    else if (data && typeof data === 'object') {
        if (Array.isArray(data.clienti)) clientiRaw = data.clienti;
        else if (Array.isArray(data.data)) clientiRaw = data.data;
        else if (Array.isArray(data.results)) clientiRaw = data.results;
        else if (Array.isArray(data.clienti_registrati)) clientiRaw = data.clienti_registrati;
        else if (data.clienti && typeof data.clienti === 'object') clientiRaw = data.clienti;
        else clientiRaw = data; // fallback: usa tutto l'oggetto
    } else {
        return [];
    }
}

        const clienti = [];

        // Helper per derivare lettera da nome (es. "Mario (A)" oppure "Mario A")
        const deriveLetterFromName = (nome) => {
            if (!nome) return null;
            // cerca pattern "(X)"
            let m = nome.match(/\(([A-Z])\)/);
            if (m && m[1]) return m[1];
            // cerca ultimo token singolo maiuscolo di lunghezza 1
            m = nome.trim().match(/(?:\s|^)([A-Z])$/);
            if (m && m[1]) return m[1];
            // cerca pattern " - A" alla fine
            m = nome.match(/-\s*([A-Z])\s*$/);
            if (m && m[1]) return m[1];
            return null;
        };

        // se clientiRaw è mappa (object) - p.e. { A: {...}, B: {...} }
        if (!Array.isArray(clientiRaw) && typeof clientiRaw === 'object') {
            Object.entries(clientiRaw).forEach(([k, info]) => {
                const letteraCandidate = (typeof k === 'string' && /^[A-Z]$/.test(k)) ? k : null;
                const nome = info && (info.nome || info.nome_cliente || info.nomeCompleto) ? (info.nome || info.nome_cliente || info.nomeCompleto) : null;
                const sessioneId = info && (info.id || info.sessione_cliente_id || info.session_id) ? (info.id || info.sessione_cliente_id || info.session_id) : null;
                let lettera = letteraCandidate || (info && (info.lettera || info.letter)) || null;

                if (!lettera) {
                    // prova a derivare da nome
                    lettera = deriveLetterFromName(nome);
                }

                // se ancora nulla, prova a confrontare sessione con parametriUrl
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

                // se manca lettera, proviamo a ricavarla
                if (!lettera) {
                    // 1) se la sessione corrisponde al cliente corrente
                    if (sessioneId && parametriUrl && (String(sessioneId) === String(parametriUrl.sessione_cliente_id) || String(sessioneId) === String(parametriUrl.session_id))) {
                        lettera = parametriUrl.cliente || null;
                    }
                    // 2) prova a derivare dal nome
                    if (!lettera) {
                        lettera = deriveLetterFromName(nome);
                    }
                    // 3) fallback: se in localStorage esistono mapping client, proviamo a trovarne uno che contenga questo sessioneId
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

                // Rimuovi duplicati, mantieni ordine e sposta cliente corrente in testa
        const map = {};
        const res = [];

        // se abbiamo cliente corrente, assicurati che sia primo
        if (parametriUrl.cliente) {
            const currLetter = String(parametriUrl.cliente);
            const foundCurr = clienti.find(c => String(c.lettera) === currLetter);
            if (foundCurr) {
                map[currLetter] = true;
                res.push(foundCurr);
            } else {
                // aggiungi comunque il cliente corrente se non presente (con nome generico)
                map[currLetter] = true;
                res.push({ lettera: currLetter, nome: `Cliente ${currLetter}`, sessione_cliente_id: parametriUrl.sessione_cliente_id || null });
            }
        }

        clienti.forEach(c => {
            if (c.lettera && !map[c.lettera]) {
                map[c.lettera] = true;
                res.push(c);
            } else if (!c.lettera) {
                // se non ha lettera, lo includiamo comunque con una chiave noletter-...
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
    // Scegli il modal visibile se presente, altrimenti quello con id
    let modalEl = document.querySelector('#modal-bevande.modal.show') || document.getElementById('modal-bevande');
    if (!modalEl) modalEl = document.getElementById('modal-bevande'); // fallback
    let container = modalEl ? modalEl.querySelector('#partecipanti-list') : document.getElementById('partecipanti-list');
    if (!container) return;

    // Evita esecuzioni concorrenti
    if (modalEl && modalEl.dataset.popolando === '1') return;
    try { if (modalEl) modalEl.dataset.popolando = '1'; } catch(e){}

    // Svuota in modo sicuro il container
    try { container.innerHTML = ''; } catch(e) {
        while (container.firstChild) container.removeChild(container.firstChild);
    }

    const clienti = await fetchClientiTavolo();

    // Fallback / avviso locale (inserito nel modal scope)
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
    }

    // Dedupe con Set e inserimento ordinato
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

    // Binding idempotente dei checkbox
    container.querySelectorAll('.partecipante-checkbox').forEach(cb => {
        if (!cb.dataset.boundChange) {
            cb.addEventListener('change', updateGruppoSummary);
            cb.dataset.boundChange = '1';
        }
    });

    updateGruppoSummary();
    try { if (modalEl) delete modalEl.dataset.popolando; } catch(e){}
}
// Aggiorna il testo vicino al radio "Per me e per..."
function updateGruppoSummary() {
    // Scope al modal corrente
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

// Mostra / nasconde il selettore partecipanti a seconda della radio selezionata
function toggleSelettorePartecipanti() {
    // Scope al modal corrente
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

// Bind e comportamento del modal bevande (collegamenti e apertura)
(function bindModalBevandeHandlers() {
    const modalEl = document.getElementById('modal-bevande');
    if (!modalEl) return;

    // Lega handler once
    if (!modalEl.dataset.boundBevande) {
                modalEl.addEventListener('shown.bs.modal', async () => {
            // Prima di tutto: assicuriamoci che parametriUrl sia popolato (utile se il modal viene aperto prima dell'inizializzazione)
            try {
                if (!parametriUrl || typeof parametriUrl !== 'object') parametriUrl = {};

                const qs = new URLSearchParams(window.location.search);
                if (!parametriUrl.sessione) parametriUrl.sessione = qs.get('sessione') || null;
                if (!parametriUrl.tavolo) parametriUrl.tavolo = qs.get('tavolo') || null;
                if (!parametriUrl.cliente) parametriUrl.cliente = qs.get('cliente') || null;
                if (!parametriUrl.sessione_cliente_id) parametriUrl.sessione_cliente_id = qs.get('sessione_cliente_id') || parametriUrl.sessione_cliente_id || null;
                if (!parametriUrl.session_id) parametriUrl.session_id = parametriUrl.sessione_cliente_id || parametriUrl.session_id || null;

                // Se manca tavolo ma abbiamo sessione, proviamo a risolvere tramite localStorage (se presente)
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

            // Se è stata impostata una pendingBevanda, (re)impostala ora che il modal è visibile
            try {
                const pending = modalEl.dataset.pendingBevanda;
                const sel = document.getElementById('select-bevanda');
                if (pending && sel) {
                    // cerca per value esatto
                    const optByValue = Array.from(sel.options).find(o => o.value === String(pending));
                    if (optByValue) {
                        sel.value = optByValue.value;
                    } else {
                        // fallback: prova fuzzy match su testo dell'opzione
                        const pendingStr = String(pending).toLowerCase();
                        const optByText = Array.from(sel.options).find(o => (o.text || '').toLowerCase().includes(pendingStr));
                        if (optByText) sel.value = optByText.value;
                    }
                    // rimuovi il flag pending per non riutilizzarlo accidentalmente
                    delete modalEl.dataset.pendingBevanda;
                }
            } catch (e) {
                console.warn('Errore nel preselect della bevanda pending:', e);
            }

                        // Assicuriamoci che il selettore partecipanti sia correttamente mostrato/populato
            try {
                toggleSelettorePartecipanti();
            } catch (e) {
                console.warn('Errore toggleSelettorePartecipanti():', e);
            }

            // Se la modalità è 'gruppo' forziamo la popolazione (utile se toggle non ha popolato per timing)
            try {
                const current = modalEl.querySelector('input[name="condivisione-bevanda"]:checked')?.value || document.querySelector('input[name="condivisione-bevanda"]:checked')?.value;
                if (current === 'gruppo') {
                    // popolaSelettorePartecipanti è async: aspettiamolo per garantire popolazione prima dell'uso
                    if (typeof popolaSelettorePartecipanti === 'function') {
                        await popolaSelettorePartecipanti();
                    } else {
                        // fallback: tentiamo di chiamare comunque
                        try { popolaSelettorePartecipanti(); } catch(e) { /* ignore */ }
                    }
                }
            } catch (e) {
                console.warn('Errore forzando popolaSelettorePartecipanti:', e);
            }

            // Focus sul bottone di chiusura in modo sicuro: non forzare il focus se un antenato ha aria-hidden="true"
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
                        // osserva le modifiche degli attributi aria-hidden e riprova
                        const mo = new MutationObserver(function () {
                            if (isVisibleForA11y(el)) {
                                try { mo.disconnect(); } catch (e) {}
                                tryFocus(el);
                            }
                        });
                        try {
                            mo.observe(document.documentElement || document.body, { attributes: true, subtree: true, attributeFilter: ['aria-hidden'] });
                            // fallback timeout
                            setTimeout(function () { try { mo.disconnect(); } catch (e) {} tryFocus(el); }, 500);
                        } catch (e) {
                            setTimeout(function () { tryFocus(el); }, 50);
                        }
                    };
                    waitAndFocus(closeBtn);
                }
            } catch (e) { /* ignore focus errors */ }
        });

                // scope radio listeners to this modal to avoid duplicate bindings
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
})();

// ======= fine blocco partecipanti =======