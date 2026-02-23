<?php
// cucina/index.php
session_start();
define('ACCESS_ALLOWED', true);
require_once('../config/config.php');

if (!isset($_SESSION['utente_id']) || !in_array($_SESSION['utente_ruolo'], ['cucina', 'admin'])) {
    header('Location: /ristorantemoka/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cucina - RistoranteMoka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #1a1a2e; color: white; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        .header {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            padding: 12px 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header h1 { font-size: 1.1rem; font-weight: 700; margin: 0; }
        .header small { opacity: 0.85; font-size: 0.78rem; }

        .ordine-card {
            background: white; color: #333; border-radius: 10px;
            margin-bottom: 20px; overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border-left: 5px solid #3498db;
        }
        .ordine-header { background: #2c3e50; color: white; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; }
        .ordine-body { padding: 10px; }
        .piatto-item {
            padding: 12px 15px; border-bottom: 1px solid #f0f0f0;
            display: flex; justify-content: space-between; align-items: center;
            cursor: pointer; transition: background 0.2s;
            border-radius: 8px; margin-bottom: 4px;
        }
        .piatto-item:hover { background: #f8f9fa; }
        .piatto-item.piatto-attesa       { border-left: 5px solid #f39c12; }
        .piatto-item.piatto-preparazione { border-left: 5px solid #3498db; background: #ebf5ff; }
        .piatto-item.piatto-pronto       { border-left: 5px solid #27ae60; background: #eafff2; opacity: 0.8; }
        .piatto-urgente-anim {
            animation: pulse-red 1.5s infinite;
            background: #fff5f5 !important;
        }
        @keyframes pulse-red {
            0%   { box-shadow: inset 0 0 0 0px rgba(231,76,60,0.4); }
            100% { box-shadow: inset 0 0 0 10px rgba(231,76,60,0); }
        }
        .stato-colonna { background: rgba(255,255,255,0.05); border-radius: 10px; padding: 15px; min-height: 500px; }
        .colonna-titolo { text-align: center; padding: 10px; margin-bottom: 15px; border-radius: 8px; font-weight: bold; }
        #in-attesa-titolo       { background: rgba(243,156,18,0.2); color: #f39c12; }
        #in-preparazione-titolo { background: rgba(52,152,219,0.2); color: #3498db; }
        #pronto-titolo          { background: rgba(39,174,96,0.2);  color: #27ae60; }
        .riepilogo-box {
            background: rgba(255,255,255,0.1); border-radius: 10px;
            padding: 15px; margin-bottom: 20px;
            display: flex; flex-wrap: wrap; gap: 10px;
        }
        .totale-item {
            background: #fff; color: #1a1a2e;
            padding: 5px 15px; border-radius: 20px;
            font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        #sidebar-archivio {
            position: fixed; right: -350px; top: 0;
            width: 350px; height: 100%;
            background: #2c3e50;
            box-shadow: -5px 0 15px rgba(0,0,0,0.5);
            transition: 0.3s; z-index: 1060;
            padding: 20px; overflow-y: auto;
        }
        #sidebar-archivio.open { right: 0; }

        /* FAB — solo aggiorna e archivio, logout tolto */
        .aggiorna-btn { position: fixed; bottom: 20px;  right: 20px; background: #e74c3c; color: white; border: none; width: 60px; height: 60px; border-radius: 50%; font-size: 24px; z-index: 1000; cursor: pointer; }
        .btn-archivio { position: fixed; bottom: 100px; right: 20px; background: #34495e; color: white; border: none; width: 60px; height: 60px; border-radius: 50%; z-index: 1000; cursor: pointer; }
    </style>
</head>
<body>

<!-- HEADER con logout sempre visibile -->
<div class="header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-utensils me-2"></i>CUCINA RISTORANTEMOKA</h1>
            <small>Chef: <?php echo htmlspecialchars($_SESSION['utente_nome']); ?></small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="bg-dark px-3 py-2 rounded d-none d-md-block">
                <i class="fas fa-clock me-1 text-warning"></i>
                <span id="orario">--:--:--</span>
            </div>
            <?php if ($_SESSION['utente_ruolo'] === 'admin'): ?>
            <a href="/ristorantemoka/admin/dashboard.php" class="btn btn-sm btn-warning fw-bold me-1">
                <i class="fas fa-user-shield me-1"></i>Admin
            </a>
            <?php endif; ?>
            <a href="/ristorantemoka/logout.php" class="btn btn-sm btn-outline-light">
                <i class="fas fa-sign-out-alt me-1"></i>Esci
            </a>
        </div>
    </div>
</div>

<div class="container mt-3 px-4">
    <div id="box-totali" class="riepilogo-box">
        <div class="text-white w-100 mb-1 small fw-bold text-uppercase" style="opacity:0.8;">
            <i class="fas fa-list-ul me-2"></i>Riepilogo piatti da preparare:
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="stato-colonna">
                <h3 class="colonna-titolo" id="in-attesa-titolo">
                    IN ATTESA <span class="badge bg-warning ms-2" id="count-attesa">0</span>
                </h3>
                <div id="ordini-attesa"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stato-colonna">
                <h3 class="colonna-titolo" id="in-preparazione-titolo">
                    IN PREPARAZIONE <span class="badge bg-primary ms-2" id="count-preparazione">0</span>
                </h3>
                <div id="ordini-preparazione"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stato-colonna">
                <h3 class="colonna-titolo" id="pronto-titolo">
                    PRONTO <span class="badge bg-success ms-2" id="count-pronto">0</span>
                </h3>
                <div id="ordini-pronto"></div>
            </div>
        </div>
    </div>
</div>

<!-- FAB: solo aggiorna e archivio -->
<button class="aggiorna-btn" title="Aggiorna" onClick="caricaOrdini()">
    <i class="fas fa-sync-alt"></i>
</button>
<button class="btn-archivio" title="Archivio serviti" onClick="toggleArchivio()">
    <i class="fas fa-history"></i>
</button>

<!-- Sidebar archivio -->
<div id="sidebar-archivio">
    <div class="d-flex justify-content-between align-items-center mb-4 text-white">
        <h4 class="mb-0">Serviti (Ultima ora)</h4>
        <button class="btn btn-sm btn-outline-light" onClick="toggleArchivio()">Chiudi</button>
    </div>
    <div id="lista-archivio"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const API_BASE = '../api/';
    let tuttiPiatti = [];

    window.onload = function() {
        aggiornaOrario();
        caricaOrdini();
        setInterval(aggiornaOrario, 1000);
        setInterval(caricaOrdini, 10000);
        setInterval(aggiornaTimerOrdini, 1000);
    };

    function aggiornaOrario() {
        const el = document.getElementById('orario');
        if (el) el.textContent = new Date().toLocaleTimeString('it-IT');
    }

    async function caricaOrdini() {
        try {
            const response = await fetch(API_BASE + 'ordini/ordini-cucina.php');
            const data = await response.json();
            if (data.success) {
                tuttiPiatti = data.data;
                const attivi   = tuttiPiatti.filter(p => parseInt(p.piatto_stato) < 3);
                const archivio = tuttiPiatti.filter(p => parseInt(p.piatto_stato) === 3);
                mostraOrdiniPerStato(attivi);
                aggiornaTotali(attivi);
                mostraArchivio(archivio);
                document.getElementById('count-attesa').textContent       = attivi.filter(p => parseInt(p.piatto_stato) === 0).length;
                document.getElementById('count-preparazione').textContent = attivi.filter(p => parseInt(p.piatto_stato) === 1).length;
                document.getElementById('count-pronto').textContent       = attivi.filter(p => parseInt(p.piatto_stato) === 2).length;
            }
        } catch (error) { console.error('Errore:', error); }
    }

    function mostraOrdiniPerStato(piatti) {
        const c0 = document.getElementById('ordini-attesa');
        const c1 = document.getElementById('ordini-preparazione');
        const c2 = document.getElementById('ordini-pronto');
        c0.innerHTML = ''; c1.innerHTML = ''; c2.innerHTML = '';

        const tavoli = {};
        piatti.forEach(p => {
            if (!tavoli[p.tavolo_numero]) tavoli[p.tavolo_numero] = { numero: p.tavolo_numero, piatti: [] };
            tavoli[p.tavolo_numero].piatti.push(p);
        });

        Object.keys(tavoli).sort((a,b) => parseInt(a) - parseInt(b)).forEach(num => {
            [0,1,2].forEach(st => {
                const filtered = tavoli[num].piatti.filter(p => parseInt(p.piatto_stato) === st);
                if (filtered.length > 0) {
                    const target = st === 0 ? c0 : (st === 1 ? c1 : c2);
                    target.innerHTML += creaCardTavolo(num, filtered);
                }
            });
        });
    }

    function creaCardTavolo(tavolo, piatti) {
        return `
        <div class="ordine-card">
            <div class="ordine-header"><strong>TAVOLO ${tavolo}</strong></div>
            <div class="ordine-body">
                ${piatti.map(p => {
                    const min = Math.floor((new Date() - new Date(p.data_ordine)) / 60000);
                    return `
                    <div class="piatto-item piatto-${getStatoClass(p.piatto_stato)}" data-data-ordine="${p.data_ordine}">
                        <div style="flex:1" onclick="prossimoStato(${p.riga_id}, ${p.piatto_stato})">
                            <div class="fw-bold text-uppercase" style="font-size:0.85rem">${p.piatto_nome}</div>
                            <small class="text-secondary">${p.cliente_nome ? p.cliente_nome + ' (' + p.cliente_lettera + ')' : 'Cliente ' + p.cliente_lettera}</small>
                        </div>
                        <div class="d-flex align-items-center">
                            ${p.piatto_stato > 0 ? `<button class="btn btn-sm btn-warning me-2" onclick="event.stopPropagation(); prossimoStato(${p.riga_id}, ${p.piatto_stato}, true)" title="Torna indietro"><i class="fas fa-undo"></i></button>` : ''}
                            <div class="text-end">
                                <span class="badge bg-dark">x${p.quantita}</span><br>
                                <span class="timer-piatto small fw-bold text-warning">${min}m</span>
                            </div>
                        </div>
                    </div>`;
                }).join('')}
            </div>
        </div>`;
    }

    function aggiornaTimerOrdini() {
        document.querySelectorAll('.piatto-item').forEach(item => {
            const dataStr   = item.getAttribute('data-data-ordine');
            const timerSpan = item.querySelector('.timer-piatto');
            if (dataStr && timerSpan) {
                const min = Math.floor((new Date() - new Date(dataStr)) / 60000);
                timerSpan.textContent = min + 'm';
                if (min >= 20 && !item.classList.contains('piatto-urgente-anim')) {
                    item.classList.add('piatto-urgente-anim');
                }
            }
        });
    }

    function aggiornaTotali(piatti) {
        const box    = document.getElementById('box-totali');
        const daFare = piatti.filter(p => parseInt(p.piatto_stato) < 2);
        const conteggio = {};
        daFare.forEach(p => { conteggio[p.piatto_nome] = (conteggio[p.piatto_nome] || 0) + parseInt(p.quantita); });
        const titolo = box.querySelector('.text-white');
        box.innerHTML = '';
        if (titolo) box.appendChild(titolo);
        Object.entries(conteggio).forEach(([nome, qta]) => {
            const el = document.createElement('div');
            el.className = 'totale-item';
            el.innerHTML = `<span class="text-danger">${qta}</span> ${nome}`;
            box.appendChild(el);
        });
    }

    function mostraArchivio(piatti) {
        const container = document.getElementById('lista-archivio');
        container.innerHTML = '';
        if (!piatti.length) {
            container.innerHTML = '<p class="text-white-50 small text-center mt-4">Nessun piatto servito.</p>';
            return;
        }
        [...piatti].reverse().forEach(p => {
            const item = document.createElement('div');
            item.className = 'p-2 mb-2 bg-white rounded text-dark d-flex justify-content-between align-items-center shadow-sm';
            item.innerHTML = `
                <div style="line-height:1.2">
                    <strong class="d-block">${p.piatto_nome}</strong>
                    <small class="text-muted">Tavolo ${p.tavolo_numero}</small>
                </div>
                <button class="btn btn-sm btn-success ms-2" onclick="prossimoStato(${p.riga_id}, ${p.piatto_stato}, true)" title="Riporta a Pronto">
                    <i class="fas fa-undo"></i>
                </button>`;
            container.appendChild(item);
        });
    }

    function toggleArchivio() { document.getElementById('sidebar-archivio').classList.toggle('open'); }
    function getStatoClass(s) { return ['attesa','preparazione','pronto'][s] || 'attesa'; }

    async function prossimoStato(rigaId, attuale, isUndo = false) {
        const sAttuale = parseInt(attuale);
        const nuovo    = isUndo ? sAttuale - 1 : sAttuale + 1;
        if (nuovo < 0 || nuovo > 3) return;
        try {
            const resp = await fetch(API_BASE + 'ordini/cambia-stato-piatto.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ riga_id: rigaId, nuovo_stato: nuovo })
            });
            if ((await resp.json()).success) caricaOrdini();
        } catch(e) { console.error(e); }
    }
</script>
</body>
</html>