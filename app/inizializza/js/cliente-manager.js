// cliente-manager.js
class ClienteManager {
    constructor() {
        this.clientiConfigurati = {}; // {tavoloId: {A: {...}, B: {...}}}
        this.clientiRegistrati = {};  // Clienti QR dal server
        this.api = window.apiService;
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.addEventListener('DOMContentLoaded', () => {
            // Event listener per badge clienti A-F
            ['A', 'B', 'C', 'D', 'E', 'F'].forEach(lettera => {
                const badge = document.getElementById(`badge-${lettera}`);
                if (badge) {
                    badge.addEventListener('click', () => this.onClienteBadgeClick(lettera));
                }
            });

            // Pulsante aggiungi cliente extra
            const btnExtra = document.getElementById('btn-aggiungi-cliente-extra');
            if (btnExtra) {
                btnExtra.addEventListener('click', () => this.apriModalClienteExtra());
            }

            // Ascolta evento tavolo selezionato
            document.addEventListener('tavolo-selezionato', (e) => this.onTavoloSelezionato(e.detail));

            // Sincronizza quando il monitor clienti emette nuovi dati
            // Registriamo il listener su window (ClienteMonitorManager emette sia su sezione che su window)
            window.addEventListener('lista-clienti-aggiornata', (e) => {
                try {
                    const payload = e.detail || {};
                    let clientiPayload = payload.clienti || payload; // potrebbe essere array o oggetto

                    let clientiObj = {};
                    if (Array.isArray(clientiPayload)) {
                        // Converti array in oggetto indicizzato per lettera/identificativo
                        clientiPayload.forEach(c => {
                            const key = c.lettera || c.identificativo || c.id;
                            if (key) clientiObj[key] = c;
                        });
                    } else if (typeof clientiPayload === 'object' && clientiPayload !== null) {
                        clientiObj = clientiPayload;
                    }

                    // Aggiorna stato locale
                    this.clientiRegistrati = clientiObj;

                    // Aggiorna badge UI e, se c'è tavolo selezionato, aggiorna la lista modal
                    const tavolo = window.tavoloManager && window.tavoloManager.getTavoloSelezionato ? window.tavoloManager.getTavoloSelezionato() : null;
                    // Aggiorna badge per ogni cliente ricevuto (manteniamo nome e registrazione)
                    Object.entries(clientiObj).forEach(([lettera, dati]) => {
                        const nome = dati && (dati.nome || (dati.metadata && dati.metadata.nome)) ? (dati.nome || (dati.metadata && dati.metadata.nome)) : (`Cliente ${lettera}`);
                        this.aggiornaBadgeCliente(lettera, nome, true);
                    });

                    if (tavolo) {
                        // Aggiorna la UI del modal se è stata aperta o quando verrà aperto
                        this.aggiornaUIListaClienti(tavolo.id);
                    }
                } catch (err) {
                    console.error('Errore handling lista-clienti-aggiornata:', err);
                }
            });
        });
    }

    onTavoloSelezionato(tavolo) {
        // RESETTA i badge prima di caricare nuovo tavolo
        this.resetBadgeClienti();
        
        this.caricaConfigurazioneTavolo(tavolo.id);
        this.aggiornaMonitorClienti();
    }

    resetBadgeClienti() {
        // Reset badge A-F - SOLO per clienti non configurati
        const lettere = ['A', 'B', 'C', 'D', 'E', 'F'];
        lettere.forEach(lettera => {
            // Controlla se questo cliente è configurato per il tavolo corrente
            const tavolo = window.tavoloManager.getTavoloSelezionato && window.tavoloManager.getTavoloSelezionato();
            const isClienteConfigurato = tavolo && 
                                       this.clientiConfigurati[tavolo.id] && 
                                       this.clientiConfigurati[tavolo.id][lettera] && 
                                       this.clientiConfigurati[tavolo.id][lettera].attivo;
            
            if (!isClienteConfigurato) {
                const badge = document.getElementById(`badge-${lettera}`);
                const nomeSpan = document.getElementById(`nome-${lettera}`);
                if (badge) {
                    badge.classList.remove('cliente-registrato');
                }
                if (nomeSpan) {
                    nomeSpan.textContent = 'Libero';
                    nomeSpan.className = 'text-muted';
                    nomeSpan.style.fontWeight = 'normal';
                }
            }
        });
    }

