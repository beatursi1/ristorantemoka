<?php
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

// Controlla se esistono giornate di chiusura reali (per mostrare/nascondere reset totale)
$conn = getDbConnection();
$haGiornateReali = false;
$ultimaChiusura = null;
$totaleOrdiniAttivi = 0;
$totaleOrdiniArchivio = 0;

if ($conn) {
    $r = $conn->query("SELECT COUNT(*) as tot FROM giornate_chiusura");
    if ($r) $haGiornateReali = ($r->fetch_assoc()['tot'] > 0);

    $r = $conn->query("SELECT MAX(data_chiusura) as ultima FROM giornate_chiusura");
    if ($r) $ultimaChiusura = $r->fetch_assoc()['ultima'];

    $r = $conn->query("SELECT COUNT(*) as tot FROM ordini_tavolo");
    if ($r) $totaleOrdiniAttivi = (int)$r->fetch_assoc()['tot'];

    $r = $conn->query("SELECT COUNT(*) as tot FROM ordini_tavolo_archivio");
    if ($r) $totaleOrdiniArchivio = (int)$r->fetch_assoc()['tot'];

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manutenzione DB - RistoranteMoka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2c3e50; --accent: #3498db; }
        body { background: #f8f9fa; font-family: 'Segoe UI', system-ui, sans-serif; }

        .topbar {
            background: var(--primary);
            color: white;
            padding: 14px 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .topbar h1 { font-size: 1.1rem; font-weight: 700; margin: 0; }
        .topbar small { opacity: 0.75; font-size: 0.78rem; }

        .nav-mobile {
            background: #34495e;
            display: flex;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            padding: 0 8px;
        }
        .nav-mobile::-webkit-scrollbar { display: none; }
        .nav-mobile a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            white-space: nowrap;
            padding: 10px 14px;
            font-size: 0.82rem;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .nav-mobile a:hover, .nav-mobile a.active { color: white; border-bottom-color: white; }

        .sidebar {
            background: var(--primary);
            min-height: 100vh;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 0 10px 5px;
            display: block;
            text-decoration: none;
            transition: 0.2s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active { color: white; background: rgba(255,255,255,0.1); }
        .sidebar .nav-link.active { background: var(--accent); font-weight: 600; }

        .section-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .section-header {
            padding: 18px 24px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .section-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .section-body { padding: 24px; }

        .stat-inline {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        .stat-inline strong { font-size: 1.3rem; }

        .zona-pericolo {
            border: 2px solid #e74c3c;
            border-radius: 16px;
        }
        .zona-pericolo .section-header {
            background: #fdf2f2;
            border-bottom-color: #fad7d7;
        }

        #log-operazione {
            background: #1e1e1e;
            color: #a8ff78;
            border-radius: 10px;
            padding: 16px;
            font-family: 'Courier New', monospace;
            font-size: 0.82rem;
            min-height: 120px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        #log-operazione.visible { display: block; }
        .log-line { margin: 2px 0; }
        .log-ok   { color: #a8ff78; }
        .log-err  { color: #ff6b6b; }
        .log-info { color: #74c0fc; }

        /* Touch-friendly buttons */
        .btn-operazione {
            min-height: 48px;
            font-weight: 600;
            border-radius: 10px;
            width: 100%;
        }
        @media (min-width: 768px) {
            .btn-operazione { width: auto; min-width: 200px; }
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-database me-2 text-warning"></i>Manutenzione DB</h1>
            <small>Admin: <?php echo htmlspecialchars($utente_nome); ?></small>
        </div>
        <a href="/ristorantemoka/logout.php" class="btn btn-sm btn-outline-light">
            <i class="fas fa-sign-out-alt me-1"></i>Esci
        </a>
    </div>
</div>

<nav class="nav-mobile d-md-none">
    <a href="dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
    <a href="tavoli.php"><i class="fas fa-table"></i>Tavoli</a>
    <a href="../app/admin/admin-menu.html"><i class="fas fa-book-open"></i>Menù</a>
    <a href="utenti.php"><i class="fas fa-users-cog"></i>Utenti</a>
    <a href="manutenzione.php" class="active"><i class="fas fa-database"></i>Manutenzione</a>
</nav>

<div class="container-fluid">
<div class="row">

    <nav class="col-md-2 d-none d-md-block sidebar">
        <div class="text-center mb-4">
            <i class="fas fa-utensils fa-2x text-info mb-2"></i>
            <h6 class="text-white fw-bold mb-0">Moka Admin</h6>
            <hr class="mx-3 opacity-25">
        </div>
        <ul class="nav flex-column">
            <li><a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
            <li><a class="nav-link" href="tavoli.php"><i class="fas fa-table me-2"></i>Gestione Tavoli</a></li>
            <li><a class="nav-link" href="../app/admin/admin-menu.html"><i class="fas fa-book-open me-2"></i>Gestione Menù</a></li>
            <li><a class="nav-link" href="utenti.php"><i class="fas fa-users-cog me-2"></i>Gestione Utenti</a></li>
            <li><a class="nav-link active" href="manutenzione.php"><i class="fas fa-database me-2"></i>Manutenzione</a></li>
        </ul>
    </nav>

    <main class="col-md-10 px-3 px-md-4 py-4">

        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1"><i class="fas fa-database me-2 text-warning"></i>Manutenzione Database</h2>
            <p class="text-muted small mb-0">Gestisci l'archiviazione degli ordini e la pulizia dei dati storici.</p>
        </div>

        <!-- STATISTICHE RAPIDE -->

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-inline text-center">
                    <div class="text-muted small mb-1">Ordini attivi</div>
                    <strong class="text-primary"><?php echo $totaleOrdiniAttivi; ?></strong>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-inline text-center">
                    <div class="text-muted small mb-1">In archivio</div>
                    <strong class="text-secondary"><?php echo $totaleOrdiniArchivio; ?></strong>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-inline text-center">
                    <div class="text-muted small mb-1">Ultima chiusura</div>
                    <strong class="text-success" style="font-size:1rem;">
                        <?php echo $ultimaChiusura ? date('d/m/Y', strtotime($ultimaChiusura)) : '—'; ?>
                    </strong>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-inline text-center">
                    <div class="text-muted small mb-1">Giornate chiuse</div>
                    <strong class="text-info"><?php echo $haGiornateReali ? 'Sì' : 'Nessuna'; ?></strong>
                </div>
            </div>
        </div>

        <!-- LOG OPERAZIONE (condiviso tra tutte le operazioni) -->
        <div id="log-operazione" class="mb-4"></div>

        <!-- ============================================================ -->
        <!-- SEZIONE 1: CHIUSURA GIORNALIERA                              -->
        <!-- ============================================================ -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-icon" style="background:#e8f5e9; color:#27ae60;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">Chiusura Giornaliera</h5>
                    <small class="text-muted">Archivia gli ordini del giorno e prepara il sistema per domani</small>
                </div>
            </div>
            <div class="section-body">
                <p class="text-muted small mb-3">
                    Sposta tutti gli ordini completati nelle tabelle archivio, registra l'incasso della giornata 
                    in <code>giornate_chiusura</code> e libera le sessioni chiuse. 
                    I dati non vengono eliminati — sono recuperabili dall'archivio.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-success btn-operazione" onClick="eseguiOperazione('chiusura_giornaliera')">
                        <i class="fas fa-calendar-check me-2"></i>Esegui Chiusura Giornaliera
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- SEZIONE 2: PULIZIA ARCHIVIO                                  -->
        <!-- ============================================================ -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-icon" style="background:#fff3e0; color:#e67e22;">
                    <i class="fas fa-broom"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">Pulizia Archivio</h5>
                    <small class="text-muted">Elimina definitivamente i dati archiviati più vecchi di N giorni</small>
                </div>
            </div>
            <div class="section-body">
                <p class="text-muted small mb-3">
                    Rimuove in modo permanente i record dall'archivio (non dalle tabelle attive). 
                    Utile per liberare spazio dopo aver tenuto i dati per il periodo fiscale necessario.
                    <strong class="text-danger">Operazione irreversibile.</strong>
                </p>
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Elimina dati archiviati prima di:</label>
                        <input type="date" id="data-pulizia" class="form-control"
                               max="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        <div class="form-text">Default: 30 giorni fa</div>
                    </div>
                </div>
                <button class="btn btn-warning btn-operazione text-white" onClick="confermaEsegui('pulizia_archivio')">
                    <i class="fas fa-broom me-2"></i>Pulisci Archivio
                </button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- SEZIONE 3: RESET TOTALE (solo fase di test)                  -->
        <!-- ============================================================ -->
        <?php if (!$haGiornateReali): ?>
        <div class="section-card zona-pericolo">
            <div class="section-header">
                <div class="section-icon" style="background:#fdf2f2; color:#e74c3c;">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-danger">Reset Totale</h5>
                    <small class="text-muted">Visibile solo in fase di test — sparirà quando esiste almeno una giornata di chiusura reale</small>
                </div>
            </div>
            <div class="section-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Attenzione:</strong> Elimina tutti gli ordini, sessioni clienti e sessioni tavolo. 
                    Menu, tavoli e utenti non vengono toccati.
                </div>
                <button class="btn btn-danger btn-operazione" onClick="confermaEsegui('reset_totale')">
                    <i class="fas fa-trash-alt me-2"></i>Reset Totale Database
                </button>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const logEl = document.getElementById('log-operazione');

function logLine(msg, tipo = 'info') {
    logEl.classList.add('visible');
    const line = document.createElement('div');
    line.className = 'log-line log-' + tipo;
    const ts = new Date().toLocaleTimeString('it-IT');
    line.textContent = '[' + ts + '] ' + msg;
    logEl.appendChild(line);
    logEl.scrollTop = logEl.scrollHeight;
}

function logClear() {
    logEl.innerHTML = '';
    logEl.classList.remove('visible');
}

async function eseguiOperazione(tipo) {
    logClear();
    logLine('Avvio operazione: ' + tipo, 'info');

    const payload = { operazione: tipo };

    if (tipo === 'pulizia_archivio') {
        const data = document.getElementById('data-pulizia')?.value;
        if (!data) { logLine('Seleziona una data prima di procedere.', 'err'); return; }
        payload.data_limite = data;
        logLine('Data limite: ' + data, 'info');
    }

    // Disabilita tutti i bottoni durante l'operazione
    document.querySelectorAll('.btn-operazione').forEach(b => b.disabled = true);

    try {
        const resp = await fetch('../api/admin/manutenzione.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });

        const data = await resp.json();

        if (data.success) {
            logLine('✓ Operazione completata con successo.', 'ok');
            if (data.dettagli) {
                Object.entries(data.dettagli).forEach(([k, v]) => {
                    logLine('  ' + k + ': ' + v, 'ok');
                });
            }
            if (data.messaggio) logLine(data.messaggio, 'ok');

            // Ricarica dopo 2s per aggiornare le statistiche
            setTimeout(() => window.location.reload(), 2000);

        } else {
            logLine('✗ Errore: ' + (data.errore || 'Errore sconosciuto'), 'err');
        }

    } catch (err) {
        logLine('✗ Errore di connessione: ' + err.message, 'err');
    } finally {
        document.querySelectorAll('.btn-operazione').forEach(b => b.disabled = false);
    }
}

function confermaEsegui(tipo) {
    const messaggi = {
        pulizia_archivio: 'Stai per eliminare DEFINITIVAMENTE i dati dall\'archivio prima della data selezionata.\n\nQuesta operazione è irreversibile.\n\nConfermi?',
        reset_totale:     'RESET TOTALE — stai per eliminare TUTTI gli ordini e le sessioni.\n\nMenu, tavoli e utenti rimarranno intatti.\n\nSei assolutamente sicuro?'
    };
    if (confirm(messaggi[tipo] || 'Confermi l\'operazione?')) {
        eseguiOperazione(tipo);
    }
}
</script>
</body>
</html>