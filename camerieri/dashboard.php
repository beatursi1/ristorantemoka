<?php
// camerieri/dashboard.php
session_start();
define('ACCESS_ALLOWED', true);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
require_once('../config/config.php');

if (!isset($_SESSION['utente_id']) || !in_array($_SESSION['utente_ruolo'], ['cameriere', 'admin'])) {
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
    <title>Dashboard Cameriere - RistoranteMoka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary:  #0d3d22;
            --accent:   #1a6b3c;
            --viola:    #5b4fcf;
            --gray-bg:  #f1f5f9;
        }
        * { box-sizing: border-box; }
        body {
            background: var(--gray-bg);
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: #1e293b;
            min-height: 100vh;
        }

        /* ── TOPBAR ── */
        .topbar {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 14px 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .topbar h1 { font-size: 1.1rem; font-weight: 700; margin: 0; }
        .topbar small { opacity: 0.8; font-size: 0.78rem; }

        /* ── CARDS ── */
        .action-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            text-decoration: none;
            color: #1e293b;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 14px;
        }
        .action-card:hover, .action-card:active {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            color: #1e293b;
        }
        .action-card:active { transform: scale(0.98); }

        .card-icon {
            width: 56px; height: 56px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .ic-tavoli   { background: #dbeafe; color: #1d4ed8; }
        .ic-comanda  { background: #dcfce7; color: #166534; }
        .ic-bevande  { background: #ede9ff; color: #4c1d95; }

        .card-text h5 { font-weight: 700; font-size: 1rem; margin: 0 0 3px; }
        .card-text p  { font-size: 0.8rem; color: #64748b; margin: 0; }

        .card-arrow {
            margin-left: auto;
            color: #cbd5e1;
            font-size: 1rem;
            flex-shrink: 0;
        }

        /* ── WELCOME ── */
        .welcome-box {
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            border-left: 5px solid var(--accent);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .welcome-box h2 { font-size: 1.1rem; font-weight: 700; margin: 0 0 3px; }
        .welcome-box p  { font-size: 0.8rem; color: #64748b; margin: 0; }

        /* ── SIDEBAR solo desktop ── */
        .sidebar {
            background: var(--primary);
            min-height: 100vh;
            padding-top: 20px;
        }
        .sidebar .brand {
            text-align: center;
            padding: 0 16px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 10px;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.75);
            padding: 11px 20px;
            border-radius: 8px;
            margin: 0 10px 4px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.2s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active { color: white; background: rgba(255,255,255,0.12); }
        .sidebar .nav-link.active { background: var(--accent); font-weight: 600; }
        .sidebar .nav-link.danger { color: #fca5a5; }
        .sidebar .nav-link.danger:hover { background: rgba(239,68,68,0.15); color: #f87171; }
    </style>
</head>
<body>

<!-- TOPBAR mobile-first con logout sempre visibile -->
<div class="topbar">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-utensils me-2 text-warning"></i>Area Camerieri</h1>
            <small><?php echo htmlspecialchars($utente_nome); ?></small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($_SESSION['utente_ruolo'] === 'admin'): ?>
            <a href="/ristorantemoka/admin/dashboard.php" class="btn btn-sm btn-warning fw-bold">
                <i class="fas fa-user-shield me-1"></i>Admin
            </a>
            <?php endif; ?>
            <a href="/ristorantemoka/logout.php" class="btn btn-sm btn-outline-light">
                <i class="fas fa-sign-out-alt me-1"></i>Esci
            </a>
        </div>
    </div>
</div>

<div class="container-fluid">
<div class="row">

    <!-- SIDEBAR solo desktop -->
    <nav class="col-lg-2 d-none d-lg-block sidebar">
        <div class="brand">
            <i class="fas fa-utensils fa-2x text-warning mb-2 d-block"></i>
            <span class="text-white fw-bold">Moka</span><br>
            <small class="text-white-50">Camerieri</small>
        </div>
        <ul class="nav flex-column mt-2">
            <li><a class="nav-link active" href="dashboard.php"><i class="fas fa-home"></i>Dashboard</a></li>
            <li><a class="nav-link" href="inizializza.php"><i class="fas fa-table"></i>Gestione Tavoli</a></li>
            <li><a class="nav-link" href="inizializza.php?mode=order"><i class="fas fa-utensils"></i>Ordina per Cliente</a></li>
            <li><a class="nav-link" href="bevande.php"><i class="fas fa-wine-glass-alt"></i>Bevande</a></li>
            
        </ul>
    </nav>

    <!-- MAIN -->
    <main class="col-lg-10 px-3 px-md-4 py-4">

        <!-- Welcome -->
        <div class="welcome-box">
            <div>
                <h2>Bentornato, <?php echo htmlspecialchars($utente_nome); ?>!</h2>
                <p>Cosa vuoi fare oggi?</p>
            </div>
            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">
                <i class="fas fa-user me-1"></i>Cameriere
            </span>
        </div>

        <!-- CARDS — visibili su mobile e desktop -->
        <a href="inizializza.php" class="action-card">
            <div class="card-icon ic-tavoli">
                <i class="fas fa-table"></i>
            </div>
            <div class="card-text">
                <h5>Gestione Tavoli</h5>
                <p>Apri tavoli, genera QR code e monitora i clienti</p>
            </div>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>

        <a href="inizializza.php?mode=order" class="action-card">
            <div class="card-icon ic-comanda">
                <i class="fas fa-utensils"></i>
            </div>
            <div class="card-text">
                <h5>Ordina per Cliente</h5>
                <p>Prendi le comande direttamente dal tuo dispositivo</p>
            </div>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>

        <a href="bevande.php" class="action-card">
            <div class="card-icon ic-bevande">
                <i class="fas fa-wine-glass-alt"></i>
            </div>
            <div class="card-text">
                <h5>Bevande</h5>
                <p>Gestisci le bevande ordinate e aggiornane lo stato</p>
            </div>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>

    </main>
</div>
</div>

</body>
</html>