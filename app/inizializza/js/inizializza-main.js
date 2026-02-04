class InizializzaApp {
    constructor() {
    this.modules = {};
    this.sessioneCorrente = null; // { sessione_id, sessione_token }

    // Inizializza subito i moduli e i pulsanti
    this.inizializzaModuli();
    this.setupPulsantiGlobali();
    this.setupIntervalli();
}
        setupEventListeners() {
        // Non facciamo più nulla qui: tutta l'inizializzazione
        // avviene nel costruttore, dopo che il DOM è pronto.
    }
    inizializzaModuli() {
        // Inizializza moduli se esistono
        if (window.tavoloManager) {
            window.tavoloManager.inizializza();
            
            // Ascolta evento selezione tavolo
            document.addEventListener('tavolo-selezionato', async (e) => {
                // Quando viene selezionato un tavolo, creiamo/recuperiamo la SessioneTavolo
                try {
                    await this.gestisciSelezioneTavolo();
                } catch (err) {
                    console.error('Errore nella gestione selezione tavolo:', err);
                    alert('Errore nella creazione della sessione per questo tavolo. Riprova o contatta l\'amministratore.');
                }
            });
        }
        
        if (window.clienteManager) {
            // ClienteManager si auto-inizializza nel costruttore
        }
		
        // Inizializza clienteBadgeManager
        if (window.clienteManager && window.clienteBadgeManager === null) {
            window.clienteBadgeManager = new ClienteBadgeManager(window.clienteManager);
        }
    
        // Inizializza clienteConfigManager
        if (window.clienteConfigManager && typeof window.clienteConfigManager.inizializza === 'function') {
            window.clienteConfigManager.inizializza();
        }
    
        // Inizializza clienteMonitorManager
        if (window.clienteMonitorManager === null && window.clienteConfigManager && window.apiService) {
            // Usa la funzione getClienteMonitorManager per ottenere l'istanza singleton
            if (typeof getClienteMonitorManager === 'function') {
                window.clienteMonitorManager = getClienteMonitorManager(window.clienteConfigManager, window.apiService);
                
                // Ora inizializza il monitor manager con gli elementi UI corretti
                if (window.clienteMonitorManager && typeof window.clienteMonitorManager.inizializza === 'function') {
                    // Ritarda l'inizializzazione di 500ms per assicurarsi che il DOM sia pronto
                    setTimeout(() => {
                        window.clienteMonitorManager.inizializza({
                            listaClienti: document.getElementById('clienti-registrati-container'),
                            conteggioClienti: document.getElementById('clienti-conteggio'),
                            sezioneMonitor: document.getElementById('fase2')
                        });
                    }, 500);
                }
            }
        }

        // Collega i due manager per sincronizzazione
        if (window.clienteMonitorManager && window.clienteBadgeManager) {
            // Quando la lista clienti si aggiorna, aggiorna anche i badge
            window.clienteMonitorManager.on('lista-clienti-aggiornata', (evento) => {
                window.clienteBadgeManager.aggiornaBadgeDaListaClienti(evento.detail.clienti);
            });
        }

        if (window.bevandeManager) {
            // BevandeManager si auto-inizializza nel costruttore
        }
    }

    setupPulsantiGlobali() {
        // Pulsanti per acqua
        const btnAcquaMeno = document.getElementById('btn-acqua-meno');
        const btnAcquaPiu = document.getElementById('btn-acqua-piu');
        if (btnAcquaMeno && btnAcquaPiu) {
            btnAcquaMeno.addEventListener('click', () => {
                if (window.bevandeManager) window.bevandeManager.modificaAcqua(-1);
            });
            btnAcquaPiu.addEventListener('click', () => {
                if (window.bevandeManager) window.bevandeManager.modificaAcqua(1);
            });
        }

        // Pulsante per aprire modal bevanda
        const btnApriBevanda = document.getElementById('btn-apri-bevanda');
        if (btnApriBevanda) {
            btnApriBevanda.addEventListener('click', () => this.apriModalBevanda());
        }

        // Pulsanti modal bevanda
        const btnBevandaMeno = document.getElementById('btn-bevanda-meno');
        const btnBevandaPiu = document.getElementById('btn-bevanda-piu');
        const btnSalvaBevanda = document.getElementById('btn-salva-bevanda');
        
        if (btnBevandaMeno) {
            btnBevandaMeno.addEventListener('click', () => this.modificaQuantitaBevanda(-1));
        }
        if (btnBevandaPiu) {
            btnBevandaPiu.addEventListener('click', () => this.modificaQuantitaBevanda(1));
        }
        if (btnSalvaBevanda) {
            btnSalvaBevanda.addEventListener('click', () => this.salvaBevanda());
        }

        // Pulsante per salvare cliente extra
        const btnSalvaClienteExtra = document.getElementById('btn-salva-cliente-extra');
        if (btnSalvaClienteExtra) {
            btnSalvaClienteExtra.addEventListener('click', () => this.aggiungiClienteExtra());
        }

        // Event listener per select bevanda
        const selectBevanda = document.getElementById('select-bevanda');
        if (selectBevanda) {
            selectBevanda.addEventListener('change', () => this.aggiornaInfoBevanda());
        }

        // Event listener per radio buttons condivisione bevanda
        document.querySelectorAll('input[name="condivisione-bevanda"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                const selettore = document.getElementById('selettore-partecipanti');
                if (selettore) {
                    selettore.style.display = e.target.value === 'parziale' ? 'block' : 'none';
                }
            });
        });

        // Pulsanti navigazione fasi
        const btnFase3 = document.getElementById('btn-vai-fase3');
        if (btnFase3) {
            btnFase3.addEventListener('click', () => this.vaiAllaFase3());
        }

        const btnFase4 = document.getElementById('btn-vai-fase4');
        if (btnFase4) {
            btnFase4.addEventListener('click', () => this.vaiAllaFase4());
        }

        // Pulsante Conferma inizializzazione
        const btnConferma = document.getElementById('btn-conferma');
        if (btnConferma) {
            btnConferma.addEventListener('click', () => this.confermaInizializzazione());
        }
    }

    setupPulsantiFase2() {
        // Questa funzione viene chiamata quando la fase 2 viene mostrata
        console.log('Setup pulsanti fase 2...');
        
        // Pulsante Aggiorna lista clienti
        const btnAggiorna = document.getElementById('btn-aggiorna-clienti');
        if (btnAggiorna) {
            // Rimuovi eventuali listener precedenti
            btnAggiorna.replaceWith(btnAggiorna.cloneNode(true));
            const newBtnAggiorna = document.getElementById('btn-aggiorna-clienti');
            newBtnAggiorna.addEventListener('click', () => this.aggiornaMonitorClienti());
        }

        // Pulsante Resetta clienti
        const btnResetta = document.getElementById('btn-resetta-clienti');
        if (btnResetta) {
            btnResetta.replaceWith(btnResetta.cloneNode(true));
            const newBtnResetta = document.getElementById('btn-resetta-clienti');
            newBtnResetta.addEventListener('click', () => this.resettaClientiTavolo());
        }

        // Pulsante Libera tavolo
        const btnLibera = document.getElementById('btn-libera-tavolo');
        if (btnLibera) {
            btnLibera.replaceWith(btnLibera.cloneNode(true));
            const newBtnLibera = document.getElementById('btn-libera-tavolo');
            newBtnLibera.addEventListener('click', () => this.liberaTavolo());
        }

        // Pulsante Ordina per cliente
        const btnOrdina = document.getElementById('btn-ordina-cliente');
        if (btnOrdina) {
            btnOrdina.replaceWith(btnOrdina.cloneNode(true));
            const newBtnOrdina = document.getElementById('btn-ordina-cliente');
            newBtnOrdina.addEventListener('click', () => this.ordinaPerCliente());
        }
    }

    setupIntervalli() {
        console.log('Intervallo di aggiornamento avviato');
        
        // Aggiorna monitor clienti ogni 5 secondi se c'è un tavolo selezionato
        setInterval(() => {
            console.log('Aggiornamento automatico in corso...');
            if (window.tavoloManager && window.tavoloManager.getTavoloSelezionato()) {
                console.log('Tavolo selezionato, aggiorno clienti');
                this.aggiornaMonitorClienti();
            } else {
                console.log('Nessun tavolo selezionato, salto aggiornamento');
            }
        }, 5000);
    }

    // ============= GESTIONE SELEZIONE TAVOLO + QR =============

    async gestisciSelezioneTavolo() {
        const tavolo = window.tavoloManager?.getTavoloSelezionato();
        if (!tavolo) return;

        // Crea o recupera la SessioneTavolo per questo tavolo
        const sessione = await this.creaORecuperaSessioneTavolo(tavolo);
        if (!sessione || !sessione.sessione_token) {
            throw new Error('Impossibile ottenere il token di sessione per questo tavolo');
        }

        this.sessioneCorrente = sessione;

        // Genera QR code basato sul token di sessione
        await this.generaQRCode();

        // Aggiorna monitor clienti
        await this.aggiornaMonitorClienti();
        
        // Mostra fase 2
        document.getElementById('fase2').style.display = 'block';
        
        // Setup pulsanti fase 2 (ORA i pulsanti esistono nel DOM)
        this.setupPulsantiFase2();
    }

    async creaORecuperaSessioneTavolo(tavolo) {
        // Usa apiService se già esiste un metodo dedicato, altrimenti chiamiamo direttamente un endpoint generico
        if (window.apiService && typeof window.apiService.creaSessioneTavolo === 'function') {
            return await window.apiService.creaSessioneTavolo(tavolo.id);
        }

        // Fallback: chiamata fetch diretta ad un endpoint PHP (da creare)
        try {
            const response = await fetch('../api/tavoli/sessione-tavolo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: `azione=crea_o_recupera&tavolo_id=${encodeURIComponent(tavolo.id)}`
            });

            if (!response.ok) {
                throw new Error(`HTTP error ${response.status}`);
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Errore nella creazione/recupero sessione');
            }

            return {
                sessione_id: data.sessione_id,
                sessione_token: data.sessione_token
            };

        } catch (err) {
            console.error('Errore creaORecuperaSessioneTavolo:', err);
            alert('Errore nella creazione della SessioneTavolo: ' + err.message);
            return null;
        }
    }

    // ============= GENERAZIONE QR CODE =============
    async generaQRCode() {
        const tavolo = window.tavoloManager?.getTavoloSelezionato();
        if (!tavolo) return;

        const qrContainer = document.getElementById('qr-code-container');
        if (!qrContainer) return;

        qrContainer.innerHTML = '';

        // Se non abbiamo ancora la sessioneCorrente, proviamo a crearla/recuperarla
        if (!this.sessioneCorrente || !this.sessioneCorrente.sessione_token) {
            const sessione = await this.creaORecuperaSessioneTavolo(tavolo);
            if (!sessione) return;
            this.sessioneCorrente = sessione;
        }
        
        const token = this.sessioneCorrente.sessione_token;
        const urlBase = window.location.origin + '/ristorantemoka/app/menu.html';
        // QR code contiene SOLO il token di sessione, non il numero tavolo
        const qrUrl = `${urlBase}?sessione=${encodeURIComponent(token)}`;
        
        // Genera QR code
        new QRCode(qrContainer, {
            text: qrUrl,
            width: 200,
            height: 200
        });
        
        // Aggiungi URL
        const urlDiv = document.createElement('div');
        urlDiv.className = 'mt-2';
        urlDiv.innerHTML = `<small class="text-muted">${qrUrl}</small>`;
        qrContainer.appendChild(urlDiv);
        
        // Aggiorna numero tavolo nell'header
        const tavoloNumeroSpan = document.getElementById('tavolo-numero-selezionato');
        if (tavoloNumeroSpan) {
            tavoloNumeroSpan.textContent = tavolo.numero;
        }
    }

    // ============= FUNZIONI GLOBALI =============

    async aggiornaMonitorClienti() {
        // Usa il nuovo clienteMonitorManager se disponibile, altrimenti fallback al vecchio clienteManager
        if (window.clienteMonitorManager && typeof window.clienteMonitorManager.aggiornaListaClienti === 'function') {
            const tavolo = window.tavoloManager?.getTavoloSelezionato();
            if (tavolo) {
                await window.clienteMonitorManager.aggiornaClientiTavolo(tavolo.id);
            } else {
                await window.clienteMonitorManager.aggiornaListaClienti();
            }
        } else if (window.clienteManager) {
            // Fallback al vecchio sistema
            await window.clienteManager.aggiornaMonitorClienti();
        }
    }

    async resettaClientiTavolo() {
        const tavolo = window.tavoloManager?.getTavoloSelezionato();
        if (!tavolo) {
            alert('Seleziona prima un tavolo');
            return;
        }

        const conferma = confirm(`⚠️ ATTENZIONE!\n\nStai per CANCELLARE TUTTI i clienti registrati per il Tavolo ${tavolo.numero}.\n\nQuesta azione:\n• Cancella tutte le registrazioni clienti\n• I clienti dovranno scansionare di nuovo il QR code\n• Non cancella gli ordini già inviati\n\nConfermi?`);

        if (!conferma) return;

        const result = await window.apiService?.resettaClientiTavolo(tavolo.id);

        if (result.success) {
            alert(`✅ Clienti del Tavolo ${tavolo.numero} resettati con successo!\n\nClienti cancellati: ${result.clienti_cancellati}`);
            this.aggiornaMonitorClienti();
            
            // Reset anche i badge clienti
            if (window.clienteBadgeManager) {
                window.clienteBadgeManager.resetBadgeClienti();
            }
        } else {
            alert('❌ Errore: ' + result.error);
        }
    }

    async liberaTavolo() {
        const motivo = prompt(`Perché vuoi liberare il Tavolo?\n\nEsempi:\n• Errore selezione\n• Clienti non arrivati\n• Cambio tavolo\n• Altro`);
        
        if (motivo === null) return;

        const cameriereId = document.body.getAttribute('data-cameriere-id') || 
                           document.querySelector('[data-cameriere-id]')?.getAttribute('data-cameriere-id') || 
                           '1';

        const result = await window.tavoloManager?.liberaTavolo(motivo, cameriereId);

        if (result && result.success) {
            alert(`✅ Tavolo liberato con successo!\n\nClienti cancellati: ${result.clienti_cancellati}\nMotivo: ${motivo}`);
            
            // Nascondi fase 2
            document.getElementById('fase2').style.display = 'none';
            
            // Reset moduli
            if (window.clienteManager) {
                window.clienteManager.clientiRegistrati = {};
                window.clienteManager.aggiornaUIListaClienti();
            }
            
            if (window.bevandeManager) {
                window.bevandeManager.reset();
            }

            // Reset anche la sessioneCorrente lato JS
            this.sessioneCorrente = null;
        } else if (result) {
            alert('❌ Errore: ' + result.error);
        }
    }

    ordinaPerCliente() {
        if (window.clienteManager) {
            window.clienteManager.apriModalSelezioneCliente();
        }
    }

    // Navigazione fasi
    vaiAllaFase3() {
        document.getElementById('fase2').style.display = 'none';
        document.getElementById('fase3').style.display = 'block';
    }

    vaiAllaFase4() {
        document.getElementById('fase3').style.display = 'none';
        document.getElementById('fase4').style.display = 'block';
        this.aggiornaRiepilogoFinale();
    }

    aggiornaRiepilogoFinale() {
        // Aggiorna riepilogo clienti
        const riepilogoClienti = document.getElementById('riepilogo-clienti');
        if (riepilogoClienti && window.clienteManager) {
            const clienti = window.clienteManager.clientiRegistrati;
            if (Object.keys(clienti).length === 0) {
                riepilogoClienti.innerHTML = '<p><em>Nessun cliente registrato</em></p>';
            } else {
                let html = '<ul class="list-unstyled">';
                Object.entries(clienti).forEach(([lettera, dati]) => {
                    const nome = dati.nome ? ` - ${dati.nome}` : '';
                    html += `<li><span class="badge bg-primary me-2">${lettera}</span>Cliente ${lettera}${nome}</li>`;
                });
                html += '</ul>';
                riepilogoClienti.innerHTML = html;
            }
        }

        // Aggiorna riepilogo bevande
        if (window.bevandeManager) {
            window.bevandeManager.aggiornaRiepilogo();
        }
    }

    async confermaInizializzazione() {
        const tavolo = window.tavoloManager?.getTavoloSelezionato();
        if (!tavolo) {
            alert('Seleziona un tavolo prima di procedere');
            return;
        }

        const clientiCount = Object.keys(window.clienteManager?.clientiRegistrati || {}).length;
        if (clientiCount === 0) {
            alert('Nessun cliente registrato ancora! Aspetta che i clienti si registrino.');
            return;
        }

        const btn = document.getElementById('btn-conferma');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processando...';

        try {
            // Simula elaborazione
            setTimeout(() => {
                alert(`✅ Tavolo ${tavolo.numero} inizializzato con successo!\n\nClienti registrati: ${clientiCount}\n\nI clienti possono ora ordinare.`);
                
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }, 1500);
        } catch (error) {
            alert('Errore: ' + error.message);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    // Funzioni per gestione clienti extra (modal)
    aggiungiClienteExtra() {
        const lettera = document.getElementById('select-lettera-extra').value;
        const nome = document.getElementById('nome-cliente-extra').value.trim();

        // Log per debug (puoi rimuoverlo in produzione)
        console.log('Aggiungi cliente extra:', lettera, nome);

        // Spostiamo il focus all'elemento che ha aperto il modal (se presente) PRIMA di nascondere il modal,
        // così evitiamo l'errore ARIA: "descendant retained focus".
        try {
            const modalEl = document.getElementById('modal-cliente-extra');
            const active = document.activeElement;
            if (modalEl && active && modalEl.contains(active)) {
                // Trova l'elemento che ha aperto il modal: di default #btn-aggiungi-cliente-extra
                const opener = document.getElementById('btn-aggiungi-cliente-extra') || document.body;
                // Spostiamo il focus (senza scrollare)
                if (typeof opener.focus === 'function') {
                    try {
                        opener.focus({ preventScroll: true });
                    } catch (err) {
                        // alcuni browser potrebbero non supportare l'opzione, fallback semplice
                        opener.focus();
                    }
                }
            }
        } catch (err) {
            // Non blocchiamo il flusso in caso di errori imprevisti
            console.warn('Errore nel focus handling prima di chiudere modal:', err);
        }

        // Chiudi il modal in modo standard tramite Bootstrap
        const modal = bootstrap.Modal.getInstance(document.getElementById('modal-cliente-extra'));
        if (modal) modal.hide();

        // Reset form
        const nomeInput = document.getElementById('nome-cliente-extra');
        if (nomeInput) nomeInput.value = '';
    }

    // Funzioni per gestione bevande (modal)
    apriModalBevanda() {
        if (window.bevandeManager) {
            window.bevandeManager.apriModalBevanda();
        }
    }

    salvaBevanda() {
        if (window.bevandeManager) {
            window.bevandeManager.salvaBevanda();
        }
    }

    modificaQuantitaBevanda(delta) {
        if (window.bevandeManager) {
            window.bevandeManager.modificaQuantitaBevanda(delta);
        }
    }

    aggiornaInfoBevanda() {
        if (window.bevandeManager) {
            window.bevandeManager.aggiornaInfoBevanda();
        }
    }
}

// Inizializza l'app quando il DOM è pronto
document.addEventListener('DOMContentLoaded', () => {
    window.inizializzaApp = new InizializzaApp();
});
// Accessibility: rimuove il focus dagli elementi dentro il modal PRIMA che venga impostato aria-hidden
// Copre: click su close/data-bs-dismiss, click sul backdrop, pressione ESC e hide.bs.modal (capture phase).
(function () {
    function tryFocus(element) {
        if (!element || typeof element.focus !== 'function') return;
        try {
            element.focus({ preventScroll: true });
        } catch (err) {
            try { element.focus(); } catch (e) { /* ignore */ }
        }
    }

    function focusOpenerFor(modalEl) {
        if (!modalEl) return;
        var opener = modalEl.__modalOpener ||
            document.querySelector('[data-bs-toggle="modal"][data-bs-target="#' + modalEl.id + '"]') ||
            document.body;
        tryFocus(opener);
    }

    function blurActiveIfIn(modalEl) {
        try {
            var active = document.activeElement;
            if (active && modalEl && modalEl.contains(active)) {
                try { active.blur(); } catch (e) { /* ignore */ }
            }
        } catch (e) {
            /* ignore */
        }
    }

    // Salva l'elemento attivo prima dell'apertura del modal (fallback)
    document.addEventListener('show.bs.modal', function (ev) {
        try {
            var modalEl = ev.target;
            if (modalEl) modalEl.__modalOpener = document.activeElement;
        } catch (err) { /* ignore */ }
    }, true);

    // Intercetta click che possono causare la chiusura (capture phase)
    document.addEventListener('click', function (ev) {
        try {
            var target = ev.target;
            if (!target) return;

            // Pulsanti che chiudono il modal: data-bs-dismiss oppure .btn-close
            var btn = (typeof target.closest === 'function') ? target.closest('[data-bs-dismiss="modal"], .btn-close') : null;
            if (btn) {
                var modalEl = (typeof btn.closest === 'function') ? btn.closest('.modal') : null;
                if (modalEl) {
                    blurActiveIfIn(modalEl);
                    focusOpenerFor(modalEl);
                }
                return;
            }

            // Click sul backdrop: target è l'elemento .modal (non .modal-dialog)
            if (target.classList && target.classList.contains('modal')) {
                blurActiveIfIn(target);
                focusOpenerFor(target);
            }
        } catch (err) { /* ignore */ }
    }, true); // capture: true -> esegue prima dei listener di Bootstrap

    // Intercetta ESC prima che Bootstrap lo gestisca (capture)
    document.addEventListener('keydown', function (ev) {
        try {
            if (ev.key !== 'Escape' && ev.key !== 'Esc') return;
            var active = document.activeElement;
            var modalEl = active && (typeof active.closest === 'function') ? active.closest('.modal') : null;
            if (!modalEl) modalEl = document.querySelector('.modal.show');
            if (modalEl) {
                blurActiveIfIn(modalEl);
                focusOpenerFor(modalEl);
            }
        } catch (err) { /* ignore */ }
    }, true);

    // Ulteriore fallback: quando bootstrap emette hide.bs.modal, assicuriamoci che il focus non sia dentro
    document.addEventListener('hide.bs.modal', function (ev) {
        try {
            var modalEl = ev.target;
            if (!modalEl) return;
            blurActiveIfIn(modalEl);
            focusOpenerFor(modalEl);
            if (modalEl.__modalOpener) {
                try { modalEl.__modalOpener = null; } catch (e) { /* ignore */ }
            }
        } catch (err) { /* ignore */ }
    }, true);
})();