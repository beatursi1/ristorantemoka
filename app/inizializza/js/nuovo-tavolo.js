// nuovo-tavolo.js
// Gestisce il pulsante "Nuovo tavolo" (creazione immediata, aggiornamento UI).
// Richiede l'endpoint server: /api/tavoli/crea-tavolo.php

document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btn-new-table');
    if (!btn) return;

    btn.addEventListener('click', async function () {
        // disabilita il bottone e mostra spinner
        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creazione...';

        try {
            const resp = await fetch('/ristorantemoka/api/tavoli/crea-tavolo.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({}) // nessun payload: server autogenera il numero
            });

            if (!resp.ok) {
                console.error('Errore HTTP:', resp.status);
                alert('Errore durante la creazione del tavolo (HTTP ' + resp.status + ').');
                return;
            }

            const data = await resp.json();
            if (!data || data.success !== true || !data.tavolo) {
                console.error('Risposta API non valida', data);
                alert('Creazione tavolo fallita: ' + (data && data.error ? data.error : 'risposta non valida'));
                return;
            }

            // Aggiungi la card del tavolo appena creato nella UI
            addTableCardToUI(data.tavolo);
        } catch (err) {
            console.error('Fetch error', err);
            alert('Errore di connessione: impossibile creare il tavolo.');
        } finally {
            // ripristina il bottone
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
});

/**
 * Aggiunge dinamicamente la card del tavolo nella grid esistente.
 * Se non trova il container, forza il reload della pagina (fallback semplice).
 */
function addTableCardToUI(tavolo) {
    const container = document.getElementById('tavoli-container');
    if (!container) {
        // se non troviamo il container, ricarichiamo la pagina per mostrare il nuovo tavolo
        window.location.reload();
        return;
    }

    // Creo il wrapper col
    const col = document.createElement('div');
    col.className = 'col-md-3 col-6 mb-3';

    // Create la card con lo stesso markup usato in inizializza.php (SENZA tasto "Modifica")
    const cardDiv = document.createElement('div');
    // Assumiamo tavolo appena creato sia libero
    cardDiv.className = 'card tavolo-card text-center p-3 tavolo-libero tavolo-selectable';
    cardDiv.setAttribute('data-id', String(tavolo.id));
    cardDiv.setAttribute('data-numero', String(tavolo.numero));
    cardDiv.setAttribute('data-stato', 'libero');

    cardDiv.innerHTML = ''
        + '<i class="fas fa-table fa-3x mb-2"></i>'
        + '<h5 class="card-title">Tavolo ' + escapeHtml(String(tavolo.numero)) + '</h5>'
        + '<span class="badge bg-success">Libero</span>';

    col.appendChild(cardDiv);

    // Inserisci in coda alla lista dei tavoli (append), così il nuovo tavolo appare dopo gli esistenti
    container.appendChild(col);

    // Non aggiungiamo pulsanti duplicati: la selezione dei tavoli è gestita tramite delegated events in tavolo-manager.js
    // Aggiungiamo eventualmente una piccola animazione/attenzione
    cardDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });

    flashTemporaryMessage('Tavolo ' + tavolo.numero + ' creato', 2500);
}

// Utility: messaggio temporaneo (toast minimale)
function flashTemporaryMessage(msg, ms) {
    const el = document.createElement('div');
    el.style.position = 'fixed';
    el.style.right = '20px';
    el.style.top = '20px';
    el.style.background = '#28a745';
    el.style.color = '#fff';
    el.style.padding = '10px 14px';
    el.style.borderRadius = '4px';
    el.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
    el.style.zIndex = 9999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), ms || 2000);
}

// Utility: escape HTML semplice
function escapeHtml(unsafe) {
    return String(unsafe).replace(/[&<>"'\/]/g, function (s) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
            '/': '&#47;'
        }[s];
    });
}