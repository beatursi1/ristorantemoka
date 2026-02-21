/**
 * cliente-config-manager.js Gestore stato configurazioni clienti
 * Sistema centralizzato per la gestione delle configurazioni dei clienti
 */

class ClienteConfigManager {
  constructor() {
    this.configurazioni = new Map();
    this.cronologiaModifiche = new Map();
    this.cacheValidita = new Map();
    this.eventListeners = {
      'config-aggiornata': [],
      'config-eliminata': [],
      'cache-invalidata': []
    };
    this.inizializzato = false;
  }

  /**
   * Inizializza il gestore configurazioni
   * @param {Array} configurazioniIniziali - Configurazioni iniziali opzionali
   * @returns {Promise<boolean>}
   */
  async inizializza(configurazioniIniziali = []) {
    if (this.inizializzato) {
      console.warn('ClienteConfigManager già inizializzato');
      return true;
    }

    try {
      if (configurazioniIniziali.length > 0) {
        await this.caricaConfigurazioniBatch(configurazioniIniziali);
      }
      
      this.inizializzato = true;
      console.log('ClienteConfigManager inizializzato con successo');
      return true;
    } catch (error) {
      console.error('Errore durante l\'inizializzazione:', error);
      throw error;
    }
  }

  /**
   * Aggiunge una nuova configurazione cliente
   * @param {string} clienteId - ID del cliente
   * @param {Object} configurazione - Oggetto configurazione
   * @param {Object} metadata - Metadati opzionali
   * @returns {Object} Configurazione salvata
   */
  aggiungiConfigurazione(clienteId, configurazione, metadata = {}) {
    this._validaIdCliente(clienteId);
    this._validaConfigurazione(configurazione);

    const timestamp = new Date().toISOString();
    const configCompleta = {
      id: this._generaIdConfigurazione(),
      clienteId,
      ...configurazione,
      metadata: {
        ...metadata,
        creazione: timestamp,
        ultimaModifica: timestamp,
        versione: 1
      },
      attiva: true
    };

    this.configurazioni.set(clienteId, configCompleta);
    this._salvaCronologiaModifica(clienteId, 'CREAZIONE', configCompleta);
    this._invalidaCache(clienteId);

    this._emitEvent('config-aggiornata', {
      clienteId,
      azione: 'aggiunta',
      configurazione: configCompleta,
      timestamp
    });

    return configCompleta;
  }

  /**
   * Recupera configurazione cliente
   * @param {string} clienteId - ID del cliente
   * @param {boolean} usaCache - Utilizza cache se disponibile
   * @returns {Object|null} Configurazione o null
   */
  getConfigurazione(clienteId, usaCache = true) {
    this._validaIdCliente(clienteId);

    if (usaCache) {
      const cached = this._getDaCache(clienteId);
      if (cached) return cached;
    }

    const configurazione = this.configurazioni.get(clienteId);
    
    if (configurazione && usaCache) {
      this._salvaInCache(clienteId, configurazione);
    }

    return configurazione || null;
  }

  /**
   * Aggiorna configurazione esistente
   * @param {string} clienteId - ID del cliente
   * @param {Object} updates - Aggiornamenti da applicare
   * @param {Object} metadata - Nuovi metadati opzionali
   * @returns {Object} Configurazione aggiornata
   */
  aggiornaConfigurazione(clienteId, updates, metadata = {}) {
    this._validaIdCliente(clienteId);
    
    const configurazioneEsistente = this.getConfigurazione(clienteId, false);
    if (!configurazioneEsistente) {
      throw new Error(`Configurazione non trovata per cliente: ${clienteId}`);
    }

    const timestamp = new Date().toISOString();
    const nuovaVersione = configurazioneEsistente.metadata.versione + 1;

    const configurazioneAggiornata = {
      ...configurazioneEsistente,
      ...updates,
      metadata: {
        ...configurazioneEsistente.metadata,
        ...metadata,
        ultimaModifica: timestamp,
        versione: nuovaVersione,
        aggiornamenti: [
          ...(configurazioneEsistente.metadata.aggiornamenti || []),
          { timestamp, updates, metadata }
        ]
      }
    };

    this.configurazioni.set(clienteId, configurazioneAggiornata);
    this._salvaCronologiaModifica(clienteId, 'AGGIORNAMENTO', configurazioneAggiornata);
    this._invalidaCache(clienteId);

    this._emitEvent('config-aggiornata', {
      clienteId,
      azione: 'aggiornamento',
      configurazione: configurazioneAggiornata,
      versione: nuovaVersione,
      timestamp
    });

    return configurazioneAggiornata;
  }