    caricaConfigurazioneTavolo(tavoloId) {
        // Inizializza configurazione se non esiste
        if (!this.clientiConfigurati[tavoloId]) {
            this.clientiConfigurati[tavoloId] = {
                'A': { attivo: false, nome: '', extra: false },
                'B': { attivo: false, nome: '', extra: false },
                'C': { attivo: false, nome: '', extra: false },
                'D': { attivo: false, nome: '', extra: false },
                'E': { attivo: false, nome: '', extra: false },
                'F': { attivo: false, nome: '', extra: false }
            };
        }

        // Aggiorna UI con i nomi salvati
        const configTavolo = this.clientiConfigurati[tavoloId];
        Object.keys(configTavolo).forEach(lettera => {
            if (configTavolo[lettera].attivo) {
                this.aggiornaBadgeCliente(lettera, configTavolo[lettera].nome, true);
            }
        });
    }

    async onClienteBadgeClick(lettera) {
        const tavolo = window.tavoloManager.getTavoloSelezionato();
        if (!tavolo) {
            alert('Seleziona prima un tavolo');
            return;
        }

        const badge = document.getElementById(`badge-${lettera}`);
        const nomeSpan = document.getElementById(`nome-${lettera}`);
        const isClienteAttivo = badge.classList.contains('cliente-registrato');
        
        if (isClienteAttivo) {
            // CLIENTE GIÀ ATTIVO: Menu opzioni
            const nomeAttuale = nomeSpan.textContent;
            const scelta = prompt(
                `Cliente ${lettera} - ${nomeAttuale}\n\nCosa vuoi fare?\n` +
                `1. Rinominare\n` +
                `2. Cancellare questo cliente\n` +
                `3. Annulla`,
                '1'
            );
            
            if (scelta === '1') {
                // Rinominare
                const nuovoNome = prompt(`Nuovo nome per Cliente ${lettera}:`, nomeAttuale);
                if (nuovoNome !== null && nuovoNome.trim() !== '') {
                    await this.aggiornaCliente(lettera, nuovoNome.trim(), tavolo.id);
                }
            } else if (scelta === '2') {
                // Cancellare
                if (confirm(`Sei sicuro di voler CANCELLARE il Cliente ${lettera} (${nomeAttuale})?`)) {
                    await this.cancellaCliente(lettera, tavolo.id);
                }
            }
            // Se scelta === '3' o null, annulla
        } else {
            // CLIENTE LIBERO: Assegna nome
            const nuovoNome = prompt(`Inserisci nome per Cliente ${lettera}:`, '');
            if (nuovoNome !== null && nuovoNome.trim() !== '') {
                await this.aggiornaCliente(lettera, nuovoNome.trim(), tavolo.id);
            }
        }
    }

    async aggiornaCliente(lettera, nome, tavoloId) {
        // Aggiorna UI
        this.aggiornaBadgeCliente(lettera, nome, true);
        
        // Salva in configurazione locale
        if (!this.clientiConfigurati[tavoloId]) {
            this.clientiConfigurati[tavoloId] = {
                'A': { attivo: false, nome: '', extra: false },
                'B': { attivo: false, nome: '', extra: false },
                'C': { attivo: false, nome: '', extra: false },
                'D': { attivo: false, nome: '', extra: false },
                'E': { attivo: false, nome: '', extra: false },
                'F': { attivo: false, nome: '', extra: false }
            };
        }
        
        this.clientiConfigurati[tavoloId][lettera] = {
            attivo: true,
            nome: nome,
            extra: false
        };
        
        // Salva nel database
        const result = await this.api.salvaClienteManuale(tavoloId, lettera, nome);
        
        if (!result.success) {
            alert('Attenzione: Cliente salvato solo localmente.');
        }
        
        // Aggiorna monitor
        await this.aggiornaMonitorClienti();
    }

    async cancellaCliente(lettera, tavoloId) {
        // API per cancellare singolo cliente
        const result = await this.api.cancellaClienteSingolo(tavoloId, lettera);
        
        if (result.success) {
            // Reset configurazione locale
            if (this.clientiConfigurati[tavoloId] && this.clientiConfigurati[tavoloId][lettera]) {
                this.clientiConfigurati[tavoloId][lettera] = {
                    attivo: false,
                    nome: '',
                    extra: false
                };
            }
            
            // Reset UI
            this.aggiornaBadgeCliente(lettera, '', false);
            
            // Aggiorna monitor
            await this.aggiornaMonitorClienti();
            
            alert(`Cliente ${lettera} cancellato con successo`);
        } else {
            alert('Errore nella cancellazione: ' + result.error);
        }
    }

    aggiornaBadgeCliente(lettera, nome, attivo = false) {
        const badge = document.getElementById(`badge-${lettera}`);
        const nomeSpan = document.getElementById(`nome-${lettera}`);
        
        if (!badge || !nomeSpan) return;

        if (attivo && nome) {
            badge.classList.add('cliente-registrato');
            nomeSpan.textContent = nome;
            nomeSpan.style.fontWeight = 'bold';
            nomeSpan.className = 'text-dark';
        } else {
            badge.classList.remove('cliente-registrato');
            nomeSpan.textContent = 'Libero';
            nomeSpan.style.fontWeight = 'normal';
            nomeSpan.className = 'text-muted';
        }
    }

