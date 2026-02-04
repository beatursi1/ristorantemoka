// bevande-manager.js
class BevandeManager {
    constructor() {
        this.bottiglieAcqua = 0;
        this.bevandeIniziali = {
            acqua: { quantita: 0, prezzo_unitario: 2.5 },
            altre: []
        };
        this.quantitaBevandaCorrente = 1;
        this.bevandaCorrente = {
            id: '8',
            nome: 'Vino rosso',
            prezzo_unitario: 18.00
        };
        
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.addEventListener('DOMContentLoaded', () => {
            // Pulsante modal bevanda
            const btnBevanda = document.querySelector('[onclick="apriModalBevanda()"]');
            if (btnBevanda) {
                btnBevanda.removeAttribute('onclick');
                btnBevanda.addEventListener('click', () => this.apriModalBevanda());
            }

            // Radio buttons condivisione bevanda
            document.querySelectorAll('input[name="condivisione-bevanda"]').forEach(radio => {
                radio.addEventListener('change', (e) => this.onCondivisioneChange(e));
            });

            // Inizializza info bevanda
            this.aggiornaInfoBevanda();
        });
    }

    modificaAcqua(delta) {
        const nuovoValore = this.bottiglieAcqua + delta;
        if (nuovoValore >= 0 && nuovoValore <= 10) {
            this.bottiglieAcqua = nuovoValore;
            this.bevandeIniziali.acqua.quantita = this.bottiglieAcqua;
            
            this.aggiornaUIAcqua();
            this.aggiornaListaBevande();
            this.aggiornaRiepilogo();
        }
    }

    aggiornaUIAcqua() {
        document.getElementById('contatore-acqua').textContent = this.bottiglieAcqua;
        
        const prezzoTotale = this.bottiglieAcqua * 2.5;
        const clientiCount = Object.keys(window.clienteManager?.clientiRegistrati || {}).length;
        const prezzoPerPersona = clientiCount > 0 ? prezzoTotale / clientiCount : 0;
        
        document.getElementById('prezzo-acqua').textContent = prezzoTotale.toFixed(2);
        document.getElementById('prezzo-acqua-per-persona').textContent = prezzoPerPersona.toFixed(2);
    }

    apriModalBevanda() {
        const clientiRegistrati = window.clienteManager?.clientiRegistrati || {};
        const clientiAttivi = Object.keys(clientiRegistrati);
        
        if (clientiAttivi.length === 0) {
            alert('Nessun cliente registrato ancora! Aspetta che i clienti si registrino.');
            return;
        }
        
        // Popola checkbox partecipanti
        this.popolaPartecipantiBevanda(clientiRegistrati);
        
        // Reset valori
        this.quantitaBevandaCorrente = 1;
        document.getElementById('quantita-bevanda').textContent = '1';
        document.getElementById('cond-personale').checked = true;
        document.getElementById('selettore-partecipanti').style.display = 'none';
        
        // Aggiorna prezzo
        this.aggiornaInfoBevanda();
        
        // Mostra modal
        const modal = new bootstrap.Modal(document.getElementById('modal-bevanda'));
        modal.show();
    }

    popolaPartecipantiBevanda(clientiRegistrati) {
        const container = document.getElementById('checkbox-partecipanti');
        if (!container) return;
        container.innerHTML = '';
        
        Object.entries(clientiRegistrati).forEach(([id, cliente]) => {
            // Estrai la lettera dall'id (esempio: man_1_A_251712 -> "A")
            let lettera = id.match(/_([A-Z])_/i) ? id.match(/_([A-Z])_/i)[1] : id;

            // Usa il nome reale se presente, altrimenti fallback
            const nomeReale = cliente.nome && cliente.nome.trim() !== '' ? cliente.nome : `Cliente ${lettera}`;
            const nomeDisplay = ` (${nomeReale})`;
            
            container.innerHTML += `
                <div class="form-check">
                    <input class="form-check-input partecipante-checkbox" type="checkbox"
                        value="${id}" id="bev-part-${id}" checked>
                    <label class="form-check-label" for="bev-part-${id}">
                        Cliente ${lettera}${nomeDisplay}
                    </label>
                </div>
            `;
        });
    }

