<?php
session_start();
define('ACCESS_ALLOWED', true);
require_once('../config/config.php');
require_once('../includes/auth.php');
checkAccess('admin');

$conn = getDbConnection();
$tavoli = $conn->query("SELECT * FROM tavoli ORDER BY CAST(numero AS UNSIGNED) ASC");
$utente_nome = $_SESSION['utente_nome'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Tavoli - RistoranteMoka</title>
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

        /* CARD TAVOLO — intera area cliccabile */
        .tavolo-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 20px 16px;
            text-align: center;
            cursor: default;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            height: 100%;
        }
        .tavolo-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }

        .tavolo-icon {
            font-size: 2.4rem;
            margin-bottom: 10px;
        }
        .tavolo-numero {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #2c3e50;
        }
        .tavolo-stato {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 14px;
        }
        .stato-libero  { background: #e8f5e9; color: #27ae60; }
        .stato-occupato { background: #fdecea; color: #e74c3c; }

        /* Pulsante elimina — touch friendly */
        .btn-elimina {
            width: 100%;
            min-height: 42px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 600;
            border: none;
            background: #fdecea;
            color: #e74c3c;
            transition: background 0.2s;
            touch-action: manipulation;
        }
        .btn-elimina:hover { background: #e74c3c; color: white; }

        /* Pulsante aggiungi fisso in basso su mobile */
        .fab-aggiungi {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #27ae60;
            color: white;
            border: none;
            font-size: 1.6rem;
            box-shadow: 0 4px 16px rgba(39,174,96,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 200;
            touch-action: manipulation;
            transition: transform 0.2s;
        }
        .fab-aggiungi:hover { transform: scale(1.1); }

        .page-title {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            border-left: 5px solid var(--accent);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-chair me-2 text-info"></i>Gestione Tavoli</h1>
            <small>Admin: <?php echo htmlspecialchars($utente_nome); ?></small>
        </div>
        <a href="/ristorantemoka/logout.php" class="btn btn-sm btn-outline-light">
            <i class="fas fa-sign-out-alt me-1"></i>Esci
        </a>
    </div>
</div>

<nav class="nav-mobile d-md-none">
    <a href="dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
    <a href="tavoli.php" class="active"><i class="fas fa-chair"></i>Tavoli</a>
    <a href="../app/admin/admin-menu.html"><i class="fas fa-book-open"></i>Menù</a>
    <a href="utenti.php"><i class="fas fa-users-cog"></i>Utenti</a>
    <a href="manutenzione.php"><i class="fas fa-database"></i>Manutenzione</a>
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
            <li><a class="nav-link active" href="tavoli.php"><i class="fas fa-chair me-2"></i>Gestione Tavoli</a></li>
            <li><a class="nav-link" href="../app/admin/admin-menu.html"><i class="fas fa-book-open me-2"></i>Gestione Menù</a></li>
            <li><a class="nav-link" href="utenti.php"><i class="fas fa-users-cog me-2"></i>Gestione Utenti</a></li>
            <li><a class="nav-link" href="manutenzione.php"><i class="fas fa-database me-2"></i>Manutenzione DB</a></li>
        </ul>
    </nav>

    <main class="col-md-10 px-3 px-md-4 py-4">

        <div class="page-title d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 fw-bold mb-1"><i class="fas fa-chair me-2 text-primary"></i>Struttura Sala</h2>
                <p class="text-muted small mb-0">Aggiungi o rimuovi tavoli dalla sala</p>
            </div>
            <!-- Pulsante aggiungi visibile solo su desktop -->
            <button id="btn-add-tavolo" class="btn btn-success d-none d-md-inline-flex align-items-center gap-2">
                <i class="fas fa-plus"></i> Aggiungi Tavolo
            </button>
        </div>

        <div class="row g-3" id="tavoli-grid">
            <?php while($t = $tavoli->fetch_assoc()): ?>
            <div class="col-6 col-md-3 col-lg-2" id="tavolo-card-<?php echo $t['id']; ?>">
                <div class="tavolo-card">
                    <div class="tavolo-icon <?php echo $t['stato'] == 'libero' ? 'text-success' : 'text-danger'; ?>">
                        <i class="fas fa-chair"></i>
                    </div>
                    <div class="tavolo-numero">Tavolo <?php echo htmlspecialchars($t['numero']); ?></div>
                    <div class="tavolo-stato <?php echo $t['stato'] == 'libero' ? 'stato-libero' : 'stato-occupato'; ?>">
                        <?php echo $t['stato'] == 'libero' ? 'Libero' : 'Occupato'; ?>
                    </div>
                    <?php if($t['stato'] == 'libero'): ?>
                    <button class="btn-elimina" onclick="eliminaTavolo(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['numero']); ?>')">
                        <i class="fas fa-trash me-1"></i>Elimina
                    </button>
                    <?php else: ?>
                    <div style="height:42px; display:flex; align-items:center; justify-content:center;">
                        <small class="text-muted">Non eliminabile</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

    </main>
</div>
</div>

<!-- FAB mobile -->
<button class="fab-aggiungi d-md-none" id="btn-add-tavolo-mobile" title="Aggiungi tavolo">
    <i class="fas fa-plus"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
async function eliminaTavolo(id, numero) {
    if (!confirm(`Vuoi davvero eliminare il Tavolo ${numero}?\nL'azione è irreversibile.`)) return;
    try {
        const resp = await fetch('../api/admin/elimina-tavolo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const res = await resp.json();
        if (res.success) {
            document.getElementById(`tavolo-card-${id}`)?.remove();
        } else {
            alert('Errore: ' + res.error);
        }
    } catch(e) {
        alert('Errore di connessione.');
    }
}

async function aggiungiTavolo() {
    try {
        const resp = await fetch('../api/tavoli/crea-tavolo.php', { method: 'POST' });
        if (resp.ok) location.reload();
    } catch(e) {
        alert('Errore di connessione.');
    }
}

document.getElementById('btn-add-tavolo')?.addEventListener('click', aggiungiTavolo);
document.getElementById('btn-add-tavolo-mobile')?.addEventListener('click', aggiungiTavolo);
</script>
</body>
</html>
<?php $conn->close(); ?>