    async aggiornaMonitorClienti() {
        // Usa parametriUrl se presente (pagina cliente), altrimenti fallback per backoffice
        let tavoloId = (typeof parametriUrl !== "undefined" && parametriUrl.tavolo) ? parametriUrl.tavolo : (window.tavoloManager && window.tavoloManager.getTavoloSelezionato ? window.tavoloManager.getTavoloSelezionato()?.id : null);
        if (!tavoloId) return;

        try {
            const result = await this.api.getClientiRegistrati(tavoloId);
            
            if (result.success) {
                this.clientiRegistrati = result.clienti || {};
                
                // SE non ci sono clienti nel DB, RESETTA anche la configurazione locale
                if (Object.keys(this.clientiRegistrati).length === 0) {
                    // Reset configurazione locale per questo tavolo
                    if (this.clientiConfigurati[tavoloId]) {
                        const configTavolo = this.clientiConfigurati[tavoloId];
                        Object.keys(configTavolo).forEach(lettera => {
                            configTavolo[lettera] = {
                                attivo: false,
                                nome: '',
                                extra: false
                            };
                            // Reset UI
                            this.aggiornaBadgeCliente(lettera, '', false);
                        });
                    }
                }
                
                this.aggiornaUIListaClienti(tavoloId);
            }
        } catch (error) {
            console.error('Errore monitor clienti:', error);
        }
    }

    _mergeManualiInCombinati(clientiCombinati, configTavolo) {
        Object.keys(configTavolo).forEach(lettera => {
            if (configTavolo[lettera] && configTavolo[lettera].attivo) {
                if (!clientiCombinati[lettera]) {
                    clientiCombinati[lettera] = {
                        lettera: lettera,
                        nome: configTavolo[lettera].nome || `Cliente ${lettera}`,
                        tipo: 'manuale',
                        origine: 'manuale',
                        ora: 'Manuale'
                    };
                }
            }
        });
    }

    aggiornaUIListaClienti(tavoloId) {
        const container = document.getElementById('clienti-registrati-container');
        if (!container) return;

        // Combina clienti QR (dal DB) con clienti manuali (config locale)
        const clientiCombinati = {...this.clientiRegistrati};
        const configTavolo = this.clientiConfigurati[tavoloId] || {};

        // Integra eventuali manuali che non sono nel DB
        this._mergeManualiInCombinati(clientiCombinati, configTavolo);

        if (Object.keys(clientiCombinati).length === 0) {
            container.innerHTML = '<p class="text-muted"><em>Nessun cliente registrato ancora</em></p>';
            return;
        }

        let html = '';
        Object.entries(clientiCombinati).forEach(([lettera, dati]) => {
            const tipo = dati.tipo || (dati.manuale ? 'manuale' : 'qr');
            const isManuale = (tipo === 'manuale');

            const badgeTipo = isManuale
                ? '<span class="badge bg-secondary ms-2">MANUALE</span>'
                : '<span class="badge bg-info ms-2">QR</span>';

            html += `
                <div class="cliente-monitor ${isManuale ? '' : 'registrato'}">
                    <div>
                        <span class="badge ${isManuale ? 'bg-secondary' : 'bg-primary'}">${lettera}</span>
                        <strong class="ms-2">${dati.nome || 'Cliente ' + lettera}</strong>
                        ${badgeTipo}
                    </div>
                    <div class="text-muted small">
                        ${isManuale 
                            ? 'Configurato manualmente' 
                            : 'Registrato via QR' + (dati.ora ? ' alle ' + dati.ora : '')
                        }
                    </div>
                </div>
            `;

            // Aggiorna anche la griglia di configurazione (badge A-F)
            this.aggiornaBadgeCliente(lettera, dati.nome, true);
        });

        container.innerHTML = html;
    }

    apriModalClienteExtra() {
        const modal = new bootstrap.Modal(document.getElementById('modal-cliente-extra'));
        modal.show();
    }

