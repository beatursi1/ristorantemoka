<?php
// login.php (ROOT) - Versione con compatibilità permessi estesa e Mobile Optimized
session_start();
require_once('config/config.php');

// Se già loggato come admin, vai alla dashboard
if ((isset($_SESSION['admin_loggato']) && $_SESSION['admin_loggato'] === true) || 
    (isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'admin')) {
    header('Location: admin/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $conn = getDbConnection();
        if ($conn) {
            $sql = "SELECT id, username, password_hash, nome, ruolo FROM utenti WHERE username = ? AND attivo = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($utente = $result->fetch_assoc()) {
                if (password_verify($password, $utente['password_hash'])) {
                    // SETTAGGIO SESSIONE COMPLETO (Copre tutte le varianti di checkAccess)
                    $_SESSION['admin_loggato'] = true;
                    $_SESSION['utente_id']     = $utente['id'];
                    $_SESSION['utente_nome']   = $utente['nome'];
                    $_SESSION['utente_ruolo']  = 'admin'; // Molte funzioni checkAccess usano questa
                    $_SESSION['ruolo']         = 'admin'; // Altre usano questa
                    $_SESSION['permessi']      = 'admin'; // Fallback di sicurezza
                    
                    header('Location: admin/dashboard.php');
                    exit;
                } else {
                    $error = "Password non corretta";
                }
            } else {
                $error = "Utente non trovato o non attivo";
            }
            $stmt->close();
            $conn->close();
        } else {
            $error = "Errore di connessione al database";
        }
    } else {
        $error = "Inserisci username e password";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login Admin - RistoranteMoka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
        body { 
            background: #f0f2f5; 
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .login-container { width: 100%; max-width: 400px; padding: 20px; }
        .login-card {
            background: white;
            border-radius: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border: none;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .login-header {
            background: var(--primary-gradient);
            padding: 45px 20px;
            text-align: center;
            color: white;
        }
        .login-header i { font-size: 3.5rem; margin-bottom: 15px; opacity: 0.9; }
        .login-header h1 { font-size: 1.6rem; font-weight: 800; margin: 0; letter-spacing: 1px; text-transform: uppercase; }
        .login-body { padding: 35px 30px; }
        .form-floating > .form-control:focus { border-color: #2a5298; box-shadow: 0 0 0 0.25rem rgba(30, 60, 114, 0.1); }
        .btn-login {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 16px;
            font-weight: 700;
            width: 100%;
            border-radius: 15px;
            font-size: 1.1rem;
            transition: all 0.3s;
            margin-top: 10px;
            letter-spacing: 1px;
        }
        .btn-login:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .btn-login:active { transform: scale(0.98); }
        .error-msg {
            background: #fff5f5;
            color: #e53e3e;
            padding: 12px 15px;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 25px;
            border-left: 5px solid #e53e3e;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        @media (max-width: 480px) {
            .login-container { padding: 15px; }
            .login-header { padding: 35px 15px; }
            .login-body { padding: 25px 20px; }
            .btn-login { padding: 14px; }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-shield-alt"></i>
            <h1>Moka Admin</h1>
            <p class="mb-0 opacity-75 small">Accesso protetto alla gestione</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required autocomplete="username">
                    <label for="username">Username Amministratore</label>
                </div>
                
                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required autocomplete="current-password">
                    <label for="password">Password</label>
                </div>
                
                <button type="submit" class="btn btn-login shadow-sm">
                    ENTRA ORA <i class="fas fa-sign-in-alt ms-2"></i>
                </button>
            </form>
        </div>
    </div>
    
    <div class="text-center mt-4 text-muted">
        <small>&copy; 2026 RistoranteMoka &bull; Amministrazione</small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>