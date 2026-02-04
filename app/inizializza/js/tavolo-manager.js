// tavolo-manager.js
class TavoloManager {
    constructor() {
        this.tavoloSelezionato = null;
        this.api = window.apiService;
    }

    inizializza() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Delegated event handling: un solo listener sul documento, così anche le card create dinamicamente rispondono
        document.addEventListener('click', (e) => {
            // Click su una card tavolo (ma non su elementi di azione interna)
            const card = e.target.closest('.tavolo-selectable');
            if (card) {
                // Se il click è su un bottone di azione interno (es. modifica), gestiamo separatamente
                const btnModifica = e.target.closest('.btn-modifica');
                if (btnModifica) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.onModificaClick(card, btnModifica, e);
                    return;
                }

                // Altrimenti trattiamo come selezione del tavolo
                this.onTavoloClick({ currentTarget: card, isDelegated: true });
                return;
            }

            // Se serve intercettare click su specifici bottoni esterni, possiamo aggiungerli qui
        });

        // Optional: ascolta dinamicamente l'aggiunta di nuove card per eventuali inizializzazioni locali
        // (non necessario per i listener grazie alla delegation, ma utile se vuoi animare nuove card)
        // const observer = new MutationObserver(mutations => { ... });
        // observer.observe(document.getElementById('tavoli-container') || document.body, { childList: true, subtree: true });
    }

    async onTavoloClick(event) {
        // Supporta sia chiamata con event reale che chiamata delegata (passiamo oggetto con currentTarget)
        const card = event.currentTarget;

        // Deseleziona precedente
        if (this.tavoloSelezionato) {
            const prevCard = document.querySelector(`[data-id="${this.tavoloSelezionato.id}"]`);
            if (prevCard) prevCard.classList.remove('selected');
        }

        // Seleziona nuovo
        card.classList.add('selected');

        // Assicuriamoci che numero sia numerico
        const numeroRaw = card.dataset.numero;
        const numero = (typeof numeroRaw !== 'undefined' && numeroRaw !== null && numeroRaw !== '') ? Number(numeroRaw) : null;

        this.tavoloSelezionato = {
            id: card.dataset.id,
            numero: numero,
            stato: card.dataset.stato
        };

        // Aggiorna UI
        this.aggiornaUIStatoTavolo(card, 'occupato');

        // Chiama API per aggiornare stato nel DB (non bloccare la UI se l'API fallisce)
        try {
            const result = await this.api.aggiornaStatoTavolo(this.tavoloSelezionato.id, 'occupato');

            if (!result.success) {
                alert('Attenzione: Errore nell\'aggiornamento del tavolo. I clienti potrebbero non poter registrarsi.');
            }
        } catch (err) {
            console.error('Errore chiamata API aggiornaStatoTavolo:', err);
            alert('Errore di comunicazione con il server (aggiornamento stato tavolo).');
        }

        // Notifica altri moduli del cambio tavolo
        this.dispatchEvent('tavolo-selezionato', this.tavoloSelezionato);
    }

    aggiornaUIStatoTavolo(card, nuovoStato) {
        const badge = card.querySelector('.badge');

        if (nuovoStato === 'occupato') {
            if (badge) {
                badge.className = 'badge bg-danger';
                badge.textContent = 'Occupato';
            }
            card.classList.remove('tavolo-libero');
            card.classList.add('tavolo-occupato');
        } else if (nuovoStato === 'libero') {
            if (badge) {
                badge.className = 'badge bg-success';
                badge.textContent = 'Libero';
            }
            card.classList.remove('tavolo-occupato');
            card.classList.add('tavolo-libero');
        }

        card.dataset.stato = nuovoStato;
        if (this.tavoloSelezionato) {
            this.tavoloSelezionato.stato = nuovoStato;
        }
    }

    async liberaTavolo(motivo, cameriereId) {
        if (!this.tavoloSelezionato) {
            alert('Seleziona prima un tavolo');
            return false;
        }

        const result = await this.api.liberaTavolo(this.tavoloSelezionato.id, motivo, cameriereId);

        if (result.success) {
            // Aggiorna UI
            const card = document.querySelector(`[data-id="${this.tavoloSelezionato.id}"]`);
            if (card) {
                this.aggiornaUIStatoTavolo(card, 'libero');
                card.classList.remove('selected');
            }

            this.tavoloSelezionato = null;

            // Notifica altri moduli
            this.dispatchEvent('tavolo-liberato', result);
        }

        return result;
    }

    getTavoloSelezionato() {
        return this.tavoloSelezionato;
    }

    // Handler per click sul pulsante "modifica" dentro una card
    onModificaClick(card, btn, originalEvent) {
        const tavoloId = card.dataset.id;
        const numeroRaw = card.dataset.numero;
        const numero = (typeof numeroRaw !== 'undefined' && numeroRaw !== null && numeroRaw !== '') ? Number(numeroRaw) : null;

        // Emetti evento per apertura modifica (altri moduli possono ascoltare)
        this.dispatchEvent('modifica-tavolo', { id: tavoloId, numero: numero, sourceButton: btn });

        // Se vuoi apertura inline di modal puoi farlo qui:
        // const modal = new bootstrap.Modal(document.getElementById('modal-modifica-tavolo'));
        // popola il modal con i dati e modal.show();
    }

    // Event system per comunicazione tra moduli
    dispatchEvent(eventName, detail) {
        const event = new CustomEvent(eventName, { detail });
        document.dispatchEvent(event);
    }
}

// Esporta l'istanza globale
window.tavoloManager = new TavoloManager();