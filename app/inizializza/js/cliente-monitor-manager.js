/**
 * cliente-monitor-manager.js Gestore monitor clienti
 * Aggiorna la lista clienti e gestisce la UI della sezione monitor
 */

class ClienteMonitorManager {
  constructor(configManager, apiService) {
    this.configManager = configManager;
    this.apiService = apiService;
    this.elementiUI = {
      listaClienti: null,
      conteggioClienti: null,
      sezioneMonitor: null
    };
    this.aggiornamentoIntervallo = null;
    this.frequenzaAggiornamento = 5000; // 5 secondi
    this.clientiCache = new Map();
  }

  /**
   * Inizializza il monitor clienti
   * @param {Object} elementi - Elementi UI da gestire
   * @returns {Promise<boolean>}
   */
  async inizializza(elementi = {}) {
    try {
      // Configura elementi UI - usa solo gli elementi forniti o cerca quelli esistenti
      this.elementiUI = {
        listaClienti: elementi.listaClienti || document.getElementById('clienti-registrati-container'),
        conteggioClienti: elementi.conteggioClienti || null, // Non esiste nel sistema attuale
        sezioneMonitor: elementi.sezioneMonitor || document.getElementById('fase2'),
        ...elementi
      };

      // Verifica solo gli elementi critici
      if (!this.elementiUI.listaClienti) {
        console.warn('Elemento listaClienti non trovato');
      }

      // Avvia aggiornamento automatico
      this.avviaAggiornamentoAutomatico();

      console.log('ClienteMonitorManager inizializzato con successo');
      return true;
    } catch (error) {
      console.error('Errore inizializzazione ClienteMonitorManager:', error);
      throw error;
    }
  }

  /**
   * Avvia aggiornamento automatico della lista clienti
   */
  avviaAggiornamentoAutomatico() {
    if (this.aggiornamentoIntervallo) {
      clearInterval(this.aggiornamentoIntervallo);
    }

    // Aggiorna immediatamente
    this.aggiornaListaClienti();

    // Poi ogni X secondi
    this.aggiornamentoIntervallo = setInterval(
      () => this.aggiornaListaClienti(),
      this.frequenzaAggiornamento
    );
  }

  /**
   * Ferma aggiornamento automatico
   */
  fermaAggiornamentoAutomatico() {
    if (this.aggiornamentoIntervallo) {
      clearInterval(this.aggiornamentoIntervallo);
      this.aggiornamentoIntervallo = null;
    }
  }