    // Funzione per gestire "Ordina per cliente" — forziamo un refresh prima di costruire il modal
    async apriModalSelezioneCliente() {
        const tavolo = window.tavoloManager && window.tavoloManager.getTavoloSelezionato ? window.tavoloManager.getTavoloSelezionato() : null;
        if (!tavolo) {
            alert('Prima seleziona un tavolo!');
            return;
        }

        // Forza un aggiornamento dal server prima di costruire il modal
        try {
            await this.aggiornaMonitorClienti();
        } catch (err) {
            console.warn('Impossibile aggiornare clienti prima di aprire il modal:', err);
            // procediamo comunque con i dati locali
        }

        // Raccogli TUTTI i clienti (dal DB + manuali locali)
        const clienti = [];
        
        // 1) Clienti da DB (QR + eventuali manuali già salvati)
        Object.entries(this.clientiRegistrati || {}).forEach(([lettera, dati]) => {
            clienti.push({
                lettera: lettera,
                nome: dati.nome || `Cliente ${lettera}`,
                tipo: dati.tipo || 'qr',
                sessione_cliente_id: dati.id || null   // id da sessioni_clienti
            });
        });

        // 2) Clienti manuali locali (che magari non sono ancora nel DB)
        const configTavolo = this.clientiConfigurati[tavolo.id] || {};
        Object.keys(configTavolo).forEach(lettera => {
            if (configTavolo[lettera] && configTavolo[lettera].attivo) {
                // Se non esiste già nella lista (da DB), aggiungilo come manuale
                const giaPresente = clienti.some(c => c.lettera === lettera);
                if (!giaPresente) {
                    clienti.push({
                        lettera: lettera,
                        nome: configTavolo[lettera].nome || `Cliente ${lettera}`,
                        tipo: 'manuale',
                        sessione_cliente_id: null
                    });
                }
            }
        });
        
        const container = document.getElementById('lista-clienti-manuali');
        const nessunCliente = document.getElementById('nessun-cliente-manuale');
        
        if (!clienti.length) {
            if (container) {
                container.innerHTML = '';
                container.style.display = 'none';
            }
            if (nessunCliente) {
                nessunCliente.style.display = 'block';
            }
        } else {
            if (container) {
                container.style.display = 'flex';
                if (nessunCliente) {
                    nessunCliente.style.display = 'none';
                }
                
                let html = '';
                clienti.forEach(cliente => {
                    const isManuale = (cliente.tipo === 'manuale');
                    const badgeTipo = isManuale
                        ? '<span class="badge bg-secondary ms-1">MANUALE</span>'
                        : '<span class="badge bg-info ms-1">QR</span>';
                    
                    html += `
                        <div class="col-md-4 col-6 mb-3 text-center">
                            <div class="card cliente-card p-3" 
                                 onclick="window.clienteManager.apriMenuPerCliente('${cliente.lettera}', '${cliente.sessione_cliente_id || ''}')"
                                 style="cursor: pointer; border: 2px solid ${isManuale ? '#17a2b8' : '#0d6efd'};">
                                <div class="cliente-badge ${isManuale ? 'badge-a cliente-registrato' : 'badge-a'}" 
                                     style="margin: 0 auto 10px; width: 60px; height: 60px; line-height: 60px; font-size: 1.5em;">
                                    ${cliente.lettera}
                                </div>
                                <h6 class="mb-1">${cliente.nome}</h6>
                                <small class="text-muted">
                                    Cliente ${cliente.lettera} ${badgeTipo}
                                </small>
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            }
        }
        
        // Mostra il modal
        const modal = new bootstrap.Modal(document.getElementById('modal-seleziona-cliente'));
        modal.show();
    }

    apriMenuPerCliente(lettera, sessioneClienteId) {
        const tavolo = window.tavoloManager.getTavoloSelezionato();
        if (!tavolo) return;
        
        // Chiudi il modal se esiste
        const modalEl = document.getElementById('modal-seleziona-cliente');
        if (modalEl) {
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();
        }

        // Recupera il token di SessioneTavolo se esiste
        let sessioneToken = null;
        if (window.inizializzaApp && window.inizializzaApp.sessioneCorrente) {
            sessioneToken = window.inizializzaApp.sessioneCorrente.sessione_token || null;
        }

        // Costruisci l'URL per il menu cliente
        const urlBase = window.location.origin + '/ristorantemoka/app/menu.html';

        let params = new URLSearchParams();
        params.set('tavolo', tavolo.id);
        params.set('cliente', lettera);
        if (sessioneClienteId) {
            params.set('sessione_cliente_id', sessioneClienteId);
        }
        if (sessioneToken) {
            params.set('sessione', sessioneToken);
        }

        const urlMenu = `${urlBase}?${params.toString()}`;

        // Apri in nuova tab/finestra
        window.open(urlMenu, '_blank');
    }

    getClientiConfiguratiPerTavolo(tavoloId) {
        return this.clientiConfigurati[tavoloId] || {};
    }

    getLettereOccupate(tavoloId) {
        const configTavolo = this.clientiConfigurati[tavoloId] || {};
        const lettereOccupate = [];
        
        Object.keys(configTavolo).forEach(lettera => {
            if (configTavolo[lettera] && configTavolo[lettera].attivo) {
                lettereOccupate.push(lettera);
            }
        });
        
        return lettereOccupate;
    }
}

// Esporta l'istanza globale
window.clienteManager = new ClienteManager();