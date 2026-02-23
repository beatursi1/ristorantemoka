// bevande-manager.js
// @version 2.0.0 - lista bevande dinamica dal DB (categoria_id = 6)
class BevandeManager {
    constructor() {
        this.bottiglieAcqua = 0;
        this.bevandeIniziali = {
            acqua: { quantita: 0, prezzo_unitario: 2.5 },
            altre: []
        };
        this.quantitaBevandaCorrente = 1;
        this.bevandaCorrente = null;
        this.listaBevande = [];   // caricata dal DB

        this.setupEventListeners();
        this.caricaListaBevande();
    }

    // ── CARICA DAL DB ────────────────────────────────────────
    async caricaListaBevande() {
        try {
            const r = await fetch('/ristorantemoka/api/bevande/get-lista-bevande.php');
            const d = await r.json();
            if (!d.success || !d.bevande.length) {
                console.warn('Nessuna bevanda trovata nel DB');
                return;
            }
            this.listaBevande = d.bevande;
            this.popolaSelectBevande();
        } catch(e) {
            console.error('Errore caricamento lista bevande:', e);
        }
    }

    popolaSelectBevande() {
        const select = document.getElementById('select-bevanda');
        if (!select) return;

        select.innerHTML = '';
        this.listaBevande.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.setAttribute('data-prezzo', b.prezzo);
            opt.setAttribute('data-nome', b.nome);
            opt.textContent = `${b.nome} — €${parseFloat(b.prezzo).toFixed(2)}`;
            select.appendChild(opt);
        });

        // Imposta la prima bevanda come corrente e aggiorna il prezzo
        this.aggiornaInfoBevanda();

        // Aggiungi listener per cambio selezione
        select.addEventListener('change', () => this.aggiornaInfoBevanda());
    }

    // ── EVENT LISTENERS ──────────────────────────────────────
    setupEventListeners() {
        document.addEventListener('DOMContentLoaded', () => {
            const btnBevanda = document.getElementById('btn-apri-bevanda');
            if (btnBevanda) {
                btnBevanda.addEventListener('click', () => this.apriModalBevanda());
            }

            document.querySelectorAll('input[name="condivisione-bevanda"]').forEach(radio => {
                radio.addEventListener('change', (e) => this.onCondivisioneChange(e));
            });

            const btnSalva = document.getElementById('btn-salva-bevanda');
            if (btnSalva) {
                btnSalva.addEventListener('click', () => this.salvaBevanda());
            }

            const btnMeno = document.getElementById('btn-bevanda-meno');
            const btnPiu  = document.getElementById('btn-bevanda-piu');
            if (btnMeno) btnMeno.addEventListener('click', () => this.modificaQuantitaBevanda(-1));
            if (btnPiu)  btnPiu.addEventListener('click',  () => this.modificaQuantitaBevanda(1));
        });
    }

    // ── ACQUA ────────────────────────────────────────────────
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
        const el = document.getElementById('contatore-acqua');
        if (el) el.textContent = this.bottiglieAcqua;

        const prezzoTotale = this.bottiglieAcqua * 2.5;
        const clientiCount = Object.keys(window.clienteManager?.clientiRegistrati || {}).length;
        const prezzoPerPersona = clientiCount > 0 ? prezzoTotale / clientiCount : 0;

        const elP = document.getElementById('prezzo-acqua');
        const elPP = document.getElementById('prezzo-acqua-per-persona');
        if (elP)  elP.textContent  = prezzoTotale.toFixed(2);
        if (elPP) elPP.textContent = prezzoPerPersona.toFixed(2);
    }

    // ── MODAL BEVANDA ────────────────────────────────────────
    apriModalBevanda() {
        const clientiRegistrati = window.clienteManager?.clientiRegistrati || {};
        const clientiAttivi = Object.keys(clientiRegistrati);

        if (clientiAttivi.length === 0) {
            alert('Nessun cliente registrato ancora! Aspetta che i clienti si registrino.');
            return;
        }

        this.popolaPartecipantiBevanda(clientiRegistrati);

        this.quantitaBevandaCorrente = 1;
        const elQta = document.getElementById('quantita-bevanda');
        if (elQta) elQta.textContent = '1';

        const condPersonale = document.getElementById('cond-personale');
        if (condPersonale) condPersonale.checked = true;

        const selettore = document.getElementById('selettore-partecipanti');
        if (selettore) selettore.style.display = 'none';

        this.aggiornaInfoBevanda();

        const modal = new bootstrap.Modal(document.getElementById('modal-bevanda'));
        modal.show();
    }

    popolaPartecipantiBevanda(clientiRegistrati) {
        const container = document.getElementById('checkbox-partecipanti');
        if (!container) return;
        container.innerHTML = '';

        Object.entries(clientiRegistrati).forEach(([id, cliente]) => {
            let lettera = id.match(/_([A-Z])_/i) ? id.match(/_([A-Z])_/i)[1] : id;
            const nomeReale = cliente.nome && cliente.nome.trim() !== '' ? cliente.nome : `Cliente ${lettera}`;

            container.innerHTML += `
                <div class="form-check">
                    <input class="form-check-input partecipante-checkbox" type="checkbox"
                           value="${id}" id="bev-part-${id}" checked>
                    <label class="form-check-label" for="bev-part-${id}">
                        Cliente ${lettera} (${nomeReale})
                    </label>
                </div>`;
        });
    }

    aggiornaInfoBevanda() {
        const select = document.getElementById('select-bevanda');
        if (!select || !select.options.length) return;

        const option = select.options[select.selectedIndex];
        if (!option) return;

        this.bevandaCorrente = {
            id:             option.value,
            nome:           option.getAttribute('data-nome'),
            prezzo_unitario: parseFloat(option.getAttribute('data-prezzo') || 0)
        };

        const prezzoTotale = this.bevandaCorrente.prezzo_unitario * this.quantitaBevandaCorrente;
        const elPrezzo = document.getElementById('prezzo-totale-bevanda');
        if (elPrezzo) elPrezzo.textContent = prezzoTotale.toFixed(2);
    }

    modificaQuantitaBevanda(delta) {
        const nuovoValore = this.quantitaBevandaCorrente + delta;
        if (nuovoValore >= 1 && nuovoValore <= 20) {
            this.quantitaBevandaCorrente = nuovoValore;
            const elQta = document.getElementById('quantita-bevanda');
            if (elQta) elQta.textContent = this.quantitaBevandaCorrente;

            if (this.bevandaCorrente) {
                const prezzoTotale = this.bevandaCorrente.prezzo_unitario * this.quantitaBevandaCorrente;
                const elPrezzo = document.getElementById('prezzo-totale-bevanda');
                if (elPrezzo) elPrezzo.textContent = prezzoTotale.toFixed(2);
            }
        }
    }

    onCondivisioneChange(event) {
        const selettore = document.getElementById('selettore-partecipanti');
        if (selettore) {
            selettore.style.display = event.target.value === 'parziale' ? 'block' : 'none';
        }
    }

    // ── SALVA BEVANDA ────────────────────────────────────────
    salvaBevanda() {
        if (!this.bevandaCorrente) {
            alert('Seleziona una bevanda dal menu');
            return;
        }

        const clientiRegistrati = window.clienteManager?.clientiRegistrati || {};
        let partecipanti = [];
        const tipoCondivisione = document.querySelector('input[name="condivisione-bevanda"]:checked')?.value || 'personale';

        if (tipoCondivisione === 'personale') {
            const primoCliente = Object.keys(clientiRegistrati)[0];
            if (primoCliente) partecipanti = [primoCliente];
        } else if (tipoCondivisione === 'tavolo') {
            partecipanti = Object.keys(clientiRegistrati);
        } else if (tipoCondivisione === 'parziale') {
            document.querySelectorAll('.partecipante-checkbox:checked').forEach(cb => {
                partecipanti.push(cb.value);
            });
        }

        if (partecipanti.length === 0) {
            alert('Seleziona almeno un partecipante!');
            return;
        }

        const nuovaBevanda = {
            id:              this.bevandaCorrente.id,
            nome:            this.bevandaCorrente.nome,
            prezzo_unitario: this.bevandaCorrente.prezzo_unitario,
            quantita:        this.quantitaBevandaCorrente,
            condivisione:    tipoCondivisione,
            partecipanti:    partecipanti,
            prezzo_totale:   this.bevandaCorrente.prezzo_unitario * this.quantitaBevandaCorrente
        };

        this.bevandeIniziali.altre.push(nuovaBevanda);

        const modal = bootstrap.Modal.getInstance(document.getElementById('modal-bevanda'));
        if (modal) modal.hide();

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

    // ── UI LISTA ─────────────────────────────────────────────
    aggiornaListaBevande() {
        const container = document.getElementById('lista-bevande');
        if (!container) return;

        if (this.bevandeIniziali.altre.length === 0 && this.bottiglieAcqua === 0) {
            container.innerHTML = '<p class="text-muted"><em>Nessuna bevanda aggiunta</em></p>';
            return;
        }

        let html = '';

        if (this.bottiglieAcqua > 0) {
            const prezzoTotale = this.bottiglieAcqua * 2.5;
            html += `
                <div class="bevanda-item">
                    <div>
                        <strong>${this.bottiglieAcqua} × Acqua naturale 1L</strong><br>
                        <small class="text-muted">Condivisione: Tavolo | €${prezzoTotale.toFixed(2)}</small>
                    </div>
                    <button class="btn btn-outline-danger btn-sm"
                            onclick="window.bevandeManager.modificaAcqua(-window.bevandeManager.bottiglieAcqua)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>`;
        }

        this.bevandeIniziali.altre.forEach((bevanda, index) => {
            const prezzoPerPartecipante = bevanda.prezzo_totale / (bevanda.partecipanti.length || 1);
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
                    <button class="btn btn-outline-danger btn-sm"
                            onclick="window.bevandeManager.rimuoviBevanda(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>`;
        });

        container.innerHTML = html;
    }

    aggiornaRiepilogo() {
        const riepilogoBevande = document.getElementById('riepilogo-bevande');
        if (!riepilogoBevande) return;

        let html = '';
        let totaleBevande = 0;

        if (this.bottiglieAcqua > 0) {
            const prezzoAcqua = this.bottiglieAcqua * 2.5;
            totaleBevande += prezzoAcqua;
            html += `<p><i class="fas fa-tint me-2 text-primary"></i>
                <strong>${this.bottiglieAcqua} bottiglie d'acqua</strong>
                <small class="text-muted">(€${prezzoAcqua.toFixed(2)})</small></p>`;
        }

        this.bevandeIniziali.altre.forEach(bevanda => {
            totaleBevande += bevanda.prezzo_totale;
            const prezzoPerPartecipante = bevanda.prezzo_totale / (bevanda.partecipanti.length || 1);
            html += `<p><i class="fas fa-wine-bottle me-2 text-success"></i>
                <strong>${bevanda.quantita} × ${bevanda.nome}</strong>
                <small class="text-muted">
                    (€${bevanda.prezzo_totale.toFixed(2)} — €${prezzoPerPartecipante.toFixed(2)} a testa)
                </small></p>`;
        });

        riepilogoBevande.innerHTML = html
            ? html + `<p class="mt-2"><strong>Totale bevande: €${totaleBevande.toFixed(2)}</strong></p>`
            : '<p><em>Nessuna bevanda</em></p>';
    }

    // ── UTILITY ──────────────────────────────────────────────
    getDatiBevande() {
        return {
            bottiglieAcqua:  this.bottiglieAcqua,
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

window.bevandeManager = new BevandeManager();