  /**
   * Aggiorna la lista clienti dal server
   * @returns {Promise<Array>} Lista clienti aggiornata
   */
  async aggiornaListaClienti() {
    try {
      // Recupera clienti dal server - usa il metodo esistente
      // Prima proviamo a ottenere il tavolo selezionato
      const tavolo = window.tavoloManager && window.tavoloManager.getTavoloSelezionato
        ? window.tavoloManager.getTavoloSelezionato()
        : null;

      let clienti = [];
      
     if (tavolo && this.apiService.getClientiRegistrati) {
  const response = await this.apiService.getClientiRegistrati(tavolo.id);
  if (response.success && response.clienti) {
    // Converti l'oggetto clienti in array
    clienti = Object.values(response.clienti);
      console.log('Clienti grezzi dal server:', clienti);
if (clienti.length > 0) {
  console.log('Primo cliente GREZZO:', clienti[0]);
}
    // Normalizza i dati dal server (senza decidere QR/manuale)
clienti = clienti.map(cliente => {
  return {
    ...cliente,
    identificativo: cliente.identificativo || cliente.lettera,
    lettera: cliente.lettera || cliente.identificativo,
    stato: cliente.stato || 'attivo'
  };
});

    console.log('Clienti dal server (normalizzati):', clienti);
    if (clienti.length > 0) {
      console.log('Primo cliente normalizzato:', {
        id: clienti[0].id,
        identificativo: clienti[0].identificativo,
        nome: clienti[0].nome,
        tipo: clienti[0].tipo,
        origine: clienti[0].origine,
        stato: clienti[0].stato,
        lettera: clienti[0].lettera
      });
    }
  }
}
      
      // 1. Ottieni anche clienti configurati manualmente
if (window.clienteConfigManager && tavolo) {
  const clientiConfigurati = window.clienteConfigManager.cercaConfigurazioni({
    'metadata.tavoloId': tavolo.id,
    attiva: true
  });
  
  // Combina i due array (aggiornando nome se il cliente esiste già)
  clientiConfigurati.forEach(config => {
    const clienteIdConfig = config.clienteId;
    
    // cerca se esiste già un cliente con quella lettera/identificativo
    const indiceEsistente = clienti.findIndex(c =>
      (c.identificativo === clienteIdConfig) || (c.lettera === clienteIdConfig)
    );
    
    if (indiceEsistente !== -1) {
      // Cliente già presente (es. registrato via QR)
      // Aggiorniamo solo i dati "amichevoli" (nome), SENZA cambiare origine/stato
      const clienteEsistente = clienti[indiceEsistente];
      const nuovoNome = config.nome || (config.metadata && config.metadata.nome);
      
      if (nuovoNome && nuovoNome !== clienteEsistente.nome) {
        console.log('Aggiorno nome cliente esistente da configurazione:', {
          lettera: clienteEsistente.lettera || clienteEsistente.identificativo,
          nome_vecchio: clienteEsistente.nome,
          nome_nuovo: nuovoNome
        });
        
        clienti[indiceEsistente] = {
          ...clienteEsistente,
          nome: nuovoNome
        };
      }
    } else {
      // Cliente non esiste ancora: creiamo un cliente "manuale" puro
      const clienteManuale = {
        id: config.id,
        identificativo: clienteIdConfig,
        lettera: clienteIdConfig,
        nome: config.nome || (config.metadata && config.metadata.nome) || 'Cliente Manuale',
        tavolo_id: tavolo.id,
        tavolo_numero: tavolo.numero,
        stato: 'manuale',
        origine: 'manuale',
        creazione: (config.metadata && config.metadata.creazione) || new Date().toISOString()
      };
      clienti.push(clienteManuale);
      console.log('Cliente manuale aggiunto:', clienteManuale);
    }
  });
}
      
      // Aggiorna cache locale
      this._aggiornaCacheClienti(clienti);
      
      // Aggiorna UI
      this._aggiornaUIListaClienti(clienti);
      
      // Aggiorna conteggio (se l'elemento esiste)
      this._aggiornaConteggioClienti(clienti);
      
      // Emetti evento
      this._emitEvent('lista-clienti-aggiornata', { clienti });
      
      return clienti;
    } catch (error) {
      console.error('Errore aggiornamento lista clienti:', error);
      this._mostraErrore('Impossibile aggiornare la lista clienti');
      return [];
    }
  }

  /**
   * Aggiorna la lista clienti per un tavolo specifico
   * @param {string} tavoloId - ID del tavolo
   * @returns {Promise<Array>} Clienti del tavolo
   */
  async aggiornaClientiTavolo(tavoloId) {
    try {
      let clientiTavolo = [];
      
      if (this.apiService.getClientiRegistrati) {
        const response = await this.apiService.getClientiRegistrati(tavoloId);
        if (response.success && response.clienti) {
          // Converti l'oggetto clienti in array
          clientiTavolo = Object.values(response.clienti);
        }
      }
      
      this._aggiornaUIListaClienti(clientiTavolo, tavoloId);
      return clientiTavolo;
    } catch (error) {
      console.error(`Errore aggiornamento clienti tavolo ${tavoloId}:`, error);
      return [];
    }
  }

  /**
   * Filtra la lista clienti
   * @param {Object} filtri - Filtri da applicare
   */
  filtraClienti(filtri = {}) {
    const clientiFiltrati = this._applicaFiltri(filtri);
    this._aggiornaUIListaClienti(clientiFiltrati);
  }

  /**
   * Cerca un cliente specifico
   * @param {string} query - Testo da cercare
   * @returns {Array} Risultati della ricerca
   */
  cercaCliente(query) {
    if (!query || query.trim() === '') {
      this.aggiornaListaClienti();
      return [];
    }

    const risultati = Array.from(this.clientiCache.values()).filter(cliente =>
      this._clienteCorrispondeRicerca(cliente, query)
    );

    this._aggiornaUIListaClienti(risultati);
    return risultati;
  }

  /**
   * Aggiunge un cliente alla lista (per aggiornamenti in tempo reale)
   * @param {Object} cliente - Dati del cliente
   */
  aggiungiCliente(cliente) {
    if (!cliente || !cliente.id) return;

    this.clientiCache.set(cliente.id, cliente);
    this._aggiornaUIListaClienti(Array.from(this.clientiCache.values()));
    this._aggiornaConteggioClienti();
  }

  /**
   * Rimuove un cliente dalla lista
   * @param {string} clienteId - ID del cliente
   */
  rimuoviCliente(clienteId) {
    this.clientiCache.delete(clienteId);
    this._aggiornaUIListaClienti(Array.from(this.clientiCache.values()));
    this._aggiornaConteggioClienti();
  }