    aggiornaInfoBevanda() {
        const select = document.getElementById('select-bevanda');
        if (!select) return;
        
        const option = select.options[select.selectedIndex];
        
        this.bevandaCorrente = {
            id: option.value,
            nome: option.getAttribute('data-nome'),
            prezzo_unitario: parseFloat(option.getAttribute('data-prezzo'))
        };
        
        // Aggiorna prezzo totale
        const prezzoTotale = this.bevandaCorrente.prezzo_unitario * this.quantitaBevandaCorrente;
        const prezzoElement = document.getElementById('prezzo-totale-bevanda');
        if (prezzoElement) {
            prezzoElement.textContent = prezzoTotale.toFixed(2);
        }
    }

    modificaQuantitaBevanda(delta) {
        const nuovoValore = this.quantitaBevandaCorrente + delta;
        if (nuovoValore >= 1 && nuovoValore <= 20) {
            this.quantitaBevandaCorrente = nuovoValore;
            document.getElementById('quantita-bevanda').textContent = this.quantitaBevandaCorrente;
            
            // Aggiorna prezzo totale
            const prezzoTotale = this.bevandaCorrente.prezzo_unitario * this.quantitaBevandaCorrente;
            document.getElementById('prezzo-totale-bevanda').textContent = prezzoTotale.toFixed(2);
        }
    }

    onCondivisioneChange(event) {
        const selettore = document.getElementById('selettore-partecipanti');
        if (selettore) {
            selettore.style.display = event.target.value === 'parziale' ? 'block' : 'none';
        }
    }

    salvaBevanda() {
        const clientiRegistrati = window.clienteManager?.clientiRegistrati || {};
        
        // Determina partecipanti
        let partecipanti = [];
        const tipoCondivisione = document.querySelector('input[name="condivisione-bevanda"]:checked').value;
        
        if (tipoCondivisione === 'personale') {
            // Prende il primo cliente registrato
            const primoCliente = Object.keys(clientiRegistrati)[0];
            if (primoCliente) partecipanti = [primoCliente];
        } else if (tipoCondivisione === 'tavolo') {
            // Tutti i clienti registrati
            Object.keys(clientiRegistrati).forEach(lettera => {
                partecipanti.push(lettera);
            });
        } else if (tipoCondivisione === 'parziale') {
            // Clienti selezionati
            document.querySelectorAll('.partecipante-checkbox:checked').forEach(cb => {
                partecipanti.push(cb.value);
            });
        }
        
        if (partecipanti.length === 0) {
            alert('Seleziona almeno un partecipante!');
            return;
        }
        
        // Aggiungi bevanda alla lista
        const nuovaBevanda = {
            id: this.bevandaCorrente.id,
            nome: this.bevandaCorrente.nome,
            prezzo_unitario: this.bevandaCorrente.prezzo_unitario,
            quantita: this.quantitaBevandaCorrente,
            condivisione: tipoCondivisione,
            partecipanti: partecipanti,
            prezzo_totale: this.bevandaCorrente.prezzo_unitario * this.quantitaBevandaCorrente
        };
        
        this.bevandeIniziali.altre.push(nuovaBevanda);
        
        // Chiudi modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('modal-bevanda'));
        if (modal) modal.hide();
        
        // Aggiorna UI
        this.aggiornaListaBevande();
        this.aggiornaRiepilogo();
    }

    rimuoviBevanda(index) {
        if (confirm('Rimuovere questa bevanda?')) {
            this.bevandeIniziali.altre.splice(index, 1);
            this.aggiornaListaBevande();
            this.aggiornaRiepilogo();
        }
    }

