<?php
session_start();
define('ACCESS_ALLOWED', true);
require_once('../config/config.php');
require_once('../includes/auth.php');
checkAccess('admin');

$conn = getDbConnection();
$msg  = '';
$err  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'crea') {
        $username = trim($_POST['username'] ?? '');
        $nome     = trim($_POST['nome'] ?? '');
        $ruolo    = $_POST['ruolo'] ?? '';
        $password = $_POST['password'] ?? '';
        $ruoliOk  = ['cameriere', 'cucina', 'admin'];
        if (empty($username) || empty($nome) || empty($password) || !in_array($ruolo, $ruoliOk)) {
            $err = 'Compila tutti i campi correttamente.';
        } elseif (strlen($password) < 8) {
            $err = 'La password deve essere di almeno 8 caratteri.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare("INSERT INTO utenti (username, password_hash, nome, ruolo, attivo) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param('ssss', $username, $hash, $nome, $ruolo);
            if ($stmt->execute()) {
                $msg = "Utente <strong>$nome</strong> creato con successo.";
            } else {
                $err = $conn->errno === 1062
                    ? "Username <strong>$username</strong> già esistente."
                    : 'Errore durante la creazione.';
            }
            $stmt->close();
        }
    }

    if ($azione === 'toggle') {
        $uid    = (int)($_POST['uid'] ?? 0);
        $attivo = (int)($_POST['attivo'] ?? 0);
        if ($uid === (int)$_SESSION['utente_id']) {
            $err = 'Non puoi disattivare il tuo stesso account.';
        } elseif ($uid > 0) {
            $nuovo = $attivo ? 0 : 1;
            $stmt  = $conn->prepare("UPDATE utenti SET attivo = ? WHERE id = ?");
            $stmt->bind_param('ii', $nuovo, $uid);
            $stmt->execute();
            $stmt->close();
            $msg = $nuovo ? 'Utente riattivato.' : 'Utente disattivato.';
        }
    }

    if ($azione === 'cambio_password') {
        $uid  = (int)($_POST['uid'] ?? 0);
        $pass = $_POST['nuova_password'] ?? '';
        if ($uid <= 0 || strlen($pass) < 8) {
            $err = 'Password non valida (minimo 8 caratteri).';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare("UPDATE utenti SET password_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $hash, $uid);
            $stmt->execute();
            $stmt->close();
            $msg = 'Password aggiornata con successo.';
        }
    }

    if ($azione === 'elimina') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid === (int)$_SESSION['utente_id']) {
            $err = 'Non puoi eliminare il tuo stesso account.';
        } elseif ($uid > 0) {
            $stmt = $conn->prepare("DELETE FROM utenti WHERE id = ?");
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();
            $msg = 'Utente eliminato.';
        }
    }
}

