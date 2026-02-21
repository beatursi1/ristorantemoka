/* api-service.js (rivisto)
   Mantiene la classe ApiService esistente e migliora:
   - aggiunge cache/credentials coerenti alle fetch
   - gestione robusta del parsing JSON / fallback
   - getClientiRegistrati supporta POST x-www-form-urlencoded per compatibilità legacy
   - espone sia window.apiService che window.ApiService per compatibilità
*/
class ApiService {
    constructor(baseUrl = '../api/') {
        this.baseUrl = baseUrl;
    }

    // Helper: POST JSON con parsing robusto
    async _postJson(path, body = {}, opts = {}) {
        try {
            const headers = Object.assign({ 'Content-Type': 'application/json' }, opts.headers || {});
            const resp = await fetch(this.baseUrl + path, {
                method: 'POST',
                headers,
                body: JSON.stringify(body),
                cache: opts.cache || 'no-store',
                credentials: opts.credentials || 'same-origin',
                signal: opts.signal
            });
            const text = await resp.text();
            try {
                return text ? JSON.parse(text) : { success: resp.ok };
            } catch (e) {
                // risposta non JSON
                return { success: resp.ok, raw: text, status: resp.status };
            }
        } catch (error) {
            console.error(`ApiService._postJson error for ${path}:`, error);
            return { success: false, error: error && error.message ? error.message : String(error) };
        }
    }

    // Helper: GET e parsing robusto
    async _getJson(path, opts = {}) {
        try {
            const resp = await fetch(this.baseUrl + path, {
                method: 'GET',
                cache: opts.cache || 'no-store',
                credentials: opts.credentials || 'same-origin',
                signal: opts.signal
            });
            const text = await resp.text();
            try {
                return text ? JSON.parse(text) : { success: resp.ok };
            } catch (e) {
                return { success: resp.ok, raw: text, status: resp.status };
            }
        } catch (error) {
            console.error(`ApiService._getJson error for ${path}:`, error);
            return { success: false, error: error && error.message ? error.message : String(error) };
        }
    }

    // Aggiunta: ottieni menu
    async getMenu() {
        try {
            const resp = await fetch(this.baseUrl + 'menu/menu.php', {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin'
            });
            const text = await resp.text();
            try {
                return text ? JSON.parse(text) : { success: resp.ok };
            } catch (e) {
                return { success: resp.ok, raw: text, status: resp.status };
            }
        } catch (error) {
            console.error('ApiService.getMenu error:', error);
            return { success: false, error: error && error.message ? error.message : String(error) };
        }
    }

    // Aggiunta: informazioni sessione (usata in inviaOrdine)
    async sessioneInfo(sessioneToken) {
        if (!sessioneToken) return { success: false, error: 'missing_token' };
        return await this._postJson('tavoli/sessione-info.php', { sessione_token: sessioneToken });
    }

    // Aggiunta: registra cliente (usata in registraClienteAutomaticamente)
    async registraCliente(tavoloId, deviceInfo = '') {
        if (!tavoloId) return { success: false, error: 'missing_tavolo' };
        return await this._postJson('clienti/registra-cliente.php', {
            tavolo_id: Number(tavoloId),
            nome: '',
            device_id: deviceInfo || (navigator.userAgent + '_' + (navigator.hardwareConcurrency || '') + '_' + (screen.width || 0) + 'x' + (screen.height || 0))
        });
    }

    // Aggiunta: crea ordine
    async creaOrdine(ordineData, sessioneToken) {
        if (!ordineData) return { success: false, error: 'missing_ordine' };
        const opts = {};
        if (sessioneToken) opts.headers = { Authorization: 'Bearer ' + sessioneToken };
        return await this._postJson('ordini/crea-ordine.php', ordineData, opts);
    }

    async aggiornaStatoTavolo(tavoloId, nuovoStato) {
        if (!tavoloId) return { success: false, error: 'missing_tavoloId' };
        return await this._postJson('tavoli/aggiorna-stato-tavolo.php', {
            tavolo_id: tavoloId,
            nuovo_stato: nuovoStato
        });
    }

    // Compatibilità migliorata: GET con no-store, parsing robusto
    async getClientiRegistrati(tavoloIdOrParams) {
        try {
            // supporta sia getClientiRegistrati(tavoloId) che getClientiRegistrati({ tavolo_id, sessione, formEncoded })
            let tavoloId = null;
            let sessione = null;
            let formEncoded = true; // default: form-urlencoded per compatibilità legacy

            if (typeof tavoloIdOrParams === 'object' && tavoloIdOrParams !== null) {
                tavoloId = tavoloIdOrParams.tavolo_id || tavoloIdOrParams.tavolo || tavoloIdOrParams.tavoloId || null;
                sessione = tavoloIdOrParams.sessione || tavoloIdOrParams.session || null;
                if (typeof tavoloIdOrParams.formEncoded !== 'undefined') formEncoded = !!tavoloIdOrParams.formEncoded;
            } else {
                tavoloId = tavoloIdOrParams;
            }

            // Se il backend legacy si aspetta form-urlencoded, usiamo POST x-www-form-urlencoded
            if (formEncoded) {
                const form = new URLSearchParams();
                form.append('tavolo_id', String(tavoloId || ''));
                form.append('tavolo', String(tavoloId || ''));
                form.append('sessione', sessione || '');
                form.append('session', sessione || '');

                const resp = await fetch(this.baseUrl + 'clienti/get-clienti-registrati.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: form.toString(),
                    cache: 'no-store',
                    credentials: 'same-origin'
                });

                const text = await resp.text();
                try {
                    return text ? JSON.parse(text) : { success: resp.ok };
                } catch (e) {
                    return { success: resp.ok, raw: text, status: resp.status };
                }
            }

            // Fallback: GET
            return await this._getJson(`clienti/get-clienti-registrati.php?tavolo_id=${encodeURIComponent(tavoloId || '')}`);
        } catch (error) {
            console.error('Errore recupero clienti:', error);
            return { success: false, clienti: {}, error: error && error.message ? error.message : String(error) };
        }
    }

    async resettaClientiTavolo(tavoloId) {
        if (!tavoloId) return { success: false, error: 'missing_tavoloId' };
        return await this._postJson('clienti/resetta-clienti-tavolo.php', { tavolo_id: tavoloId });
    }

    async liberaTavolo(tavoloId, motivo, cameriereId) {
        if (!tavoloId) return { success: false, error: 'missing_tavoloId' };
        return await this._postJson('tavoli/libera-tavolo.php', {
            tavolo_id: tavoloId,
            motivo: motivo,
            cameriere_id: cameriereId
        });
    }

    async salvaClienteManuale(tavoloId, lettera, nome) {
        if (!tavoloId || !lettera) return { success: false, error: 'missing_parameters' };
        return await this._postJson('clienti/salva-cliente-manuale.php', {
            tavolo_id: tavoloId,
            lettera: lettera,
            nome: nome
        });
    }

    async cancellaClienteSingolo(tavoloId, lettera) {
        if (!tavoloId || !lettera) return { success: false, error: 'missing_parameters' };
        return await this._postJson('clienti/cancella-cliente-singolo.php', {
            tavolo_id: tavoloId,
            lettera: lettera
        });
    }
}

// Esporta l'istanza globale (manteniamo sia apiService che ApiService per compatibilità)
window.apiService = window.apiService || new ApiService();
window.ApiService = window.ApiService || window.apiService;