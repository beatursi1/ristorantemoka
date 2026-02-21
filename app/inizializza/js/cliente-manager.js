// cliente-manager.js - VERSIONE COMPLETA CON PATCH INTEGRATA
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
            window.addEventListener('lista-clienti-aggiornata', (e) => {
                try {
                    const payload = e.detail || {};
                    let clientiPayload = payload.clienti || payload;

                    let clientiObj = {};
                    if (Array.isArray(clientiPayload)) {
                        clientiPayload.forEach(c => {
                            const key = c.lettera || c.identificativo || c.id;
                            if (key) clientiObj[key] = c;
                        });
                    } else if (typeof clientiPayload === 'object' && clientiPayload !== null) {
                        clientiObj = clientiPayload;
                    }

                    this.clientiRegistrati = clientiObj;

                    const tavolo = window.tavoloManager && window.tavoloManager.getTavoloSelezionato ? window.tavoloManager.getTavoloSelezionato() : null;
                    Object.entries(clientiObj).forEach(([lettera, dati]) => {
                        const nome = dati && (dati.nome || (dati.metadata && dati.metadata.nome)) ? (dati.nome || (dati.metadata && dati.metadata.nome)) : (`Cliente ${lettera}`);
                        this.aggiornaBadgeCliente(lettera, nome, true);
                    });

                    if (tavolo) {
                        this.aggiornaUIListaClienti(tavolo.id);
                    }
                } catch (err) {
                    console.error('Errore handling lista-clienti-aggiornata:', err);
                }
            });
        });
    }

    onTavoloSelezionato(tavolo) {
        this.resetBadgeClienti();
        this.caricaConfigurazioneTavolo(tavolo.id);
        this.aggiornaMonitorClienti();
    }

    resetBadgeClienti() {
        const lettere = ['A', 'B', 'C', 'D', 'E', 'F'];
        lettere.forEach(lettera => {
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
            const nomeAttuale = nomeSpan.textContent;
            const scelta = prompt(
                `Cliente ${lettera} - ${nomeAttuale}\n\nCosa vuoi fare?\n` +
                `1. Rinominare\n` +
                `2. Cancellare questo cliente\n` +
                `3. Annulla`,
                '1'
            );
            
            if (scelta === '1') {
                const nuovoNome = prompt(`Nuovo nome per Cliente ${lettera}:`, nomeAttuale);
                if (nuovoNome !== null && nuovoNome.trim() !== '') {
                    await this.aggiornaCliente(lettera, nuovoNome.trim(), tavolo.id);
                }
            } else if (scelta === '2') {
                if (confirm(`Sei sicuro di voler CANCELLARE il Cliente ${lettera} (${nomeAttuale})?`)) {
                    await this.cancellaCliente(lettera, tavolo.id);
                }
            }
        } else {
            const nuovoNome = prompt(`Inserisci nome per Cliente ${lettera}:`, '');
            if (nuovoNome !== null && nuovoNome.trim() !== '') {
                await this.aggiornaCliente(lettera, nuovoNome.trim(), tavolo.id);
            }
        }
    }

    // ⭐ METODO CON PATCH INTEGRATA - Fix "cliente scompare"
    async aggiornaCliente(lettera, nome, tavoloId) {
        console.log('[ClienteManager] Salvando cliente:', lettera, nome);
        
        // 1. Aggiorna UI IMMEDIATAMENTE (ottimistico)
        this.aggiornaBadgeCliente(lettera, nome, true);
        
        // 2. Salva in configurazione locale SUBITO
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
        
        // 3. Salva nel database
        const result = await this.api.salvaClienteManuale(tavoloId, lettera, nome);
        
        console.log('[ClienteManager] Risposta API:', result);
        
        if (!result.success) {
            alert('Attenzione: Cliente salvato solo localmente. Errore: ' + (result.error || 'Sconosciuto'));
            return;
        }
        
        // 4. ⭐ IMPORTANTE: Aspetta 500ms prima di refreshare dal server
        console.log('[ClienteManager] Aspetto 500ms prima di refreshare...');
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // 5. Aggiorna monitor
        await this.aggiornaMonitorClienti();
        
        console.log('[ClienteManager] Cliente salvato e UI aggiornata');
    }

    async cancellaCliente(lettera, tavoloId) {
        const result = await this.api.cancellaClienteSingolo(tavoloId, lettera);
        
        if (result.success) {
            if (this.clientiConfigurati[tavoloId] && this.clientiConfigurati[tavoloId][lettera]) {
                this.clientiConfigurati[tavoloId][lettera] = {
                    attivo: false,
                    nome: '',
                    extra: false
                };
            }
            
            this.aggiornaBadgeCliente(lettera, '', false);
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
        let tavoloId = (typeof parametriUrl !== "undefined" && parametriUrl.tavolo) ? parametriUrl.tavolo : (window.tavoloManager && window.tavoloManager.getTavoloSelezionato ? window.tavoloManager.getTavoloSelezionato()?.id : null);
        if (!tavoloId) return;

        try {
            const result = await this.api.getClientiRegistrati(tavoloId);
            
            if (result.success) {
                this.clientiRegistrati = result.clienti || {};
                
                if (Object.keys(this.clientiRegistrati).length === 0) {
                    if (this.clientiConfigurati[tavoloId]) {
                        const configTavolo = this.clientiConfigurati[tavoloId];
                        Object.keys(configTavolo).forEach(lettera => {
                            configTavolo[lettera] = {
                                attivo: false,
                                nome: '',
                                extra: false
                            };
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

        const clientiCombinati = {...this.clientiRegistrati};
        const configTavolo = this.clientiConfigurati[tavoloId] || {};

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

            this.aggiornaBadgeCliente(lettera, dati.nome, true);
        });

        container.innerHTML = html;
    }

    apriModalClienteExtra() {
        const modal = new bootstrap.Modal(document.getElementById('modal-cliente-extra'));
        modal.show();
    }

    async apriModalSelezioneCliente() {
        const tavolo = window.tavoloManager && window.tavoloManager.getTavoloSelezionato ? window.tavoloManager.getTavoloSelezionato() : null;
        if (!tavolo) {
            alert('Prima seleziona un tavolo!');
            return;
        }

        try {
            await this.aggiornaMonitorClienti();
        } catch (err) {
            console.warn('Impossibile aggiornare clienti prima di aprire il modal:', err);
        }

        const clienti = [];
        
        Object.entries(this.clientiRegistrati || {}).forEach(([lettera, dati]) => {
            clienti.push({
                lettera: lettera,
                nome: dati.nome || `Cliente ${lettera}`,
                tipo: dati.tipo || 'qr',
                sessione_cliente_id: dati.id || null
            });
        });

        const configTavolo = this.clientiConfigurati[tavolo.id] || {};
        Object.keys(configTavolo).forEach(lettera => {
            if (configTavolo[lettera] && configTavolo[lettera].attivo) {
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
        
        const modal = new bootstrap.Modal(document.getElementById('modal-seleziona-cliente'));
        modal.show();
    }

    apriMenuPerCliente(lettera, sessioneClienteId) {
        const tavolo = window.tavoloManager.getTavoloSelezionato();
        if (!tavolo) return;
        
        const modalEl = document.getElementById('modal-seleziona-cliente');
        if (modalEl) {
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();
        }

        let sessioneToken = null;
        if (window.inizializzaApp && window.inizializzaApp.sessioneCorrente) {
            sessioneToken = window.inizializzaApp.sessioneCorrente.sessione_token || null;
        }

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
        params.set('cameriere', '1');

        window.location.href = `${urlBase}?${params.toString()}`;
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