$utenti = $conn->query(
    "SELECT id, username, nome, ruolo, attivo, ultimo_login, created_at 
     FROM utenti ORDER BY ruolo ASC, nome ASC"
);
$utente_nome = $_SESSION['utente_nome'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Utenti — RistoranteMoka</title>
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

        .page-title {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 20px;
            border-left: 5px solid var(--accent);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        /* CARD UTENTE — intera area cliccabile su mobile */
        .utente-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 16px;
            margin-bottom: 12px;
            transition: box-shadow 0.2s;
        }
        .utente-card.disattivo { opacity: 0.55; }
        .utente-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,0.1); }

        .utente-nome { font-weight: 700; font-size: 1rem; color: #2c3e50; }
        .utente-username { font-size: 0.8rem; color: #888; font-family: monospace; }
        .utente-meta { font-size: 0.75rem; color: #aaa; }

        .badge-admin     { background: #8e44ad; color: white; }
        .badge-cameriere { background: #27ae60; color: white; }
        .badge-cucina    { background: #e67e22; color: white; }

        /* Azioni touch-friendly */
        .azioni-gruppo { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .btn-azione-card {
            flex: 1;
            min-width: 80px;
            min-height: 40px;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 600;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            touch-action: manipulation;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-azione-card:hover { opacity: 0.85; }
        .btn-toggle-off { background: #fff3e0; color: #e67e22; }
        .btn-toggle-on  { background: #e8f5e9; color: #27ae60; }
        .btn-password   { background: #e8f0fe; color: #3498db; }
        .btn-elimina    { background: #fdecea; color: #e74c3c; }

        /* Tabella solo desktop */
        .table-desktop { display: none; }
        @media (min-width: 768px) {
            .cards-mobile  { display: none; }
            .table-desktop { display: block; }
        }

        .table-card { background: white; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
        .table thead th { background: var(--primary); color: white; font-weight: 500; font-size: 0.85rem; padding: 14px 16px; border: none; }
        .table tbody td { padding: 14px 16px; vertical-align: middle; border-color: #f0f0f0; }
        .table tbody tr:hover { background: #f8fbff; }
        .utente-disattivo td { opacity: 0.5; }
        .btn-azione { padding: 5px 10px; font-size: 0.78rem; border-radius: 6px; }

        /* Form crea utente */
        .create-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        .form-label { font-size: 0.8rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: #666; }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(52,152,219,0.15);
        }

        /* Pulsante submit touch-friendly */
        .btn-submit-crea {
            min-height: 48px;
            border-radius: 10px;
            font-weight: 700;
            width: 100%;
            font-size: 1rem;
        }
        @media (min-width: 768px) {
            .btn-submit-crea { width: auto; min-width: 160px; }
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-users-cog me-2 text-info"></i>Gestione Utenti</h1>
            <small>Admin: <?php echo htmlspecialchars($utente_nome); ?></small>
        </div>
        <a href="/ristorantemoka/logout.php" class="btn btn-sm btn-outline-light">
            <i class="fas fa-sign-out-alt me-1"></i>Esci
        </a>
    </div>
</div>

<nav class="nav-mobile d-md-none">
    <a href="dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
    <a href="tavoli.php"><i class="fas fa-chair"></i>Tavoli</a>
    <a href="../app/admin/admin-menu.html"><i class="fas fa-book-open"></i>Menù</a>
    <a href="utenti.php" class="active"><i class="fas fa-users-cog"></i>Utenti</a>
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
            <li><a class="nav-link" href="tavoli.php"><i class="fas fa-chair me-2"></i>Gestione Tavoli</a></li>
            <li><a class="nav-link" href="../app/admin/admin-menu.html"><i class="fas fa-book-open me-2"></i>Gestione Menù</a></li>
            <li><a class="nav-link active" href="utenti.php"><i class="fas fa-users-cog me-2"></i>Gestione Utenti</a></li>
            <li><a class="nav-link" href="manutenzione.php"><i class="fas fa-database me-2"></i>Manutenzione DB</a></li>
        </ul>
    </nav>

    <main class="col-md-10 px-3 px-md-4 py-4">

        <div class="page-title">
            <h2 class="h4 fw-bold mb-1"><i class="fas fa-users-cog me-2 text-primary"></i>Gestione Utenti</h2>
            <p class="text-muted small mb-0">Crea e gestisci l'accesso del personale al sistema</p>
        </div>

        <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3">
            <i class="fas fa-check-circle me-2"></i><?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($err): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $err; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- FORM CREA UTENTE -->
        <div class="create-card">
            <h5 class="fw-bold mb-4"><i class="fas fa-user-plus me-2 text-success"></i>Aggiungi nuovo utente</h5>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="azione" value="crea">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Nome completo</label>
                        <input type="text" name="nome" class="form-control" placeholder="Es: Marco Rossi" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Es: marco.rossi" required autocomplete="off">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Ruolo</label>
                        <select name="ruolo" class="form-select" required>
                            <option value="">— Ruolo —</option>
                            <option value="cameriere">Cameriere</option>
                            <option value="cucina">Cucina</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Password <small class="text-muted">(min. 8 car.)</small></label>
                        <div class="input-group">
                            <input type="password" name="password" id="pw-crea" class="form-control"
                                   placeholder="••••••••" required minlength="8" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePw('pw-crea', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12 col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-success btn-submit-crea">
                            <i class="fas fa-plus me-1"></i>Crea
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ═══════════════════════════════════════════ -->
        <!-- LISTA MOBILE: card per ogni utente          -->
        <!-- ═══════════════════════════════════════════ -->
        <div class="cards-mobile">
            <?php
            $utenti->data_seek(0);
            while ($u = $utenti->fetch_assoc()):
                $isSelf = ($u['id'] == $_SESSION['utente_id']);
            ?>
            <div class="utente-card <?php echo !$u['attivo'] ? 'disattivo' : ''; ?>">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <div>
                        <div class="utente-nome">
                            <?php echo htmlspecialchars($u['nome']); ?>
                            <?php if ($isSelf): ?><span class="badge bg-secondary ms-1" style="font-size:0.65rem;">Tu</span><?php endif; ?>
                        </div>
                        <div class="utente-username">@<?php echo htmlspecialchars($u['username']); ?></div>
                    </div>
                    <span class="badge badge-<?php echo $u['ruolo']; ?> rounded-pill px-3">
                        <?php echo ucfirst($u['ruolo']); ?>
                    </span>
                </div>

                <div class="d-flex align-items-center gap-2 mb-2">
                    <?php if ($u['attivo']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Attivo</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Disattivato</span>
                    <?php endif; ?>
                    <span class="utente-meta">
                        <?php echo $u['ultimo_login']
                            ? 'Accesso: ' . date('d/m/Y H:i', strtotime($u['ultimo_login']))
                            : 'Mai connesso'; ?>
                    </span>
                </div>

                <div class="azioni-gruppo">
                    <?php if (!$isSelf): ?>
                    <form method="POST" style="flex:1; min-width:80px;">
                        <input type="hidden" name="azione" value="toggle">
                        <input type="hidden" name="uid" value="<?php echo $u['id']; ?>">
                        <input type="hidden" name="attivo" value="<?php echo $u['attivo']; ?>">
                        <button type="submit" class="btn-azione-card <?php echo $u['attivo'] ? 'btn-toggle-off' : 'btn-toggle-on'; ?>" style="width:100%;">
                            <i class="fas <?php echo $u['attivo'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                            <?php echo $u['attivo'] ? 'Disattiva' : 'Riattiva'; ?>
                        </button>
                    </form>
                    <?php endif; ?>

                    <button class="btn-azione-card btn-password"
                            onclick="apriCambioPassword(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['nome']); ?>')">
                        <i class="fas fa-key"></i> Password
                    </button>

                    <?php if (!$isSelf): ?>
                    <button class="btn-azione-card btn-elimina"
                            onclick="confermaElimina(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['nome']); ?>')">
                        <i class="fas fa-trash"></i> Elimina
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- ═══════════════════════════════════════════ -->
        <!-- TABELLA DESKTOP                             -->
        <!-- ═══════════════════════════════════════════ -->
        <div class="table-desktop">
            <div class="table-card">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Username</th>
                            <th>Ruolo</th>
                            <th>Stato</th>
                            <th>Ultimo accesso</th>
                            <th class="text-end">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $utenti->data_seek(0);
                    while ($u = $utenti->fetch_assoc()):
                        $isSelf = ($u['id'] == $_SESSION['utente_id']);
                    ?>
                    <tr class="<?php echo !$u['attivo'] ? 'utente-disattivo' : ''; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($u['nome']); ?></strong>
                            <?php if ($isSelf): ?><span class="badge bg-secondary ms-1">Tu</span><?php endif; ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($u['username']); ?></code></td>
                        <td>
                            <span class="badge badge-<?php echo $u['ruolo']; ?> rounded-pill px-3">
                                <?php echo ucfirst($u['ruolo']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['attivo']): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Attivo</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Disattivato</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.78rem; color:#888;">
                            <?php echo $u['ultimo_login']
                                ? date('d/m/Y H:i', strtotime($u['ultimo_login']))
                                : '<em>Mai</em>'; ?>
                        </td>
                        <td class="text-end">
                            <?php if (!$isSelf): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="azione" value="toggle">
                                <input type="hidden" name="uid" value="<?php echo $u['id']; ?>">
                                <input type="hidden" name="attivo" value="<?php echo $u['attivo']; ?>">
                                <button class="btn btn-azione <?php echo $u['attivo'] ? 'btn-warning' : 'btn-success'; ?>"
                                        title="<?php echo $u['attivo'] ? 'Disattiva' : 'Riattiva'; ?>">
                                    <i class="fas <?php echo $u['attivo'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <button class="btn btn-azione btn-outline-primary"
                                    onclick="apriCambioPassword(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['nome']); ?>')"
                                    title="Cambia password">
                                <i class="fas fa-key"></i>
                            </button>
                            <?php if (!$isSelf): ?>
                            <button class="btn btn-azione btn-outline-danger"
                                    onclick="confermaElimina(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['nome']); ?>')"
                                    title="Elimina utente">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>
</div>

<!-- Modal cambia password -->
<div class="modal fade" id="modalPassword" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="azione" value="cambio_password">
                <input type="hidden" name="uid" id="modal-uid">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Cambia password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Utente: <strong id="modal-nome"></strong></p>
                    <label class="form-label">Nuova password <small>(min. 8 caratteri)</small></label>
                    <div class="input-group">
                        <input type="password" name="nuova_password" id="pw-modal" class="form-control"
                               placeholder="••••••••" required minlength="8" autocomplete="new-password">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePw('pw-modal', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" id="form-elimina" style="display:none">
    <input type="hidden" name="azione" value="elimina">
    <input type="hidden" name="uid" id="elimina-uid">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function apriCambioPassword(uid, nome) {
    document.getElementById('modal-uid').value = uid;
    document.getElementById('modal-nome').textContent = nome;
    document.getElementById('pw-modal').value = '';
    new bootstrap.Modal(document.getElementById('modalPassword')).show();
}

function confermaElimina(uid, nome) {
    if (confirm(`Eliminare definitivamente l'utente "${nome}"?\nQuesta azione non può essere annullata.`)) {
        document.getElementById('elimina-uid').value = uid;
        document.getElementById('form-elimina').submit();
    }
}

function togglePw(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
<?php $conn->close(); ?>