  /**
   * Aggiorna lo stato di un cliente
   * @param {string} clienteId - ID del cliente
   * @param {Object} updates - Aggiornamenti da applicare
   */
  aggiornaStatoCliente(clienteId, updates) {
    const cliente = this.clientiCache.get(clienteId);
    if (!cliente) return;

    const clienteAggiornato = { ...cliente, ...updates };
    this.clientiCache.set(clienteId, clienteAggiornato);
    
    // Aggiorna solo l'elemento specifico
    this._aggiornaElementoClienteUI(clienteId, clienteAggiornato);
  }

  /**
   * Ottiene statistiche clienti
   * @returns {Object} Statistiche
   */
  getStatistiche() {
    const clienti = Array.from(this.clientiCache.values());
    
    return {
      totale: clienti.length,
      attivi: clienti.filter(c => c.stato === 'attivo').length,
      inAttesa: clienti.filter(c => c.stato === 'in_attesa').length,
      completati: clienti.filter(c => c.stato === 'completato').length,
      perTavolo: this._contaClientiPerTavolo(clienti),
      timestamp: new Date().toISOString()
    };
  }

  /**
   * Esporta dati clienti
   * @returns {Object} Dati esportati
   */
  esportaDati() {
    return {
      clienti: Array.from(this.clientiCache.values()),
      cacheSize: this.clientiCache.size,
      ultimoAggiornamento: new Date().toISOString(),
      statistiche: this.getStatistiche()
    };
  }

  // Metodi privati

  _verificaElementiUI() {
    const elementiMancanti = [];
    
    Object.entries(this.elementiUI).forEach(([nome, elemento]) => {
      if (!elemento) {
        elementiMancanti.push(nome);
      }
    });

    if (elementiMancanti.length > 0) {
      console.warn('Elementi UI mancanti:', elementiMancanti);
    }
  }

  _aggiornaCacheClienti(clienti) {
    // Aggiorna cache con nuovi clienti
    clienti.forEach(cliente => {
      this.clientiCache.set(cliente.id, cliente);
    });

    // Rimuovi clienti non più presenti
    const idsAggiornati = clienti.map(c => c.id);
    const idsDaRimuovere = Array.from(this.clientiCache.keys())
      .filter(id => !idsAggiornati.includes(id));
    
    idsDaRimuovere.forEach(id => this.clientiCache.delete(id));
  }

  _aggiornaUIListaClienti(clienti, tavoloId = null) {
    if (!this.elementiUI.listaClienti) return;

    const lista = this.elementiUI.listaClienti;
    
    // Svuota lista
    lista.innerHTML = '';

    if (!clienti || clienti.length === 0) {
      this._mostraMessaggioVuoto(lista, tavoloId);
      return;
    }

    // Ordina clienti
    const clientiOrdinati = this._ordinaClienti(clienti);

    // Crea elementi UI
    clientiOrdinati.forEach(cliente => {
      const elemento = this._creaElementoCliente(cliente);
      lista.appendChild(elemento);
    });

    // Aggiungi gestori eventi
    this._aggiungiGestoriEventiLista();
  }

  _aggiornaConteggioClienti(clienti = null) {
    // Se conteggioClienti non esiste, salta semplicemente
    if (!this.elementiUI.conteggioClienti) {
      return;
    }

    const conteggio = clienti 
      ? clienti.length 
      : this.clientiCache.size;
    
    this.elementiUI.conteggioClienti.textContent = conteggio;
    this.elementiUI.conteggioClienti.dataset.totale = conteggio;
    
    // Aggiorna classe in base al conteggio
    this.elementiUI.conteggioClienti.classList.toggle('alto', conteggio > 10);
    this.elementiUI.conteggioClienti.classList.toggle('medio', conteggio > 5 && conteggio <= 10);
    this.elementiUI.conteggioClienti.classList.toggle('basso', conteggio <= 5);
  }

