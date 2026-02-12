<?php
// admin/dashboard.php
require_once('../includes/auth.php');
// SICUREZZA: Solo l'admin può visualizzare la dashboard
checkAccess('admin'); 
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
        :root { --primary-color: #2c3e50; --accent-color: #3498db; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .sidebar { background: var(--primary-color); min-height: 100vh; color: white; padding-top: 20px; box-shadow: 4px 0 10px rgba(0,0,0,0.1); }
        .nav-link { color: rgba(255,255,255,0.7); margin-bottom: 10px; transition: 0.3s; padding: 12px 20px; border-radius: 8px; margin: 0 10px 5px 10px; }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(255,255,255,0.1); }
        .nav-link.active { background: var(--accent-color); font-weight: 600; }
        
        .stat-card { border: none; border-radius: 20px; transition: all 0.3s; background: white; overflow: hidden; height: 100%; }
        .stat-card:hover { transform: translateY(-8px); box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important; }
        
        .icon-box { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 28px; flex-shrink: 0; }
        .bg-tavoli { background-color: #27ae60; color: white; }
        .bg-menu { background-color: #e67e22; color: white; }
        .bg-cucina { background-color: #3498db; color: white; }
        
        .welcome-section { background: white; padding: 30px; border-radius: 20px; margin-bottom: 30px; border-left: 6px solid var(--accent-color); shadow: 0 4px 6px rgba(0,0,0,0.02); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky">
                    <div class="text-center mb-4">
                        <i class="fas fa-utensils fa-3x mb-2 text-info"></i>
                        <h5 class="fw-bold">Moka Admin</h5>
                        <hr class="mx-3 opacity-25">
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="tavoli.php"><i class="fas fa-table me-2"></i> Gestione Tavoli</a></li>
                        <li class="nav-item"><a class="nav-link" href="../app/admin/admin-menu.html"><i class="fas fa-book-open me-2"></i> Gestione Menù</a></li>
                        <li class="nav-item mt-5"><a class="nav-link text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Esci</a></li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="welcome-section shadow-sm d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-1 fw-bold text-dark">Bentornato, <?php echo htmlspecialchars($_SESSION['utente_nome']); ?>!</h1>
                        <p class="text-muted mb-0">Ecco cosa sta succedendo nel tuo ristorante oggi.</p>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                        <i class="fas fa-user-shield me-1"></i> Admin Account
                    </span>
                </div>

                <div class="row">
                    <!-- Card Tavoli -->
                    <div class="col-md-4 mb-4">
                        <div class="card stat-card shadow-sm">
                            <div class="card-body p-4 d-flex align-items-start">
                                <div class="icon-box bg-tavoli shadow-sm me-3"><i class="fas fa-chair"></i></div>
                                <div>
                                    <h5 class="fw-bold mb-1">Tavoli</h5>
                                    <p class="small text-muted">Configura la sala e visualizza lo stato dei tavoli in tempo reale.</p>
                                    <a href="tavoli.php" class="btn btn-sm btn-success px-3 rounded-pill mt-2">Gestisci Tavoli</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Menù -->
                    <div class="col-md-4 mb-4">
                        <div class="card stat-card shadow-sm">
                            <div class="card-body p-4 d-flex align-items-start">
                                <div class="icon-box bg-menu shadow-sm me-3"><i class="fas fa-utensils"></i></div>
                                <div>
                                    <h5 class="fw-bold mb-1">Menù Digitale</h5>
                                    <p class="small text-muted">Aggiungi piatti, cambia prezzi e gestisci le categorie del menù.</p>
                                    <a href="../app/admin/admin-menu.html" class="btn btn-sm btn-warning text-white px-3 rounded-pill mt-2">Gestisci Menù</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card Monitor (In arrivo) -->
                    <div class="col-md-4 mb-4">
                        <div class="card stat-card shadow-sm border-0 bg-light">
                            <div class="card-body p-4 d-flex align-items-start opacity-50">
                                <div class="icon-box bg-cucina shadow-sm me-3"><i class="fas fa-desktop"></i></div>
                                <div>
                                    <h5 class="fw-bold mb-1">Monitor Cucina</h5>
                                    <p class="small text-muted">Visualizza gli ordini in arrivo per lo chef (In fase di sviluppo).</p>
                                    <button class="btn btn-sm btn-secondary px-3 rounded-pill mt-2" disabled>Prossimamente</button>
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