<?php
/**
 * camerieri/bevande.php
 * @version 4.2.0 — partecipanti come oggetti {lettera,nome}, nome lungo identico al sistema
 */
session_start();
define('ACCESS_ALLOWED', true);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!isset($_SESSION['utente_id']) || !in_array($_SESSION['utente_ruolo'], ['cameriere', 'admin'])) {
    header('Location: /ristorantemoka/login.php');
    exit;
}

require_once('../config/config.php');
$conn = getDbConnection();
if (!$conn) die("Errore di connessione al database");
$utente_nome = $_SESSION['utente_nome'];
$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Bevande — RistoranteMoka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --viola:    #5b4fcf;
            --viola-dk: #3d3490;
            --viola-lt: #ede9ff;
            --gold:     #f59e0b;
            --green:    #10b981;
            --blue:     #3b82f6;
            --gray-bg:  #f1f5f9;
        }
        * { box-sizing: border-box; }
        body { background: var(--gray-bg); font-family: 'Segoe UI', system-ui, sans-serif; color: #1e293b; min-height: 100vh; padding-bottom: 100px; }

        .topbar { background: linear-gradient(135deg, var(--viola-dk), var(--viola)); color: white; padding: 14px 16px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 12px rgba(0,0,0,0.2); }
        .topbar h1 { font-size: 1.1rem; font-weight: 700; margin: 0; }
        .topbar small { opacity: 0.8; font-size: 0.78rem; }

        .filtri-bar { background: white; padding: 10px 16px; border-bottom: 1px solid #e2e8f0; display: flex; gap: 8px; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
        .filtri-bar::-webkit-scrollbar { display: none; }
        .filtro-btn { border: 2px solid #e2e8f0; background: white; border-radius: 20px; padding: 5px 14px; font-size: 0.82rem; font-weight: 600; white-space: nowrap; cursor: pointer; transition: all 0.2s; color: #64748b; }
        .filtro-btn.active { background: var(--viola); border-color: var(--viola); color: white; }
        .badge-count { background: #e2e8f0; color: #1e293b; border-radius: 10px; padding: 1px 6px; font-size: 0.72rem; margin-left: 4px; }
        .filtro-btn.active .badge-count { background: rgba(255,255,255,0.3); color: white; }

        .sommario { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; padding: 12px 16px; }
        .s-card { background: white; border-radius: 12px; padding: 10px 6px; text-align: center; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .s-card .num { font-size: 1.5rem; font-weight: 800; line-height: 1; }
        .s-card .lbl { font-size: 0.63rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; margin-top: 3px; }
        .s-attesa .num  { color: var(--gold); }
        .s-prep .num    { color: var(--blue); }
        .s-pronta .num  { color: var(--green); }
        .s-servita .num { color: #94a3b8; }

        .tavolo-section { margin: 0 12px 16px; }
        .tavolo-header { display: flex; align-items: center; gap: 10px; padding: 9px 14px; background: var(--viola-lt); border-radius: 10px 10px 0 0; border-left: 4px solid var(--viola); }
        .tavolo-num { font-weight: 800; font-size: 1rem; color: var(--viola-dk); }
        .tavolo-info { font-size: 0.78rem; color: #64748b; }

        .bev-card { background: white; border-left: 4px solid #e2e8f0; padding: 12px 14px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 12px; }
        .bev-card:last-child { border-radius: 0 0 10px 10px; border-bottom: none; }
        .bev-card.s0 { border-left-color: var(--gold); }
        .bev-card.s1 { border-left-color: var(--blue); }
        .bev-card.s2 { border-left-color: var(--green); }

        .bev-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .s0 .bev-icon { background: #fef3c7; }
        .s1 .bev-icon { background: #dbeafe; }
        .s2 .bev-icon { background: #d1fae5; }

        .bev-info { flex: 1; min-width: 0; }
        .bev-nome { font-weight: 700; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bev-meta { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; margin-top: 3px; font-size: 0.75rem; color: #64748b; }
        .tag-cliente { background: var(--viola-lt); color: var(--viola-dk); border-radius: 6px; padding: 1px 7px; font-weight: 700; font-size: 0.72rem; }
        .tag-condivisa { background: #f0fdf4; color: #166534; border-radius: 6px; padding: 1px 7px; font-size: 0.72rem; }
        .stato-pill { font-size: 0.7rem; font-weight: 700; border-radius: 12px; padding: 3px 9px; white-space: nowrap; }
        .pill-0 { background: #fef3c7; color: #92400e; }
        .pill-1 { background: #dbeafe; color: #1e40af; }
        .pill-2 { background: #d1fae5; color: #065f46; }

        .bev-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; flex-shrink: 0; }
        .btn-az { border: none; border-radius: 8px; padding: 6px 11px; font-size: 0.74rem; font-weight: 700; cursor: pointer; transition: opacity 0.15s; white-space: nowrap; }
        .btn-az:active { opacity: 0.7; }
        .btn-az:disabled { opacity: 0.35; pointer-events: none; }
        .az-avanza { background: #dbeafe; color: #1e40af; }
        .az-undo   { background: #fef3c7; color: #92400e; }
        .az-storna { background: #fee2e2; color: #991b1b; }

        .fab-area { position: fixed; bottom: 22px; right: 16px; display: flex; flex-direction: column; align-items: flex-end; gap: 10px; z-index: 200; }
        .fab { width: 52px; height: 52px; border-radius: 50%; border: none; color: white; font-size: 1.3rem; box-shadow: 0 4px 14px rgba(0,0,0,0.25); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform 0.15s; }
        .fab:active { transform: scale(0.9); }
        .fab-add     { background: var(--viola); }
        .fab-archive { background: #475569; }

        .empty-state { text-align: center; padding: 60px 20px; color: #64748b; }
        .empty-state i { font-size: 3rem; opacity: 0.25; margin-bottom: 14px; display: block; }
        .loader-wrap { text-align: center; padding: 60px 20px; }

        .modal-content { border-radius: 16px; }
        .modal-header { border-bottom: none; padding-bottom: 0; }
        .modal-footer { border-top: none; }

        .prezzo-totale-box { background: var(--viola-lt); border-radius: 10px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; }
        .prezzo-totale-box .lbl { font-size: 0.82rem; color: var(--viola-dk); font-weight: 600; }
        .prezzo-totale-box .val { font-size: 1.1rem; font-weight: 800; color: var(--viola-dk); }

        .partecipante-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; border-radius: 8px; margin-bottom: 4px; background: #f8fafc; }
        .partecipante-row.is-ordinante { background: var(--viola-lt); }
        .badge-ord { font-size: 0.65rem; background: var(--viola); color: white; border-radius: 10px; padding: 2px 7px; }

        .toast-container { z-index: 9999; }

        .overlay-archivio { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 300; }
        .overlay-archivio.open { display: block; }
        .bottom-sheet { position: fixed; bottom: 0; left: 0; right: 0; background: #1e293b; border-radius: 20px 20px 0 0; z-index: 301; max-height: 75vh; transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.32,0.72,0,1); display: flex; flex-direction: column; }
        .bottom-sheet.open { transform: translateY(0); }
        .sheet-handle { width: 40px; height: 4px; background: rgba(255,255,255,0.25); border-radius: 2px; margin: 12px auto 4px; flex-shrink: 0; }
        .sheet-header { padding: 8px 20px 14px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sheet-header h5 { color: white; margin: 0; font-weight: 700; font-size: 1rem; }
        .sheet-body { overflow-y: auto; padding: 14px 16px 24px; flex: 1; }
        .arch-card { background: rgba(255,255,255,0.07); border-radius: 10px; padding: 10px 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        .arch-info { flex: 1; min-width: 0; }
        .arch-nome { font-weight: 700; font-size: 0.9rem; color: white; }
        .arch-meta { font-size: 0.75rem; color: rgba(255,255,255,0.55); margin-top: 2px; }
        .btn-undo-arch { background: #f59e0b; color: #1e293b; border: none; border-radius: 8px; padding: 6px 11px; font-size: 0.78rem; font-weight: 700; cursor: pointer; white-space: nowrap; flex-shrink: 0; }
        .btn-undo-arch:active { opacity: 0.7; }

        @media (min-width: 640px) {
            .bev-card { padding: 14px 18px; }
            .btn-az { padding: 7px 14px; font-size: 0.8rem; }
            .bottom-sheet { max-width: 540px; left: 50%; right: auto; transform: translateX(-50%) translateY(100%); }
            .bottom-sheet.open { transform: translateX(-50%) translateY(0); }
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-wine-glass-alt me-2"></i>Bevande</h1>
            <small><?php echo htmlspecialchars($utente_nome); ?></small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-light" onclick="caricaBevande()" id="btn-refresh"><i class="fas fa-sync-alt"></i></button>
            <a href="dashboard.php" class="btn btn-sm btn-outline-light"><i class="fas fa-home"></i></a>
        </div>
    </div>
</div>

<div class="filtri-bar">
    <button class="filtro-btn active" onclick="setFiltro(this,'')">Tutti <span class="badge-count" id="cnt-tutti">0</span></button>
    <button class="filtro-btn" onclick="setFiltro(this,'0')">⏳ Attesa <span class="badge-count" id="cnt-0">0</span></button>
    <button class="filtro-btn" onclick="setFiltro(this,'1')">🔄 In prep. <span class="badge-count" id="cnt-1">0</span></button>
    <button class="filtro-btn" onclick="setFiltro(this,'2')">✅ Pronte <span class="badge-count" id="cnt-2">0</span></button>
</div>

<div class="sommario">
    <div class="s-card s-attesa"><div class="num" id="sum-0">0</div><div class="lbl">Attesa</div></div>
    <div class="s-card s-prep"><div class="num" id="sum-1">0</div><div class="lbl">In prep.</div></div>
    <div class="s-card s-pronta"><div class="num" id="sum-2">0</div><div class="lbl">Pronte</div></div>
    <div class="s-card s-servita"><div class="num" id="sum-3">0</div><div class="lbl">Servite</div></div>
</div>

<div id="bevande-container">
    <div class="loader-wrap">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="text-muted small mt-3">Caricamento bevande...</p>
    </div>
</div>

<div class="fab-area">
    <button class="fab fab-archive" onclick="apriArchivio()" title="Archivio servite"><i class="fas fa-history"></i></button>
    <button class="fab fab-add" onclick="apriAggiungi()" title="Aggiungi bevanda"><i class="fas fa-plus"></i></button>
</div>

<div class="overlay-archivio" id="overlay-archivio" onclick="chiudiArchivio()"></div>
<div class="bottom-sheet" id="bottom-sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-header">
        <h5><i class="fas fa-history me-2"></i>Bevande Servite</h5>
        <button class="btn btn-sm btn-outline-light" onclick="chiudiArchivio()">Chiudi</button>
    </div>
    <div class="sheet-body" id="archivio-body">
        <p class="text-white-50 text-center small mt-3">Nessuna bevanda servita.</p>
    </div>
</div>

<!-- MODAL AGGIUNGI -->
<div class="modal fade" id="modalAggiungi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-wine-bottle me-2 text-primary"></i>Aggiungi Bevanda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Tavolo</label>
                    <select class="form-select" id="add-tavolo">
                        <option value="">Seleziona tavolo...</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Cliente che ordina</label>
                    <select class="form-select" id="add-cliente" disabled onchange="onClienteChange()">
                        <option value="">Prima seleziona un tavolo</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Bevanda</label>
                    <select class="form-select" id="add-bevanda" onchange="aggiornaPrezzoTotale()">
                        <option value="">Caricamento...</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Quantità</label>
                    <div class="d-flex align-items-center gap-3">
                        <div class="input-group" style="max-width:140px">
                            <button class="btn btn-outline-secondary" onclick="cambiaQta(-1)" type="button">−</button>
                            <input type="number" class="form-control text-center fw-bold" id="add-qta" value="1" min="1" max="20" oninput="aggiornaPrezzoTotale()">
                            <button class="btn btn-outline-secondary" onclick="cambiaQta(1)" type="button">+</button>
                        </div>
                        <div class="prezzo-totale-box flex-fill">
                            <span class="lbl">Totale</span>
                            <span class="val" id="prezzo-totale">€ 0.00</span>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Condivisione</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="cond-modal" id="cond-personale" value="personale" checked onchange="onCondivisioneChange()">
                        <label class="form-check-label" for="cond-personale"><i class="fas fa-user me-1 text-muted"></i>Solo per questo cliente</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="cond-modal" id="cond-tavolo" value="tavolo" onchange="onCondivisioneChange()">
                        <label class="form-check-label" for="cond-tavolo"><i class="fas fa-users me-1 text-muted"></i>Divisa per tutto il tavolo</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="cond-modal" id="cond-parziale" value="parziale" onchange="onCondivisioneChange()">
                        <label class="form-check-label" for="cond-parziale"><i class="fas fa-user-friends me-1 text-muted"></i>Scegli chi partecipa</label>
                    </div>
                </div>

                <div class="mb-2" id="selettore-partecipanti" style="display:none;">
                    <label class="form-label fw-semibold">Chi partecipa alla spesa?</label>
                    <div id="checkbox-partecipanti"
                         class="border rounded p-2"
                         style="max-height:200px; overflow-y:auto; background:#f8fafc;">
                        <p class="text-muted small mb-0">Seleziona prima tavolo e cliente</p>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button class="btn btn-primary fw-bold" onclick="aggiungiBevanda()">
                    <i class="fas fa-plus me-1"></i>Aggiungi
                </button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3">
    <div id="myToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="toast-text"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';

let filtroAttivo     = '';
let azioneInCorso    = false;
let cacheAttive      = [];
let cacheArchivio    = [];
let listaBevande     = [];
// clientiTavolo: { "A": {lettera:"A", nome:"Mario"}, "B": {...} }
let clientiTavolo    = {};
let clienteOrdinante = '';
let modalEl, toastEl, toastBody;

const LABEL = ['In attesa','In preparazione','Pronta','Servita'];
const ICON  = ['⏳','🔄','✅','🍽️'];

document.addEventListener('DOMContentLoaded', () => {
    modalEl   = new bootstrap.Modal(document.getElementById('modalAggiungi'));
    toastEl   = new bootstrap.Toast(document.getElementById('myToast'), { delay: 2500 });
    toastBody = document.getElementById('toast-text');
    document.getElementById('add-tavolo').addEventListener('change', onTavoloChange);
    caricaListaBevande();
    caricaBevande();
    setInterval(() => { if (!azioneInCorso) caricaBevande(true); }, 10000);
});

// ── LISTA BEVANDE ──────────────────────────────────────────
async function caricaListaBevande() {
    try {
        const r = await fetch('../api/bevande/get-lista-bevande.php');
        const d = await r.json();
        if (!d.success) return;
        listaBevande = d.bevande || [];
        popolaSelectBevande();
    } catch(e) { console.error('Errore lista bevande:', e); }
}

function popolaSelectBevande() {
    const sel = document.getElementById('add-bevanda');
    sel.innerHTML = '<option value="">Seleziona bevanda...</option>';
    listaBevande.forEach(b => {
        sel.innerHTML += `<option value="${b.id}" data-prezzo="${b.prezzo}" data-nome="${b.nome}">
            ${b.nome} — €${parseFloat(b.prezzo).toFixed(2)}
        </option>`;
    });
    aggiornaPrezzoTotale();
}

function aggiornaPrezzoTotale() {
    const sel    = document.getElementById('add-bevanda');
    const qta    = parseInt(document.getElementById('add-qta').value) || 1;
    const opt    = sel.options[sel.selectedIndex];
    const prezzo = opt ? parseFloat(opt.getAttribute('data-prezzo') || 0) : 0;
    document.getElementById('prezzo-totale').textContent = '€ ' + (prezzo * qta).toFixed(2);
}

// ── CARICA BEVANDE ─────────────────────────────────────────
async function caricaBevande(silenzioso = false) {
    if (!silenzioso) document.getElementById('btn-refresh').innerHTML = '<i class="fas fa-sync-alt fa-spin"></i>';
    try {
        const r = await fetch('../api/bevande/get-bevande.php?t=' + Date.now());
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const d = await r.json();
        if (!d.success) throw new Error(d.error || 'Errore API');
        cacheAttive   = (d.bevande || []).filter(b => b.stato < 3);
        cacheArchivio = (d.bevande || []).filter(b => b.stato === 3);
        aggiornaSommario(d.counts || {});
        renderBevande();
        renderArchivio();
        popolaTavoliModal(d.tavoli_attivi || []);
    } catch(e) {
        if (!silenzioso) mostraErrore(e.message);
    } finally {
        document.getElementById('btn-refresh').innerHTML = '<i class="fas fa-sync-alt"></i>';
    }
}

function aggiornaSommario(c) {
    const tot = [0,1,2].reduce((a,k) => a + parseInt(c[k]||0), 0);
    [0,1,2,3].forEach(k => {
        document.getElementById('sum-' + k).textContent = c[k] || 0;
        if (k < 3) document.getElementById('cnt-' + k).textContent = c[k] || 0;
    });
    document.getElementById('cnt-tutti').textContent = tot;
}

function setFiltro(btn, val) {
    document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    filtroAttivo = val;
    renderBevande();
}

function renderBevande() {
    const container = document.getElementById('bevande-container');
    const lista = filtroAttivo === '' ? cacheAttive : cacheAttive.filter(b => String(b.stato) === filtroAttivo);

    if (!lista.length) {
        container.innerHTML = `<div class="empty-state"><i class="fas fa-wine-glass-alt"></i>
            <p class="fw-bold mb-1">Nessuna bevanda</p>
            <p class="small">${filtroAttivo === '' ? 'Nessuna bevanda attiva.' : 'Nessuna bevanda in questo stato.'}</p></div>`;
        return;
    }

    const gruppi = {};
    lista.forEach(b => {
        if (!gruppi[b.tavolo_id]) gruppi[b.tavolo_id] = { numero: b.tavolo_numero, items: [] };
        gruppi[b.tavolo_id].items.push(b);
    });

    let html = '';
    Object.values(gruppi).forEach(g => {
        html += `<div class="tavolo-section"><div class="tavolo-header">
            <span class="tavolo-num">Tavolo ${g.numero}</span>
            <span class="tavolo-info">${g.items.length} bevanda${g.items.length !== 1 ? 'e' : ''}</span>
        </div>`;

        g.items.forEach(b => {
            const s = parseInt(b.stato);
            let condHtml = '';
            if (b.condivisione !== 'personale' && b.partecipanti && b.partecipanti.length) {
                const nomi = b.partecipanti.map(p =>
                    typeof p === 'string' ? p : (p.nome ? `${p.nome} (${p.lettera})` : `Cliente ${p.lettera}`)
                ).join(', ');
                condHtml = `<span class="tag-condivisa">👥 ${nomi}</span>`;
            }
            const azUndo   = s > 0 ? `<button class="btn-az az-undo" onclick="cambiaStato(${b.id},${s-1},this)"><i class="fas fa-undo"></i></button>` : '';
            let azAvanza   = '';
            if (s === 0) azAvanza = `<button class="btn-az az-avanza" onclick="cambiaStato(${b.id},1,this)">🔄 In prep.</button>`;
            if (s === 1) azAvanza = `<button class="btn-az az-avanza" onclick="cambiaStato(${b.id},2,this)">✅ Pronta</button>`;
            if (s === 2) azAvanza = `<button class="btn-az az-avanza" onclick="cambiaStato(${b.id},3,this)">🍽️ Servita</button>`;
            const azStorna = `<button class="btn-az az-storna" onclick="storna(${b.id},this)">✕</button>`;

            html += `<div class="bev-card s${s}" id="bev-${b.id}">
                <div class="bev-icon">${ICON[s]}</div>
                <div class="bev-info">
                    <div class="bev-nome">${b.nome}</div>
                    <div class="bev-meta">
                        <span class="tag-cliente">Cliente ${b.cliente_lettera}${b.cliente_nome ? ' · ' + b.cliente_nome : ''}</span>
                        <span>×${b.quantita}</span>
                        <span>${b.ora}</span>
                        ${condHtml}
                    </div>
                </div>
                <div class="bev-actions">
                    <span class="stato-pill pill-${s}">${LABEL[s]}</span>
                    <div class="d-flex gap-1 mt-1">${azUndo}${azAvanza}${azStorna}</div>
                </div>
            </div>`;
        });
        html += `</div>`;
    });
    container.innerHTML = html;
}

function renderArchivio() {
    const body = document.getElementById('archivio-body');
    if (!cacheArchivio.length) {
        body.innerHTML = '<p class="text-white-50 text-center small mt-3">Nessuna bevanda servita.</p>';
        return;
    }
    let html = '';
    [...cacheArchivio].reverse().forEach(b => {
        html += `<div class="arch-card">
            <div class="arch-info">
                <div class="arch-nome">${b.nome} ×${b.quantita}</div>
                <div class="arch-meta">Tavolo ${b.tavolo_numero} · Cliente ${b.cliente_lettera}${b.cliente_nome ? ' · ' + b.cliente_nome : ''} · ${b.ora}</div>
            </div>
            <button class="btn-undo-arch" onclick="cambiaStato(${b.id},2,this)"><i class="fas fa-undo me-1"></i>Undo</button>
        </div>`;
    });
    body.innerHTML = html;
}

function mostraErrore(msg) {
    document.getElementById('bevande-container').innerHTML = `<div class="empty-state">
        <i class="fas fa-exclamation-triangle text-danger"></i>
        <p class="fw-bold">Errore caricamento</p>
        <p class="small text-muted">${msg}</p>
        <button class="btn btn-primary btn-sm mt-2" onclick="caricaBevande()">Riprova</button>
    </div>`;
}

function apriArchivio() {
    document.getElementById('overlay-archivio').classList.add('open');
    document.getElementById('bottom-sheet').classList.add('open');
}
function chiudiArchivio() {
    document.getElementById('overlay-archivio').classList.remove('open');
    document.getElementById('bottom-sheet').classList.remove('open');
}

async function cambiaStato(id, nuovoStato, btn) {
    if (azioneInCorso) return;
    azioneInCorso = true;
    btn.disabled = true;
    try {
        await api({ riga_id: id, azione: 'stato', nuovo_stato: nuovoStato });
        toast('✅ Stato aggiornato', 'bg-success');
        await caricaBevande(true);
    } catch(e) {
        toast('❌ ' + e.message, 'bg-danger');
        btn.disabled = false;
    } finally { azioneInCorso = false; }
}

async function storna(id, btn) {
    if (!confirm('Stornare questa bevanda?')) return;
    if (azioneInCorso) return;
    azioneInCorso = true;
    btn.disabled = true;
    try {
        await api({ riga_id: id, azione: 'storna' });
        toast('🗑️ Bevanda stornata', 'bg-warning text-dark');
        await caricaBevande(true);
    } catch(e) {
        toast('❌ ' + e.message, 'bg-danger');
        btn.disabled = false;
    } finally { azioneInCorso = false; }
}

// ── MODAL ─────────────────────────────────────────────────
function apriAggiungi() {
    document.getElementById('add-qta').value    = 1;
    document.getElementById('add-tavolo').value = '';
    document.getElementById('add-cliente').innerHTML = '<option value="">Prima seleziona un tavolo</option>';
    document.getElementById('add-cliente').disabled  = true;
    document.getElementById('cond-personale').checked = true;
    document.getElementById('selettore-partecipanti').style.display = 'none';
    document.getElementById('checkbox-partecipanti').innerHTML = '<p class="text-muted small mb-0">Seleziona prima tavolo e cliente</p>';
    aggiornaPrezzoTotale();
    clientiTavolo    = {};
    clienteOrdinante = '';
    modalEl.show();
}

function cambiaQta(d) {
    const el = document.getElementById('add-qta');
    el.value = Math.max(1, Math.min(20, parseInt(el.value || 1) + d));
    aggiornaPrezzoTotale();
}

function popolaTavoliModal(tavoli) {
    const sel = document.getElementById('add-tavolo');
    const cur = sel.value;
    sel.innerHTML = '<option value="">Seleziona tavolo...</option>';
    tavoli.forEach(t => { sel.innerHTML += `<option value="${t.id}">Tavolo ${t.numero}</option>`; });
    if (cur) sel.value = cur;
}

async function onTavoloChange() {
    const tavoloId = document.getElementById('add-tavolo').value;
    const selCl    = document.getElementById('add-cliente');
    clientiTavolo    = {};
    clienteOrdinante = '';
    document.getElementById('checkbox-partecipanti').innerHTML = '<p class="text-muted small mb-0">Seleziona prima tavolo e cliente</p>';

    if (!tavoloId) {
        selCl.innerHTML = '<option value="">Prima seleziona un tavolo</option>';
        selCl.disabled  = true;
        return;
    }

    selCl.innerHTML = '<option>Caricamento...</option>';
    selCl.disabled  = true;

    try {
        const r = await fetch(`../api/clienti/get-clienti-registrati.php?tavolo_id=${tavoloId}`);
        const d = await r.json();
        // La risposta è un oggetto { "A": {lettera, nome, ...}, "B": {...} }
        const obj = d.clienti || {};
        const arr = typeof obj === 'object' && !Array.isArray(obj) ? Object.values(obj) : obj;

        if (arr.length) {
            selCl.innerHTML = '<option value="">Seleziona cliente...</option>';
            arr.forEach(c => {
                const lettera = c.lettera || c.identificativo || '';
                const nome    = c.nome && c.nome !== 'Cliente ' + lettera ? c.nome : '';
                const label   = nome ? `${lettera} – ${nome}` : `Cliente ${lettera}`;
                selCl.innerHTML += `<option value="${lettera}">${label}</option>`;
                // Salva con nome reale per costruire nome lungo nel server
                clientiTavolo[lettera] = { lettera, nome: nome || 'Cliente ' + lettera };
            });
            selCl.disabled = false;
        } else {
            selCl.innerHTML = '<option value="">Nessun cliente al tavolo</option>';
        }
    } catch(e) {
        selCl.innerHTML = '<option value="">Errore caricamento</option>';
    }
}

function onClienteChange() {
    clienteOrdinante = document.getElementById('add-cliente').value;
    if (clienteOrdinante) popolaCheckboxPartecipanti();
}

function popolaCheckboxPartecipanti() {
    const container = document.getElementById('checkbox-partecipanti');
    if (!Object.keys(clientiTavolo).length || !clienteOrdinante) {
        container.innerHTML = '<p class="text-muted small mb-0">Seleziona prima tavolo e cliente</p>';
        return;
    }

    let html = '';
    Object.values(clientiTavolo).forEach(c => {
        const isOrdinante = c.lettera === clienteOrdinante;
        const label = c.nome !== 'Cliente ' + c.lettera ? `Cliente ${c.lettera} – ${c.nome}` : `Cliente ${c.lettera}`;
        html += `<div class="partecipante-row ${isOrdinante ? 'is-ordinante' : ''}">
            <div class="d-flex align-items-center gap-2">
                <input class="form-check-input partecipante-cb" type="checkbox"
                       value="${c.lettera}" id="cb-${c.lettera}"
                       ${isOrdinante ? 'checked disabled' : ''}
                       style="margin:0;">
                <label class="form-check-label mb-0" for="cb-${c.lettera}">${label}</label>
            </div>
            ${isOrdinante ? '<span class="badge-ord">ordina</span>' : ''}
        </div>`;
    });
    container.innerHTML = html;
}

function onCondivisioneChange() {
    const val = document.querySelector('input[name="cond-modal"]:checked').value;
    document.getElementById('selettore-partecipanti').style.display = val === 'parziale' ? 'block' : 'none';
    if (val === 'parziale' && clienteOrdinante) popolaCheckboxPartecipanti();
}

async function aggiungiBevanda() {
    const tavoloId = document.getElementById('add-tavolo').value;
    const cliente  = document.getElementById('add-cliente').value;
    const selBev   = document.getElementById('add-bevanda');
    const bevOpt   = selBev.options[selBev.selectedIndex];
    const bevandaId  = selBev.value;
    const nomeBev    = bevOpt ? bevOpt.getAttribute('data-nome') : '';
    const prezzo     = bevOpt ? parseFloat(bevOpt.getAttribute('data-prezzo') || 0) : 0;
    const qta        = parseInt(document.getElementById('add-qta').value) || 1;
    const condivisione = document.querySelector('input[name="cond-modal"]:checked').value;

    if (!tavoloId || !cliente || !bevandaId) {
        toast('⚠️ Compila tutti i campi', 'bg-warning text-dark');
        return;
    }

    // ── Costruisce partecipanti come array di oggetti {lettera, nome}
    // Il server li userà per costruire il nome lungo identico al sistema esistente
    let partecipanti = [];

    if (condivisione === 'personale') {
        const c = clientiTavolo[cliente];
        partecipanti = [{ lettera: cliente, nome: c ? c.nome : 'Cliente ' + cliente }];

    } else if (condivisione === 'tavolo') {
        partecipanti = Object.values(clientiTavolo).map(c => ({ lettera: c.lettera, nome: c.nome }));
        if (!partecipanti.length) {
            partecipanti = [{ lettera: cliente, nome: 'Cliente ' + cliente }];
        }

    } else {
        // parziale: ordinante sempre incluso (disabled checkbox)
        const c = clientiTavolo[cliente];
        partecipanti = [{ lettera: cliente, nome: c ? c.nome : 'Cliente ' + cliente }];
        document.querySelectorAll('.partecipante-cb:checked:not(:disabled)').forEach(cb => {
            const lettera = cb.value;
            if (lettera !== cliente) {
                const cl = clientiTavolo[lettera];
                partecipanti.push({ lettera, nome: cl ? cl.nome : 'Cliente ' + lettera });
            }
        });
        if (partecipanti.length < 1) {
            toast('⚠️ Seleziona almeno un partecipante', 'bg-warning text-dark');
            return;
        }
    }

    try {
        const risultato = await api({
            azione:          'aggiungi',
            tavolo_id:       parseInt(tavoloId),
            cliente_lettera: cliente,
            piatto_id:       parseInt(bevandaId),
            nome:            nomeBev,
            prezzo_unitario: prezzo,
            quantita:        qta,
            condivisione:    condivisione,
            partecipanti:    partecipanti   // array di oggetti {lettera, nome}
        });

        modalEl.hide();
        if (risultato.duplicato) {
            toast('ℹ️ Bevanda già presente nell\'ordine', 'bg-info');
        } else {
            toast('✅ Bevanda aggiunta', 'bg-success');
        }
        await caricaBevande(true);
    } catch(e) { toast('❌ ' + e.message, 'bg-danger'); }
}

async function api(body) {
    const r = await fetch('../api/bevande/aggiorna-bevanda.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(body)
    });
    const d = await r.json();
    if (!d.success) throw new Error(d.error || 'Errore sconosciuto');
    return d;
}

function toast(msg, cls = 'bg-success') {
    document.getElementById('myToast').className = `toast align-items-center text-white border-0 ${cls}`;
    toastBody.textContent = msg;
    toastEl.show();
}
</script>
</body>
</html>