  _creaElementoCliente(cliente) {
    const div = document.createElement('div');
    
    // Usa la classe esistente dal tuo CSS
    div.className = 'cliente-monitor';
    if (cliente.stato === 'attivo' || cliente.stato === 'manuale') {
      div.classList.add('registrato');
    }
    
    // Stili consistenti con il sistema esistente
    div.style.cssText = `
        background: white;
        border-radius: 8px;
        padding: 12px 15px;
        margin: 8px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid ${cliente.stato === 'manuale' ? '#3498db' : '#27ae60'};
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    `;
    
    div.dataset.clienteId = cliente.id;
    div.dataset.tavoloId = cliente.tavolo_id || 'sconosciuto';
    div.dataset.lettera = cliente.identificativo || cliente.lettera || '';

let statoCliente = cliente.stato || 'attivo';
let iconaStato = this._getIconaStato(statoCliente);

// Regola: se il DB dice esplicitamente "qr", è QR e basta.
// Solo se tipo è "manuale" lo consideriamo manuale.
//origine è solo un fallback quando tipo è assente.
let tipoEffettivo = 'qr';
if (cliente.tipo === 'manuale') {
  tipoEffettivo = 'manuale';
} else if (!cliente.tipo && cliente.origine === 'manuale') {
  tipoEffettivo = 'manuale';
}

let tipoTesto = tipoEffettivo === 'manuale'
  ? ' <span class="badge bg-secondary ms-1">MANUALE</span>'
  : ' <span class="badge bg-info ms-1">QR</span>';
    
    let letteraCliente = cliente.identificativo || cliente.lettera || '?';
    
    // Contenuto organizzato
    div.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="
                width: 30px;
                height: 30px;
                background: ${cliente.stato === 'manuale' ? '#3498db' : '#27ae60'};
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 14px;
            ">
                ${letteraCliente}
            </div>
            <div>
                <div style="font-weight: 600; color: #2c3e50;">
                    ${cliente.nome || 'Cliente'}${tipoTesto}
                </div>
                <div style="font-size: 0.85em; color: #666; margin-top: 2px;">
                    ${iconaStato}
                </div>
            </div>
        </div>
        <div style="color: #666; font-size: 0.9em;">
            Tavolo: ${cliente.tavolo_numero || 'N/A'}
        </div>
    `;

    return div;
  }

  _aggiornaElementoClienteUI(clienteId, cliente) {
    const elemento = this.elementiUI.listaClienti.querySelector(
      `[data-cliente-id="${clienteId}"]`
    );
    
    if (elemento) {
      // Aggiorna classi stato
      elemento.className = `cliente-monitor ${cliente.stato === 'attivo' ? 'registrato' : ''}`;
      
      // Aggiorna contenuto
      const nomeEl = elemento.querySelector('.cliente-nome');
      const statoBadge = elemento.querySelector('.cliente-stato-badge');
      const azioniEl = elemento.querySelector('.cliente-azioni');
      
      if (nomeEl) nomeEl.textContent = cliente.nome || 'Cliente';
      if (statoBadge) statoBadge.innerHTML = this._getIconaStato(cliente.stato);
      
      // Aggiorna azioni se stato cambiato
      if (azioniEl && cliente.stato === 'completato') {
        const btnCompleta = azioniEl.querySelector('[data-azione="completa"]');
        if (btnCompleta) btnCompleta.remove();
      }
    }
  }

  _mostraMessaggioVuoto(lista, tavoloId) {
    const messaggio = document.createElement('div');
    messaggio.className = 'lista-vuota-messaggio';
    
    if (tavoloId) {
      messaggio.innerHTML = `
        <i class="fas fa-users-slash"></i>
        <p>Nessun cliente al tavolo ${tavoloId}</p>
      `;
    } else {
      messaggio.innerHTML = `
        <i class="fas fa-users"></i>
        <p>Nessun cliente attivo</p>
        <small>I clienti appariranno qui dopo la registrazione</small>
      `;
    }
    
    lista.appendChild(messaggio);
  }

  _aggiungiGestoriEventiLista() {
    const elementi = this.elementiUI.listaClienti.querySelectorAll('.cliente-item');
    
    elementi.forEach(elemento => {
      // Click sull'elemento (dettagli)
      elemento.addEventListener('click', (e) => {
        if (!e.target.closest('.btn-azione')) {
          const clienteId = elemento.dataset.clienteId;
          this._emitEvent('cliente-selezionato', { 
            clienteId,
            elemento 
          });
        }
      });

      // Click sulle azioni
      const azioni = elemento.querySelectorAll('.btn-azione');
      azioni.forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const azione = btn.dataset.azione;
          const clienteId = elemento.dataset.clienteId;
          
          this._emitEvent(`cliente-${azione}`, {
            clienteId,
            elemento,
            azione
          });
        });
      });
    });
  }

  _applicaFiltri(filtri) {
    let clienti = Array.from(this.clientiCache.values());

    Object.entries(filtri).forEach(([chiave, valore]) => {
      if (valore !== undefined && valore !== '') {
        clienti = clienti.filter(cliente => {
          if (chiave === 'tavolo_id') {
            return cliente.tavolo_id == valore;
          }
          if (chiave === 'stato') {
            return cliente.stato === valore;
          }
          if (chiave === 'search') {
            return this._clienteCorrispondeRicerca(cliente, valore);
          }
          return cliente[chiave] == valore;
        });
      }
    });

    return clienti;
  }

  _clienteCorrispondeRicerca(cliente, query) {
    if (!cliente) return false;
    
    const queryLower = query.toLowerCase();
    return (
      (cliente.nome && cliente.nome.toLowerCase().includes(queryLower)) ||
      ((cliente.identificativo || cliente.lettera) && (cliente.identificativo || cliente.lettera).toLowerCase().includes(queryLower)) ||
      (cliente.tavolo_numero && cliente.tavolo_numero.toString().includes(queryLower))
    );
  }

  _ordinaClienti(clienti) {
    return [...clienti].sort((a, b) => {
      // Prima per tavolo (gestisci valori undefined)
      const tavoloA = a.tavolo_numero || 0;
      const tavoloB = b.tavolo_numero || 0;
      
      if (tavoloA !== tavoloB) {
        return tavoloA - tavoloB;
      }
      
      // Poi per lettera (gestisci valori undefined)
      const letteraA = a.identificativo || a.lettera || '';
      const letteraB = b.identificativo || b.lettera || '';
      
      return letteraA.localeCompare(letteraB);
    });
  }

  _contaClientiPerTavolo(clienti) {
    const conteggio = {};
    clienti.forEach(cliente => {
      const tavolo = cliente.tavolo_numero || 'sconosciuto';
      conteggio[tavolo] = (conteggio[tavolo] || 0) + 1;
    });
    return conteggio;
  }

  _getIconaStato(stato) {
    const icone = {
      'attivo': '<i class="fas fa-check-circle text-success"></i>',
      'in_attesa': '<i class="fas fa-clock text-warning"></i>',
      'completato': '<i class="fas fa-flag-checkered text-secondary"></i>',
      'cancellato': '<i class="fas fa-times-circle text-danger"></i>',
      'manuale': '<i class="fas fa-user-edit text-info"></i>'
    };
    return icone[stato] || '<i class="fas fa-question-circle text-muted"></i>';
  }

  _formattaTempo(timestamp) {
    if (!timestamp) return 'N/A';
    
    const data = new Date(timestamp);
    const ora = data.getHours().toString().padStart(2, '0');
    const minuti = data.getMinutes().toString().padStart(2, '0');
    return `${ora}:${minuti}`;
  }

  _mostraErrore(messaggio) {
    // Potrebbe essere implementato con un sistema di notifiche
    console.error('Errore UI:', messaggio);
    
    if (this.elementiUI.listaClienti) {
      const erroreEl = document.createElement('div');
      erroreEl.className = 'errore-caricamento';
      erroreEl.innerHTML = `
        <i class="fas fa-exclamation-triangle"></i>
        <span>${messaggio}</span>
        <button onclick="this.parentElement.remove()">×</button>
      `;
      this.elementiUI.listaClienti.prepend(erroreEl);
    }
  }

  _emitEvent(nomeEvento, dati) {
    const evento = new CustomEvent(nomeEvento, { detail: dati });
    if (this.elementiUI.sezioneMonitor) {
      this.elementiUI.sezioneMonitor.dispatchEvent(evento);
    }
    // Emetti anche globalmente
    window.dispatchEvent(evento);
  }

  /**
   * Aggiunge un listener per eventi
   * @param {string} evento - Nome evento
   * @param {Function} callback - Funzione callback
   */
  on(evento, callback) {
    // Usa il sistema eventi del DOM (già implementato in _emitEvent)
    if (this.elementiUI.sezioneMonitor) {
      this.elementiUI.sezioneMonitor.addEventListener(evento, callback);
    }
    // Aggiungi anche listener globale
    window.addEventListener(evento, callback);
  }

  /**
   * Rimuove un listener
   * @param {string} evento - Nome evento
   * @param {Function} callback - Funzione callback da rimuovere
   */
  off(evento, callback) {
    if (this.elementiUI.sezioneMonitor) {
      this.elementiUI.sezioneMonitor.removeEventListener(evento, callback);
    }
    window.removeEventListener(evento, callback);
  }
}

// Singleton instance (rinominata per evitare conflitti)
let monitorInstance = null;

/**
 * Ottiene l'istanza singleton di ClienteMonitorManager
 * @param {ClienteConfigManager} configManager 
 * @param {Object} apiService 
 * @returns {ClienteMonitorManager}
 */
function getClienteMonitorManager(configManager, apiService) {
  if (!monitorInstance) {
    monitorInstance = new ClienteMonitorManager(configManager, apiService);
  }
  return monitorInstance;
}

// Crea e esporta la funzione factory globale
window.getClienteMonitorManager = getClienteMonitorManager;
window.clienteMonitorManager = null; // Sarà inizializzata dopo