  /**
   * Elimina configurazione cliente
   * @param {string} clienteId - ID del cliente
   * @param {string} motivo - Motivo dell'eliminazione
   * @returns {boolean} Successo dell'operazione
   */
  eliminaConfigurazione(clienteId, motivo = '') {
    this._validaIdCliente(clienteId);

    const configurazione = this.getConfigurazione(clienteId, false);
    if (!configurazione) {
      throw new Error(`Configurazione non trovata per cliente: ${clienteId}`);
    }

    this._salvaCronologiaModifica(clienteId, 'ELIMINAZIONE', {
      ...configurazione,
      motivoEliminazione: motivo,
      eliminazioneTimestamp: new Date().toISOString()
    });

    this.configurazioni.delete(clienteId);
    this._invalidaCache(clienteId);
    this._rimuoviCronologiaCliente(clienteId);

    this._emitEvent('config-eliminata', {
      clienteId,
      motivo,
      timestamp: new Date().toISOString()
    });

    return true;
  }

  /**
   * Disattiva configurazione cliente
   * @param {string} clienteId - ID del cliente
   * @returns {Object} Configurazione disattivata
   */
  disattivaConfigurazione(clienteId) {
    return this.aggiornaConfigurazione(clienteId, { attiva: false }, {
      disattivazione: new Date().toISOString()
    });
  }

  /**
   * Riattiva configurazione cliente
   * @param {string} clienteId - ID del cliente
   * @returns {Object} Configurazione riattivata
   */
  riattivaConfigurazione(clienteId) {
    const config = this.getConfigurazione(clienteId, false);
    if (!config) {
      throw new Error(`Configurazione non trovata per cliente: ${clienteId}`);
    }

    return this.aggiornaConfigurazione(clienteId, { attiva: true }, {
      riattivazione: new Date().toISOString()
    });
  }

  /**
   * Clona configurazione da un cliente a un altro
   * @param {string} clienteOrigine - ID cliente origine
   * @param {string} clienteDestinazione - ID cliente destinazione
   * @param {Object} modifiche - Modifiche da applicare alla clonazione
   * @returns {Object} Configurazione clonata
   */
  clonaConfigurazione(clienteOrigine, clienteDestinazione, modifiche = {}) {
    const configOrigine = this.getConfigurazione(clienteOrigine, false);
    if (!configOrigine) {
      throw new Error(`Configurazione origine non trovata: ${clienteOrigine}`);
    }

    const configurazioneClonata = {
      ...configOrigine,
      ...modifiche,
      id: this._generaIdConfigurazione(),
      clienteId: clienteDestinazione,
      metadata: {
        ...configOrigine.metadata,
        origineClonazione: clienteOrigine,
        clonazioneTimestamp: new Date().toISOString(),
        creazione: new Date().toISOString(),
        versione: 1
      }
    };

    this.configurazioni.set(clienteDestinazione, configurazioneClonata);
    this._salvaCronologiaModifica(clienteDestinazione, 'CLONAZIONE', configurazioneClonata);

    return configurazioneClonata;
  }

  /**
   * Cerca configurazioni per criteri
   * @param {Object} criteri - Criteri di ricerca
   * @returns {Array} Configurazioni trovate
   */
  cercaConfigurazioni(criteri = {}) {
    const risultati = [];
    
    for (const [clienteId, config] of this.configurazioni.entries()) {
      if (this._configurazioneCorrispondeCriteri(config, criteri)) {
        risultati.push(config);
      }
    }

    return risultati;
  }

  /**
   * Ottiene cronologia modifiche di un cliente
   * @param {string} clienteId - ID del cliente
   * @param {number} limite - Limite risultati
   * @returns {Array} Cronologia modifiche
   */
  getCronologia(clienteId, limite = 10) {
    this._validaIdCliente(clienteId);
    
    const cronologia = this.cronologiaModifiche.get(clienteId) || [];
    return cronologia.slice(-limite);
  }

  /**
   * Ripristina configurazione a una versione precedente
   * @param {string} clienteId - ID del cliente
   * @param {string} versioneId - ID versione da ripristinare
   * @returns {Object} Configurazione ripristinata
   */
  ripristinaVersione(clienteId, versioneId) {
    const cronologia = this.cronologiaModifiche.get(clienteId);
    if (!cronologia) {
      throw new Error(`Nessuna cronologia trovata per cliente: ${clienteId}`);
    }

    const versione = cronologia.find(v => v.id === versioneId);
    if (!versione) {
      throw new Error(`Versione ${versioneId} non trovata`);
    }

    const configurazioneRipristinata = {
      ...versione.configurazione,
      metadata: {
        ...versione.configurazione.metadata,
        ultimaModifica: new Date().toISOString(),
        versione: versione.configurazione.metadata.versione + 1,
        ripristinoDa: versioneId
      }
    };

    this.configurazioni.set(clienteId, configurazioneRipristinata);
    this._salvaCronologiaModifica(clienteId, 'RIPRISTINO', configurazioneRipristinata);

    return configurazioneRipristinata;
  }

  /**
   * Carica configurazioni in batch
   * @param {Array} configurazioni - Array di configurazioni
   * @returns {Promise<number>} Numero di configurazioni caricate
   */
  async caricaConfigurazioniBatch(configurazioni) {
    let conteggio = 0;

    for (const config of configurazioni) {
      try {
        this.aggiungiConfigurazione(config.clienteId, config, config.metadata);
        conteggio++;
      } catch (error) {
        console.error(`Errore nel caricamento configurazione ${config.clienteId}:`, error);
      }
    }

    return conteggio;
  }