    aggiornaListaBevande() {
        const container = document.getElementById('lista-bevande');
        if (!container) return;
        
        if (this.bevandeIniziali.altre.length === 0 && this.bottiglieAcqua === 0) {
            container.innerHTML = '<p class="text-muted"><em>Nessuna bevanda aggiunta</em></p>';
            return;
        }
        
        let html = '';
        
        // Acqua
        if (this.bottiglieAcqua > 0) {
            const prezzoTotale = this.bottiglieAcqua * 2.5;
            html += `
                <div class="bevanda-item">
                    <div>
                        <strong>${this.bottiglieAcqua} × Acqua naturale 1L</strong><br>
                        <small class="text-muted">Condivisione: Tavolo | €${prezzoTotale.toFixed(2)}</small>
                    </div>
                    <button class="btn btn-outline-danger btn-sm" onclick="window.bevandeManager.modificaAcqua(-window.bevandeManager.bottiglieAcqua)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }
        
        // Altre bevande
        this.bevandeIniziali.altre.forEach((bevanda, index) => {
            const prezzoPerPartecipante = bevanda.prezzo_totale / bevanda.partecipanti.length;
            html += `
                <div class="bevanda-item">
                    <div>
                        <strong>${bevanda.quantita} × ${bevanda.nome}</strong><br>
                        <small class="text-muted">
                            Condivisione: ${bevanda.condivisione} | 
                            Partecipanti: ${bevanda.partecipanti.join(', ')} | 
                            €${bevanda.prezzo_totale.toFixed(2)} totali
                            (€${prezzoPerPartecipante.toFixed(2)} a testa)
                        </small>
                    </div>
                    <button class="btn btn-outline-danger btn-sm" onclick="window.bevandeManager.rimuoviBevanda(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }

    aggiornaRiepilogo() {
        const riepilogoBevande = document.getElementById('riepilogo-bevande');
        if (!riepilogoBevande) return;
        
        let bevandeHtml = '';
        let totaleBevande = 0;
        
        // Acqua
        if (this.bottiglieAcqua > 0) {
            const prezzoAcqua = this.bottiglieAcqua * 2.5;
            totaleBevande += prezzoAcqua;
            bevandeHtml += `
                <p><i class="fas fa-tint me-2 text-primary"></i>
                <strong>${this.bottiglieAcqua} bottiglie d'acqua</strong>
                <small class="text-muted">(€${prezzoAcqua.toFixed(2)})</small></p>
            `;
        }
        
        // Altre bevande
        this.bevandeIniziali.altre.forEach(bevanda => {
            totaleBevande += bevanda.prezzo_totale;
            const prezzoPerPartecipante = bevanda.prezzo_totale / bevanda.partecipanti.length;
            bevandeHtml += `
                <p><i class="fas fa-wine-bottle me-2 text-success"></i>
                <strong>${bevanda.quantita} × ${bevanda.nome}</strong>
                <small class="text-muted">
                    (€${bevanda.prezzo_totale.toFixed(2)} - €${prezzoPerPartecipante.toFixed(2)} a testa)
                </small></p>
            `;
        });
        
        if (bevandeHtml === '') {
            riepilogoBevande.innerHTML = '<p><em>Nessuna bevanda</em></p>';
        } else {
            riepilogoBevande.innerHTML = bevandeHtml + 
                `<p class="mt-2"><strong>Totale bevande: €${totaleBevande.toFixed(2)}</strong></p>`;
        }
    }

    getDatiBevande() {
        return {
            bottiglieAcqua: this.bottiglieAcqua,
            bevandeIniziali: this.bevandeIniziali
        };
    }

    reset() {
        this.bottiglieAcqua = 0;
        this.bevandeIniziali = {
            acqua: { quantita: 0, prezzo_unitario: 2.5 },
            altre: []
        };
        
        this.aggiornaUIAcqua();
        this.aggiornaListaBevande();
        this.aggiornaRiepilogo();
    }
}

// Esporta l'istanza globale
window.bevandeManager = new BevandeManager();