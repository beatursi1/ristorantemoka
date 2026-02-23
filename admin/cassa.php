<?php
// admin/cassa.php
session_start();
define('ACCESS_ALLOWED', true);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once('../config/config.php');

if (!isset($_SESSION['utente_id']) || $_SESSION['utente_ruolo'] !== 'admin') {
    header('Location: /ristorantemoka/login.php');
    exit;
}
$utente_nome = $_SESSION['utente_nome'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cassa — RistoranteMoka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary:  #2c3e50;
            --accent:   #3498db;
            --gold:     #f39c12;
            --green:    #27ae60;
            --card-r:   14px;
        }
        * { box-sizing: border-box; }
        body { background: #f0f4f8; font-family: 'Segoe UI', system-ui, sans-serif; color: #1e293b; }

        /* TOPBAR */
        .topbar { background: var(--primary); color: white; padding: 14px 16px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .topbar h1 { font-size: 1.1rem; font-weight: 700; margin: 0; }
        .topbar small { opacity: 0.75; font-size: 0.78rem; }

        /* NAV MOBILE */
        .nav-mobile { background: #34495e; display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; padding: 0 8px; }
        .nav-mobile::-webkit-scrollbar { display: none; }
        .nav-mobile a { color: rgba(255,255,255,0.8); text-decoration: none; white-space: nowrap; padding: 10px 14px; font-size: 0.82rem; font-weight: 600; border-bottom: 3px solid transparent; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-mobile a:hover, .nav-mobile a.active { color: white; border-bottom-color: white; }

        /* SIDEBAR */
        .sidebar { background: var(--primary); min-height: 100vh; padding-top: 20px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 12px 20px; border-radius: 8px; margin: 0 10px 5px; display: block; text-decoration: none; transition: 0.2s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: white; background: rgba(255,255,255,0.1); }
        .sidebar .nav-link.active { background: var(--accent); font-weight: 600; }

        /* STEP CARD */
        .step-card { background: white; border-radius: var(--card-r); padding: 20px 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .step-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .step-title .step-num { background: var(--accent); color: white; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; flex-shrink: 0; }

        /* GRIGLIA TAVOLI */
        .tavoli-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; }
        .tavolo-card-btn {
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 12px;
            padding: 14px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.18s;
            position: relative;
        }
        .tavolo-card-btn:hover { border-color: var(--accent); box-shadow: 0 4px 12px rgba(52,152,219,0.15); transform: translateY(-2px); }
        .tavolo-card-btn.selected { border-color: var(--accent); background: #ebf5fb; }
        .tavolo-card-btn .t-num { font-size: 1.6rem; font-weight: 900; color: #1e293b; display: block; line-height: 1; }
        .tavolo-card-btn .t-clienti { font-size: 0.7rem; color: #64748b; margin-top: 4px; }
        .tavolo-card-btn .t-totale { font-size: 0.82rem; font-weight: 700; color: var(--green); margin-top: 4px; }
        .tavolo-card-btn .t-tempo { font-size: 0.65rem; color: #94a3b8; margin-top: 2px; }
        .badge-occ { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; background: var(--green); border-radius: 50%; }

        /* MODALITÀ PAGAMENTO */
        .modo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        @media (max-width: 576px) { .modo-grid { grid-template-columns: 1fr; } }
        .modo-btn { border: 2px solid #e2e8f0; background: white; border-radius: 12px; padding: 18px 12px; text-align: center; cursor: pointer; transition: all 0.18s; }
        .modo-btn:hover { border-color: var(--gold); box-shadow: 0 4px 12px rgba(243,156,18,0.15); }
        .modo-btn.selected { border-color: var(--gold); background: #fffbeb; }
        .modo-icon { font-size: 2rem; display: block; margin-bottom: 8px; }
        .modo-label { font-size: 0.88rem; font-weight: 700; color: #1e293b; display: block; }
        .modo-desc { font-size: 0.7rem; color: #64748b; margin-top: 3px; display: block; }

        /* BANNER TOTALE */
        .totale-banner { background: linear-gradient(135deg, #1e293b, #2c3e50); color: white; border-radius: var(--card-r); padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .totale-banner .b-label { font-size: 0.75rem; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.05em; }
        .totale-banner .b-val { font-size: 2.2rem; font-weight: 900; line-height: 1; }
        .totale-banner .b-modo { font-size: 0.78rem; opacity: 0.65; margin-top: 3px; }

        /* CLIENTE CARD */
        .cliente-card { background: white; border-radius: 12px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .c-header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; margin-bottom: 10px; }
        .badge-lettera { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.05rem; flex-shrink: 0; color: white; }
        .c-nome { font-weight: 700; font-size: 1rem; }
        .c-totale { font-size: 1.25rem; font-weight: 900; color: var(--green); }
        .c-totale.equo { color: var(--gold); }

        .riga-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 5px 0; font-size: 0.82rem; border-bottom: 1px solid #f8fafc; }
        .riga-item:last-child { border-bottom: none; }
        .riga-nome { flex: 1; color: #334155; }
        .riga-nota { font-size: 0.68rem; color: #94a3b8; display: block; margin-top: 1px; }
        .riga-importo { font-weight: 700; color: #1e293b; white-space: nowrap; margin-left: 12px; }
        .badge-tipo { font-size: 0.6rem; padding: 1px 5px; border-radius: 4px; background: #f0fdf4; color: #166534; margin-left: 4px; vertical-align: middle; }
        .badge-tipo.bev { background: #eff6ff; color: #1e40af; }

        /* AZIONI */
        .azioni-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; padding-bottom: 40px; }
        .btn-cassa { flex: 1; min-width: 140px; padding: 14px 20px; border-radius: 10px; font-weight: 700; font-size: 0.95rem; border: none; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-cassa:active { transform: scale(0.97); }
        .btn-stampa { background: #e74c3c; color: white; }
        .btn-stampa:hover { background: #c0392b; }
        .btn-chiudi { background: var(--green); color: white; }
        .btn-chiudi:hover { background: #229954; }
        .btn-cambia { flex: 0; background: #f1f5f9; color: #475569; }
        .btn-cambia:hover { background: #e2e8f0; }

        .loader-box { text-align: center; padding: 40px 20px; color: #94a3b8; }
        .empty-box { text-align: center; padding: 40px 20px; color: #94a3b8; }

        /* SCONTRINO STAMPA */
        @media print {
            .topbar, .nav-mobile, .sidebar, .step-card, .azioni-bar, nav, .totale-banner { display: none !important; }
            body { background: white !important; }
            .print-area { display: block !important; }
            .cliente-card { box-shadow: none !important; border: 1px solid #ccc; break-inside: avoid; margin-bottom: 8px; }
        }
        .print-area { display: none; }
        .scontrino-wrap { max-width: 380px; margin: 0 auto; font-family: 'Courier New', monospace; font-size: 0.85rem; }
        .scontrino-header { text-align: center; border-bottom: 2px dashed #999; padding-bottom: 10px; margin-bottom: 10px; }
        .scontrino-row { display: flex; justify-content: space-between; margin: 3px 0; }
        .scontrino-sep { border-top: 1px dashed #ccc; margin: 8px 0; }
        .scontrino-total { display: flex; justify-content: space-between; font-weight: bold; font-size: 1rem; border-top: 2px dashed #999; padding-top: 8px; margin-top: 6px; }
        .scontrino-footer { text-align: center; font-size: 0.72rem; color: #666; margin-top: 14px; }

        /* COLORI CLIENTI */
        .col-A { background: #e74c3c; } .col-B { background: #3498db; }
        .col-C { background: #27ae60; } .col-D { background: #9b59b6; }
        .col-E { background: #f39c12; } .col-F { background: #1abc9c; }
        .col-G { background: #e67e22; } .col-default { background: #2c3e50; }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-cash-register me-2 text-warning"></i>Cassa</h1>
            <small>Admin: <?php echo htmlspecialchars($utente_nome); ?></small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-light" onclick="caricaTavoli()" title="Aggiorna lista tavoli">
                <i class="fas fa-sync-alt" id="icon-refresh"></i>
            </button>
            <a href="/ristorantemoka/logout.php" class="btn btn-sm btn-outline-light">
                <i class="fas fa-sign-out-alt me-1"></i>Esci
            </a>
        </div>
    </div>
</div>

<!-- NAV MOBILE -->
<nav class="nav-mobile d-md-none">
    <a href="dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
    <a href="tavoli.php"><i class="fas fa-table"></i>Tavoli</a>
    <a href="../app/admin/admin-menu.html"><i class="fas fa-book-open"></i>Menù</a>
    <a href="utenti.php"><i class="fas fa-users-cog"></i>Utenti</a>
    <a href="manutenzione.php"><i class="fas fa-database"></i>Manutenzione</a>
    <a href="cassa.php" class="active"><i class="fas fa-cash-register"></i>Cassa</a>
</nav>

<div class="container-fluid">
<div class="row">

    <!-- SIDEBAR desktop -->
    <nav class="col-md-2 d-none d-md-block sidebar">
        <div class="text-center mb-4">
            <i class="fas fa-utensils fa-2x text-info mb-2"></i>
            <h6 class="text-white fw-bold mb-0">Moka Admin</h6>
            <hr class="mx-3 opacity-25">
        </div>
        <ul class="nav flex-column">
            <li><a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
            <li><a class="nav-link" href="tavoli.php"><i class="fas fa-table me-2"></i>Tavoli</a></li>
            <li><a class="nav-link" href="../app/admin/admin-menu.html"><i class="fas fa-book-open me-2"></i>Menù</a></li>
            <li><a class="nav-link" href="utenti.php"><i class="fas fa-users-cog me-2"></i>Utenti</a></li>
            <li><a class="nav-link" href="manutenzione.php"><i class="fas fa-database me-2"></i>Manutenzione DB</a></li>
            <li><a class="nav-link active" href="cassa.php"><i class="fas fa-cash-register me-2"></i>Cassa</a></li>
            <li class="mt-4"><a class="nav-link text-danger" href="/ristorantemoka/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Esci</a></li>
        </ul>
    </nav>

    <!-- MAIN -->
    <main class="col-md-10 px-3 px-md-4 py-4">

        <!-- STEP 1: TAVOLI -->
        <div class="step-card">
            <div class="step-title">
                <span class="step-num">1</span>
                Seleziona il tavolo da chiudere
            </div>
            <div class="tavoli-grid" id="tavoli-grid">
                <div class="loader-box w-100">
                    <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                    <div class="small">Caricamento tavoli...</div>
                </div>
            </div>
        </div>

        <!-- STEP 2: MODALITÀ (nascosto) -->
        <div class="step-card" id="step-modo" style="display:none;">
            <div class="step-title">
                <span class="step-num">2</span>
                Come vuole pagare il tavolo <strong id="label-tavolo-scelto"></strong>?
            </div>
            <div class="modo-grid">
                <div class="modo-btn" onclick="selezionaModo('totale', this)">
                    <span class="modo-icon">💳</span>
                    <span class="modo-label">Conto Unico</span>
                    <span class="modo-desc">Paga tutto un cliente</span>
                </div>
                <div class="modo-btn" onclick="selezionaModo('per_cliente', this)">
                    <span class="modo-icon">👤</span>
                    <span class="modo-label">Per Cliente</span>
                    <span class="modo-desc">Ognuno paga esattamente il suo</span>
                </div>
                <div class="modo-btn" onclick="selezionaModo('equo', this)">
                    <span class="modo-icon">⚖️</span>
                    <span class="modo-label">Parti Uguali</span>
                    <span class="modo-desc">Totale ÷ numero clienti</span>
                </div>
            </div>
        </div>

        <!-- STEP 3: CONTO (nascosto) -->
        <div id="step-conto" style="display:none;">

            <div class="totale-banner">
                <div>
                    <div class="b-label">Tavolo</div>
                    <div class="b-val" id="banner-tavolo">—</div>
                    <div class="b-modo" id="banner-modo"></div>
                </div>
                <div class="text-end">
                    <div class="b-label">Totale</div>
                    <div class="b-val">€ <span id="banner-totale">0.00</span></div>
                    <div class="b-modo" id="banner-clienti"></div>
                </div>
            </div>

            <div id="conto-dettaglio"></div>

            <div class="azioni-bar">
                <button class="btn-cassa btn-stampa" onclick="stampaConto()">
                    <i class="fas fa-print"></i>Stampa / PDF
                </button>
                <button class="btn-cassa btn-chiudi" onclick="confermaChiusura()">
                    <i class="fas fa-check-circle"></i>Chiudi Tavolo
                </button>
                <button class="btn-cassa btn-cambia" onclick="cambiaModo()">
                    <i class="fas fa-redo"></i>Cambia
                </button>
            </div>
        </div>

        <!-- AREA STAMPA -->
        <div class="print-area" id="print-area"></div>

    </main>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';

let tavoloSelezionato = null; // { id, numero, num_clienti, totale_provvisorio }
let modoSelezionato   = null;
let datiConto         = null;

// ── AVVIO ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', caricaTavoli);

// ── CARICA TAVOLI ─────────────────────────────────────────
async function caricaTavoli() {
    const grid = document.getElementById('tavoli-grid');
    const icon = document.getElementById('icon-refresh');
    icon.classList.add('fa-spin');
    grid.innerHTML = `<div class="loader-box w-100"><div class="spinner-border spinner-border-sm text-primary mb-2"></div><div class="small">Caricamento...</div></div>`;

    try {
        const r = await fetch('../api/tavoli/get-tavoli-occupati.php');
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const d = await r.json();
        if (!d.success) throw new Error(d.error || 'Errore API');
        renderTavoli(d.tavoli || []);
    } catch(e) {
        grid.innerHTML = `<div class="empty-box w-100">
            <i class="fas fa-exclamation-triangle text-danger fa-2x mb-2 d-block"></i>
            <div class="small text-danger">Errore: ${e.message}</div>
            <button class="btn btn-sm btn-outline-primary mt-3" onclick="caricaTavoli()">Riprova</button>
        </div>`;
    } finally {
        icon.classList.remove('fa-spin');
    }
}

function renderTavoli(tavoli) {
    const grid = document.getElementById('tavoli-grid');

    if (!tavoli.length) {
        grid.innerHTML = `<div class="empty-box w-100">
            <i class="fas fa-coffee fa-3x mb-3 d-block" style="opacity:0.2;"></i>
            <div class="fw-bold">Nessun tavolo occupato</div>
            <div class="small text-muted mt-1">Tutti i tavoli sono liberi al momento.</div>
        </div>`;
        return;
    }

    grid.innerHTML = tavoli.map(t => {
        const ore   = Math.floor(t.minuti_aperti / 60);
        const min   = t.minuti_aperti % 60;
        const tempo = ore > 0 ? `${ore}h ${min}m` : `${min}m`;
        return `
        <div class="tavolo-card-btn" onclick="selezionaTavolo(${t.id}, '${t.numero}')" data-id="${t.id}">
            <div class="badge-occ"></div>
            <span class="t-num">${t.numero}</span>
            <div class="t-clienti"><i class="fas fa-users me-1"></i>${t.num_clienti} client${t.num_clienti === 1 ? 'e' : 'i'}</div>
            <div class="t-totale">€ ${t.totale_provvisorio.toFixed(2)}</div>
            <div class="t-tempo"><i class="fas fa-clock me-1"></i>${tempo}</div>
        </div>`;
    }).join('');
}

// ── SELEZIONE TAVOLO ──────────────────────────────────────
function selezionaTavolo(id, numero) {
    // Evidenzia selezione
    document.querySelectorAll('.tavolo-card-btn').forEach(b => b.classList.remove('selected'));
    const btn = document.querySelector(`.tavolo-card-btn[data-id="${id}"]`);
    if (btn) btn.classList.add('selected');

    tavoloSelezionato = { id, numero };

    // Aggiorna label step 2
    document.getElementById('label-tavolo-scelto').textContent = numero;

    // Mostra step 2, nascondi step 3
    const stepModo = document.getElementById('step-modo');
    stepModo.style.display = 'block';
    document.getElementById('step-conto').style.display = 'none';
    document.querySelectorAll('.modo-btn').forEach(b => b.classList.remove('selected'));
    modoSelezionato = null;
    datiConto = null;

    stepModo.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── SELEZIONE MODO ─────────────────────────────────────────
function selezionaModo(modo, el) {
    modoSelezionato = modo;
    document.querySelectorAll('.modo-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    caricaConto();
}

function cambiaModo() {
    document.getElementById('step-conto').style.display = 'none';
    document.getElementById('step-modo').scrollIntoView({ behavior: 'smooth' });
}

// ── CARICA CONTO ──────────────────────────────────────────
async function caricaConto() {
    if (!tavoloSelezionato || !modoSelezionato) return;

    const stepConto = document.getElementById('step-conto');
    stepConto.style.display = 'block';
    document.getElementById('conto-dettaglio').innerHTML = `
        <div class="loader-box">
            <div class="spinner-border text-primary mb-2"></div>
            <div class="small text-muted">Calcolo conto in corso...</div>
        </div>`;
    stepConto.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    try {
        const r = await fetch(`../api/ordini/get-conto-tavolo.php?tavolo_id=${tavoloSelezionato.id}`);
        if (!r.ok) throw new Error('HTTP ' + r.status);
        datiConto = await r.json();
        if (!datiConto.success) throw new Error(datiConto.error || 'Errore calcolo conto');
        renderConto();
    } catch(e) {
        document.getElementById('conto-dettaglio').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Errore:</strong> ${e.message}
            </div>`;
    }
}

// ── RENDER CONTO ──────────────────────────────────────────
function renderConto() {
    const d = datiConto;
    const modoLabel = {
        totale:      'Conto Unico',
        per_cliente: 'Dettagliato per Cliente',
        equo:        'Parti Uguali'
    }[modoSelezionato];

    document.getElementById('banner-tavolo').textContent   = d.tavolo.numero;
    document.getElementById('banner-totale').textContent   = d.totale_generale.toFixed(2);
    document.getElementById('banner-modo').textContent     = modoLabel;
    document.getElementById('banner-clienti').textContent  = `${d.num_clienti} client${d.num_clienti === 1 ? 'e' : 'i'}`;

    const cont = document.getElementById('conto-dettaglio');
    if      (modoSelezionato === 'totale')      cont.innerHTML = renderTotale(d);
    else if (modoSelezionato === 'per_cliente') cont.innerHTML = renderPerCliente(d);
    else                                         cont.innerHTML = renderEquo(d);
}

function colorLettera(lett) {
    const mappa = { A:'#e74c3c', B:'#3498db', C:'#27ae60', D:'#9b59b6', E:'#f39c12', F:'#1abc9c', G:'#e67e22' };
    return mappa[lett] || '#2c3e50';
}

function renderTotale(d) {
    let righeHtml = '';
    d.righe_dettaglio.forEach(r => {
        const isBev = r.tipo && r.tipo.startsWith('bevanda');
        const badge = isBev ? `<span class="badge-tipo bev">🍷</span>` : `<span class="badge-tipo">🍽️</span>`;
        const qtaStr = r.qta > 1 ? ` <span class="text-muted">×${r.qta}</span>` : '';
        const condStr = r.condivisione !== 'personale' ? `<span class="riga-nota">condiviso</span>` : '';
        righeHtml += `
            <div class="riga-item">
                <div class="riga-nome">${r.nome}${badge}${qtaStr}${condStr}</div>
                <div class="riga-importo">€ ${r.totale.toFixed(2)}</div>
            </div>`;
    });
    return `
        <div class="cliente-card">
            <div class="c-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="badge-lettera" style="background:#2c3e50; font-size:1.2rem;">🏠</div>
                    <div><div class="c-nome">Tavolo ${d.tavolo.numero}</div><div class="text-muted small">${d.num_clienti} clienti</div></div>
                </div>
                <div class="c-totale">€ ${d.totale_generale.toFixed(2)}</div>
            </div>
            ${righeHtml}
        </div>`;
}

function renderPerCliente(d) {
    return d.split_per_cliente.map(cli => {
        let righeHtml = '';
        (cli.righe || []).forEach(r => {
            const isBev = r.tipo && r.tipo.startsWith('bevanda');
            const badge = isBev ? `<span class="badge-tipo bev">🍷</span>` : `<span class="badge-tipo">🍽️</span>`;
            const qtaStr = r.qta > 1 ? ` <span class="text-muted">×${r.qta}</span>` : '';
            const notaStr = r.nota ? `<span class="riga-nota">${r.nota}</span>` : '';
            righeHtml += `
                <div class="riga-item">
                    <div class="riga-nome">${r.nome}${badge}${qtaStr}${notaStr}</div>
                    <div class="riga-importo">€ ${r.totale.toFixed(2)}</div>
                </div>`;
        });
        return `
            <div class="cliente-card">
                <div class="c-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="badge-lettera" style="background:${colorLettera(cli.lettera)};">${cli.lettera}</div>
                        <div class="c-nome">${cli.nome}</div>
                    </div>
                    <div class="c-totale">€ ${cli.importo.toFixed(2)}</div>
                </div>
                ${righeHtml || '<div class="text-muted small py-1 fst-italic">Nessun ordine personale</div>'}
            </div>`;
    }).join('');
}

function renderEquo(d) {
    return d.split_equo.map(cli => `
        <div class="cliente-card">
            <div class="c-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="badge-lettera" style="background:${colorLettera(cli.lettera)};">${cli.lettera}</div>
                    <div class="c-nome">${cli.nome}</div>
                </div>
                <div class="c-totale equo">€ ${cli.importo.toFixed(2)}</div>
            </div>
            <div class="text-muted small">
                Quota: € ${datiConto.totale_generale.toFixed(2)} ÷ ${datiConto.num_clienti} = € ${cli.importo.toFixed(2)}
            </div>
        </div>`).join('');
}

// ── STAMPA ────────────────────────────────────────────────
function stampaConto() {
    if (!datiConto) return;
    const d = datiConto;
    const ora = new Date().toLocaleString('it-IT');
    const modoLabel = { totale:'Conto Unico', per_cliente:'Dettagliato per Cliente', equo:'Parti Uguali' }[modoSelezionato];

    let corpo = '';

    if (modoSelezionato === 'totale') {
        d.righe_dettaglio.forEach(r => {
            corpo += `<div class="scontrino-row"><span>${r.nome}${r.qta>1?' ×'+r.qta:''}</span><span>€ ${r.totale.toFixed(2)}</span></div>`;
        });
        corpo += `<div class="scontrino-total"><span>TOTALE</span><span>€ ${d.totale_generale.toFixed(2)}</span></div>`;

    } else if (modoSelezionato === 'per_cliente') {
        d.split_per_cliente.forEach(cli => {
            corpo += `<div class="scontrino-sep"></div>
                <div style="font-weight:bold;">Cli. ${cli.lettera} — ${cli.nome}</div>`;
            (cli.righe || []).forEach(r => {
                corpo += `<div class="scontrino-row"><span>${r.nome}${r.nota?' ('+r.nota+')':''}</span><span>€ ${r.totale.toFixed(2)}</span></div>`;
            });
            corpo += `<div class="scontrino-row" style="font-weight:bold;"><span>Subtotale ${cli.lettera}</span><span>€ ${cli.importo.toFixed(2)}</span></div>`;
        });
        corpo += `<div class="scontrino-total"><span>TOTALE TAVOLO</span><span>€ ${d.totale_generale.toFixed(2)}</span></div>`;

    } else {
        corpo += `<div class="scontrino-row"><span>Totale tavolo</span><span>€ ${d.totale_generale.toFixed(2)}</span></div>`;
        corpo += `<div class="scontrino-row"><span>÷ ${d.num_clienti} clienti</span><span></span></div>`;
        d.split_equo.forEach(cli => {
            corpo += `<div class="scontrino-row"><span>${cli.nome} (${cli.lettera})</span><span>€ ${cli.importo.toFixed(2)}</span></div>`;
        });
        corpo += `<div class="scontrino-total"><span>TOTALE</span><span>€ ${d.totale_generale.toFixed(2)}</span></div>`;
    }

    document.getElementById('print-area').innerHTML = `
        <div class="scontrino-wrap">
            <div class="scontrino-header">
                <div style="font-size:1.3rem; font-weight:900;">☕ RistoranteMoka</div>
                <div style="margin-top:4px;">Tavolo ${d.tavolo.numero} · ${modoLabel}</div>
                <div style="font-size:0.72rem; color:#666; margin-top:2px;">${ora}</div>
            </div>
            ${corpo}
            <div class="scontrino-footer">
                Grazie per la visita!<br>
                <em>Documento non fiscale — chiedere ricevuta alla cassa</em>
            </div>
        </div>`;
    window.print();
}

// ── CHIUDI TAVOLO ─────────────────────────────────────────
async function confermaChiusura() {
    if (!tavoloSelezionato?.id) { alert('Seleziona un tavolo valido.'); return; }
    if (!datiConto) { alert('Carica prima il conto.'); return; }

    const ok = confirm(
        `Chiudere il Tavolo ${tavoloSelezionato.numero}?\n\n` +
        `Totale: € ${datiConto.totale_generale.toFixed(2)}\n` +
        `Clienti: ${datiConto.num_clienti}\n\n` +

        `Il tavolo verrà liberato e la sessione chiusa.`
    );
    if (!ok) return;

    try {
        const r = await fetch('../api/tavoli/libera-tavolo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tavolo_id:    tavoloSelezionato.id,
                motivo:       'Chiusura conto cassa (' + modoSelezionato + ')',
                cameriere_id: 0
            })
        });
        const d = await r.json();
        if (!d.success) throw new Error(d.error || 'Errore nella chiusura');

        alert(`✅ Tavolo ${tavoloSelezionato.numero} chiuso con successo!`);

        // Reset completo
        tavoloSelezionato = null;
        modoSelezionato   = null;
        datiConto         = null;
        document.getElementById('step-modo').style.display  = 'none';
        document.getElementById('step-conto').style.display = 'none';
        caricaTavoli();

    } catch(e) {
        alert('❌ ' + e.message);
    }
}
</script>
</body>
</html>