  /**
   * Pulisce la cache
   * @param {string} clienteId - ID specifico del cliente (opzionale)
   */
  pulisciCache(clienteId = null) {
    if (clienteId) {
      this._invalidaCache(clienteId);
    } else {
      this.cacheValidita.clear();
    }

    this._emitEvent('cache-invalidata', { clienteId, timestamp: new Date().toISOString() });
  }

  /**
   * Aggiunge un listener per eventi
   * @param {string} evento - Nome evento
   * @param {Function} callback - Funzione callback
   */
  on(evento, callback) {
    if (this.eventListeners[evento]) {
      this.eventListeners[evento].push(callback);
    }
  }

  /**
   * Rimuove un listener
   * @param {string} evento - Nome evento
   * @param {Function} callback - Funzione callback da rimuovere
   */
  off(evento, callback) {
    if (this.eventListeners[evento]) {
      this.eventListeners[evento] = this.eventListeners[evento].filter(cb => cb !== callback);
    }
  }

  /**
   * Statistiche del gestore
   * @returns {Object} Statistiche
   */
  getStatistiche() {
    const totale = this.configurazioni.size;
    const attive = Array.from(this.configurazioni.values())
      .filter(c => c.attiva).length;
    
    const conCache = Array.from(this.cacheValidita.keys()).length;
    const conCronologia = Array.from(this.cronologiaModifiche.keys()).length;

    return {
      totaleConfigurazioni: totale,
      configurazioniAttive: attive,
      configurazioniDisattive: totale - attive,
      cacheAttive: conCache,
      clientiConCronologia: conCronologia,
      timestamp: new Date().toISOString()
    };
  }

  /**
   * Esporta tutte le configurazioni
   * @returns {Object} Stato completo
   */
  esportaStato() {
    return {
      configurazioni: Array.from(this.configurazioni.entries()).reduce((acc, [key, val]) => {
        acc[key] = val;
        return acc;
      }, {}),
      cronologia: Array.from(this.cronologiaModifiche.entries()).reduce((acc, [key, val]) => {
        acc[key] = val;
        return acc;
      }, {}),
      statistiche: this.getStatistiche(),
      timestamp: new Date().toISOString()
    };
  }


  // Metodi privati

  _validaIdCliente(clienteId) {
    if (!clienteId || typeof clienteId !== 'string') {
      throw new Error('ID cliente non valido');
    }
  }

  _validaConfigurazione(configurazione) {
    if (!configurazione || typeof configurazione !== 'object') {
      throw new Error('Configurazione non valida');
    }
  }

  _generaIdConfigurazione() {
    return `cfg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  _salvaCronologiaModifica(clienteId, azione, configurazione) {
    if (!this.cronologiaModifiche.has(clienteId)) {
      this.cronologiaModifiche.set(clienteId, []);
    }

    const entry = {
      id: `hist_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
      timestamp: new Date().toISOString(),
      azione,
      configurazione: JSON.parse(JSON.stringify(configurazione)),
      metadata: {
        versione: configurazione.metadata?.versione || 1
      }
    };

    this.cronologiaModifiche.get(clienteId).push(entry);
  }

  _salvaInCache(clienteId, configurazione) {
    this.cacheValidita.set(clienteId, {
      data: JSON.parse(JSON.stringify(configurazione)),
      timestamp: new Date().toISOString(),
      scadenza: Date.now() + (30 * 60 * 1000) // 30 minuti
    });
  }

  _getDaCache(clienteId) {
    const cached = this.cacheValidita.get(clienteId);
    
    if (cached && cached.scadenza > Date.now()) {
      return cached.data;
    }
    
    if (cached) {
      this.cacheValidita.delete(clienteId);
    }
    
    return null;
  }

  _invalidaCache(clienteId) {
    this.cacheValidita.delete(clienteId);
  }

  _rimuoviCronologiaCliente(clienteId) {
    this.cronologiaModifiche.delete(clienteId);
  }

  _configurazioneCorrispondeCriteri(configurazione, criteri) {
    for (const [key, value] of Object.entries(criteri)) {
      if (key === 'attiva') {
        if (configurazione.attiva !== value) return false;
      } else if (key.startsWith('metadata.')) {
        const metaKey = key.split('.')[1];
        if (configurazione.metadata[metaKey] !== value) return false;
      } else if (configurazione[key] !== value) {
        return false;
      }
    }
    return true;
  }

  _emitEvent(evento, dati) {
    if (this.eventListeners[evento]) {
      this.eventListeners[evento].forEach(callback => {
        try {
          callback(dati);
        } catch (error) {
          console.error(`Errore in listener evento ${evento}:`, error);
        }
      });
    }
  }
}

// Singleton instance
let instance = null;

/**
 * Ottiene l'istanza singleton di ClienteConfigManager
 * @returns {ClienteConfigManager}
 */
function getClienteConfigManager() {
  if (!instance) {
    instance = new ClienteConfigManager();
  }
  return instance;
}

// Crea e esporta l'istanza globale
window.clienteConfigManager = getClienteConfigManager();