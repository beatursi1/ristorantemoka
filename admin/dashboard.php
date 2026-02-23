<?php
// admin/dashboard.php
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
    <title>Dashboard Admin - RistoranteMoka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2c3e50; --accent: #3498db; }
        body { background: #f8f9fa; font-family: 'Segoe UI', system-ui, sans-serif; }
        .topbar { background: var(--primary); color: white; padding: 14px 16px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .topbar h1 { font-size: 1.1rem; font-weight: 700; margin: 0; }
        .topbar small { opacity: 0.75; font-size: 0.78rem; }
        .nav-mobile { background: #34495e; display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; padding: 0 8px; }
        .nav-mobile::-webkit-scrollbar { display: none; }
        .nav-mobile a { color: rgba(255,255,255,0.8); text-decoration: none; white-space: nowrap; padding: 10px 14px; font-size: 0.82rem; font-weight: 600; border-bottom: 3px solid transparent; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-mobile a:hover, .nav-mobile a.active { color: white; border-bottom-color: white; }
        .stat-card { border: none; border-radius: 16px; transition: all 0.3s; background: white; height: 100%; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-card:hover { transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0,0,0,0.1); }
        .icon-box { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
        .ic-tavoli { background: #27ae60; color: white; }
        .ic-menu   { background: #e67e22; color: white; }
        .ic-utenti { background: #8e44ad; color: white; }
        .ic-manut  { background: #fff3e0; color: #e67e22; }
        .ic-cassa  { background: #fef9c3; color: #b45309; }
        .welcome-box { background: white; padding: 20px; border-radius: 16px; margin-bottom: 20px; border-left: 5px solid var(--accent); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .sidebar { background: var(--primary); min-height: 100vh; padding-top: 20px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 12px 20px; border-radius: 8px; margin: 0 10px 5px; display: block; text-decoration: none; transition: 0.2s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: white; background: rgba(255,255,255,0.1); }
        .sidebar .nav-link.active { background: var(--accent); font-weight: 600; }
    </style>
</head>
<body>

<div class="topbar">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-utensils me-2 text-info"></i>Moka Admin</h1>
            <small>Admin: <?php echo htmlspecialchars($utente_nome); ?></small>
        </div>
        <a href="/ristorantemoka/logout.php" class="btn btn-sm btn-outline-light">
            <i class="fas fa-sign-out-alt me-1"></i>Esci
        </a>
    </div>
</div>

<nav class="nav-mobile d-md-none">
    <a href="dashboard.php" class="active"><i class="fas fa-home"></i>Dashboard</a>
    <a href="tavoli.php"><i class="fas fa-table"></i>Tavoli</a>
    <a href="../app/admin/admin-menu.html"><i class="fas fa-book-open"></i>Menù</a>
    <a href="utenti.php"><i class="fas fa-users-cog"></i>Utenti</a>
    <a href="manutenzione.php"><i class="fas fa-database"></i>Manutenzione</a>
    <a href="cassa.php"><i class="fas fa-cash-register"></i>Cassa</a>
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
            <li><a class="nav-link active" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
            <li><a class="nav-link" href="tavoli.php"><i class="fas fa-table me-2"></i>Gestione Tavoli</a></li>
            <li><a class="nav-link" href="../app/admin/admin-menu.html"><i class="fas fa-book-open me-2"></i>Gestione Menù</a></li>
            <li><a class="nav-link" href="utenti.php"><i class="fas fa-users-cog me-2"></i>Gestione Utenti</a></li>
            <li><a class="nav-link" href="manutenzione.php"><i class="fas fa-database me-2"></i>Manutenzione DB</a></li>
            <li><a class="nav-link" href="cassa.php"><i class="fas fa-cash-register me-2"></i>Cassa</a></li>
            <li class="mt-4"><a class="nav-link text-danger" href="/ristorantemoka/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Esci</a></li>
        </ul>
    </nav>

    <main class="col-md-10 px-3 px-md-4 py-4">

        <div class="welcome-box d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 fw-bold mb-1">Bentornato, <?php echo htmlspecialchars($utente_nome); ?>!</h2>
                <p class="text-muted mb-0 small">Ecco cosa sta succedendo nel tuo ristorante oggi.</p>
            </div>
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                <i class="fas fa-user-shield me-1"></i>Admin
            </span>
        </div>

        <div class="row g-3">

            <div class="col-12 col-md-4">
                <div class="stat-card">
                    <div class="card-body p-4 d-flex align-items-start gap-3">
                        <div class="icon-box ic-tavoli"><i class="fas fa-chair"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1">Tavoli</h5>
                            <p class="small text-muted mb-2">Configura la sala e visualizza lo stato dei tavoli.</p>
                            <a href="tavoli.php" class="btn btn-sm btn-success rounded-pill px-3">Gestisci Tavoli</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="stat-card">
                    <div class="card-body p-4 d-flex align-items-start gap-3">
                        <div class="icon-box ic-menu"><i class="fas fa-utensils"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1">Menù Digitale</h5>
                            <p class="small text-muted mb-2">Aggiungi piatti, cambia prezzi e categorie.</p>
                            <a href="../app/admin/admin-menu.html" class="btn btn-sm btn-warning text-white rounded-pill px-3">Gestisci Menù</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="stat-card">
                    <div class="card-body p-4 d-flex align-items-start gap-3">
                        <div class="icon-box ic-utenti"><i class="fas fa-users-cog"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1">Gestione Utenti</h5>
                            <p class="small text-muted mb-2">Aggiungi camerieri, cuochi e admin.</p>
                            <a href="utenti.php" class="btn btn-sm rounded-pill px-3 text-white" style="background:#8e44ad;">Gestisci Utenti</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="stat-card">
                    <div class="card-body p-4 d-flex align-items-start gap-3">
                        <div class="icon-box ic-manut"><i class="fas fa-database"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1">Manutenzione DB</h5>
                            <p class="small text-muted mb-2">Chiusura giornaliera, archivio e pulizia dati.</p>
                            <a href="manutenzione.php" class="btn btn-sm rounded-pill px-3 text-white" style="background:#e67e22;">Gestisci</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="stat-card">
                    <div class="card-body p-4 d-flex align-items-start gap-3">
                        <div class="icon-box ic-cassa"><i class="fas fa-cash-register"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1">Cassa</h5>
                            <p class="small text-muted mb-2">Chiudi conti, gestisci pagamenti e stampa scontrini.</p>
                            <a href="cassa.php" class="btn btn-sm rounded-pill px-3 text-white fw-bold" style="background:#b45309;">Apri Cassa</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="stat-card">
                    <div class="card-body p-4 d-flex align-items-start gap-3">
                        <div class="icon-box" style="background:#dcfce7;color:#166534;"><i class="fas fa-concierge-bell"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1">Area Camerieri</h5>
                            <p class="small text-muted mb-2">Accedi alla dashboard camerieri e gestisci tavoli e comande.</p>
                            <a href="../camerieri/dashboard.php" class="btn btn-sm rounded-pill px-3 text-white fw-bold" style="background:#166534;">Vai ai Camerieri</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="stat-card">
                    <div class="card-body p-4 d-flex align-items-start gap-3">
                        <div class="icon-box" style="background:#fee2e2;color:#b91c1c;"><i class="fas fa-fire-burner"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1">Area Cucina</h5>
                            <p class="small text-muted mb-2">Monitora gli ordini in entrata e aggiorna lo stato dei piatti.</p>
                            <a href="../cucina/index.php" class="btn btn-sm rounded-pill px-3 text-white fw-bold" style="background:#b91c1c;">Vai alla Cucina</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>
</div>
</body>
</html>