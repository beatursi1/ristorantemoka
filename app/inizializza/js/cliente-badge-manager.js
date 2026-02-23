// cliente-badge-manager.js - Gestione UI badge clienti
class ClienteBadgeManager {
    constructor(clienteManager) {
        this.clienteManager = clienteManager;
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.addEventListener('DOMContentLoaded', () => {
            // Delegazione eventi invece di listener individuali
            document.getElementById('clienti-container')?.addEventListener('click', (e) => {
                const badge = e.target.closest('.cliente-badge');
                if (badge) {
                    const lettera = badge.dataset.lettera;
                    if (lettera) this.onClienteBadgeClick(lettera);
                }
            });
        });
    }

    async onClienteBadgeClick(lettera) {
        const tavolo = window.tavoloManager?.getTavoloSelezionato();
        if (!tavolo) {
            alert('Seleziona prima un tavolo');
            return;
        }

        const badge = document.getElementById(`badge-${lettera}`);
        const nomeSpan = document.getElementById(`nome-${lettera}`);
        const isClienteAttivo = badge?.classList.contains('cliente-registrato');
        
        if (isClienteAttivo) {
            await this.menuClienteAttivo(lettera, nomeSpan?.textContent || '', tavolo.id);
        } else {
            await this.assegnaNuovoCliente(lettera, tavolo.id);
        }
    }

    async menuClienteAttivo(lettera, nomeAttuale, tavoloId) {
        const scelta = prompt(
            `Cliente ${lettera} - ${nomeAttuale}\n\nCosa vuoi fare?\n` +
            `1. Rinominare\n` +
            `2. Cancellare questo cliente\n` +
            `3. Annulla`,
            '1'
        );
        
        if (scelta === '1') {
            const nuovoNome = prompt(`Nuovo nome per Cliente ${lettera}:`, nomeAttuale);
            if (nuovoNome?.trim()) {
                await this.clienteManager.aggiornaCliente(lettera, nuovoNome.trim(), tavoloId);
            }
        } else if (scelta === '2') {
            if (confirm(`Sei sicuro di voler CANCELLARE il Cliente ${lettera} (${nomeAttuale})?`)) {
                await this.clienteManager.cancellaCliente(lettera, tavoloId);
            }
        }
    }

    async assegnaNuovoCliente(lettera, tavoloId) {
        const nuovoNome = prompt(`Inserisci nome per Cliente ${lettera}:`, '');
        if (nuovoNome?.trim()) {
            await this.clienteManager.aggiornaCliente(lettera, nuovoNome.trim(), tavoloId);
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

    resetBadgeClienti() {
        // Reset badge A-F - SOLO per clienti non configurati
        const lettere = ['A', 'B', 'C', 'D', 'E', 'F'];
        lettere.forEach(lettera => {
            // Controlla se questo cliente è configurato per il tavolo corrente
            const tavolo = window.tavoloManager?.getTavoloSelezionato();
            const configTavolo = this.clienteManager?.clientiConfigurati[tavolo?.id] || {};
            const isClienteConfigurato = configTavolo[lettera]?.attivo;
            
            if (!isClienteConfigurato) {
                this.aggiornaBadgeCliente(lettera, '', false);
            }
        });
    }

    /**
     * Aggiorna badge quando la lista clienti cambia
     * @param {Array} clienti - Lista clienti aggiornata
     */
    aggiornaBadgeDaListaClienti(clienti) {
    if (!clienti || !Array.isArray(clienti)) return;
    
    console.log('Sincronizzazione badge con', clienti.length, 'clienti');
    
    clienti.forEach(cliente => {
    const lettera = cliente.identificativo || cliente.lettera;
    const nome = cliente.nome;

    // Regola: se il DB dice esplicitamente "qr", resta QR.
    // Solo se tipo è manuale (o solo origine manuale) lo consideriamo manuale.
    let tipoEffettivo = 'qr';
    if (cliente.tipo === 'manuale') {
        tipoEffettivo = 'manuale';
    } else if (!cliente.tipo && cliente.origine === 'manuale') {
        tipoEffettivo = 'manuale';
    }

    if (lettera && nome) {
        console.log(`Sincronizzo badge ${lettera}: ${nome} (${tipoEffettivo})`);
        this.aggiornaBadgeCliente(lettera, nome, true);
    }
});
}
}

// Esporta l'istanza globale
window.clienteBadgeManager = null; // Verrà inizializzata dopo clienteManager