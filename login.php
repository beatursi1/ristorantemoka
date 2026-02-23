<?php
// login.php (ROOT) - Login unificato per admin, cameriere, cucina
define('ACCESS_ALLOWED', true);
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
require_once('config/config.php');

if (isset($_SESSION['utente_id'])) {
    $ruolo = $_SESSION['utente_ruolo'] ?? '';
    if ($ruolo === 'admin')     { header('Location: admin/dashboard.php');      exit; }
    if ($ruolo === 'cameriere') { header('Location: camerieri/dashboard.php');  exit; }
    if ($ruolo === 'cucina')    { header('Location: cucina/index.php');          exit; }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Inserisci username e password.';
    } else {
        $conn = getDbConnection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT id, username, password_hash, nome, ruolo FROM utenti WHERE username = ? AND attivo = 1 LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $utente = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($utente && password_verify($password, $utente['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['utente_id']     = $utente['id'];
                $_SESSION['utente_nome']   = $utente['nome'];
                $_SESSION['utente_ruolo']  = $utente['ruolo'];
                $_SESSION['last_activity'] = time();

                $upd = $conn->prepare("UPDATE utenti SET ultimo_login = NOW() WHERE id = ?");
                $upd->bind_param('i', $utente['id']);
                $upd->execute();
                $upd->close();
                $conn->close();

                if ($utente['ruolo'] === 'admin')     { header('Location: admin/dashboard.php');      exit; }
                if ($utente['ruolo'] === 'cameriere') { header('Location: camerieri/dashboard.php');  exit; }
                if ($utente['ruolo'] === 'cucina')    { header('Location: cucina/index.php');          exit; }
            } else {
                $error = 'Credenziali non valide.';
                if ($conn) $conn->close();
            }
        } else {
            $error = 'Errore di connessione al database.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Accesso — RistoranteMoka</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink:    #1a1209;
            --cream:  #faf6ef;
            --gold:   #c8973a;
            --gold2:  #e8b84b;
            --muted:  #8a7d6b;
            --danger: #c0392b;
        }

        body {
            min-height: 100vh;
            background-color: var(--cream);
            background-image:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(200,151,58,0.18) 0%, transparent 70%),
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c8973a' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'DM Sans', sans-serif;
            padding: 20px;
        }

        .card {
            width: 100%; max-width: 420px;
            background: #fff; border-radius: 4px;
            box-shadow: 0 2px 4px rgba(26,18,9,0.06), 0 8px 24px rgba(26,18,9,0.10), 0 0 0 1px rgba(200,151,58,0.15);
            overflow: hidden;
            animation: rise 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(24px) scale(0.98); }
            to   { opacity: 1; transform: none; }
        }

        .card-header {
            background: var(--ink);
            padding: 44px 40px 36px;
            text-align: center;
            position: relative; overflow: hidden;
        }
        .card-header::before {
            content: ''; position: absolute; inset: 0;
            background: repeating-linear-gradient(-45deg, transparent, transparent 18px, rgba(200,151,58,0.04) 18px, rgba(200,151,58,0.04) 19px);
        }
        .logo-ring {
            width: 80px; height: 80px; border-radius: 50%;
            border: 2px solid var(--gold);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; position: relative; z-index: 1;
        }
        .logo-ring svg { width: 38px; height: 38px; fill: var(--gold); }
        .card-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem; font-weight: 900; color: #fff;
            letter-spacing: 0.04em; position: relative; z-index: 1;
        }
        .card-header p {
            font-size: 0.8rem; color: var(--gold);
            letter-spacing: 0.15em; text-transform: uppercase;
            margin-top: 6px; position: relative; z-index: 1; font-weight: 500;
        }

        .divider {
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--gold), var(--gold2), var(--gold), transparent);
        }

        .card-body { padding: 36px 40px 40px; }

        .error-box {
            background: #fdf2f2; border-left: 3px solid var(--danger);
            color: var(--danger); padding: 11px 14px; border-radius: 3px;
            font-size: 0.875rem; margin-bottom: 24px;
            animation: shake 0.3s ease;
        }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25%     { transform: translateX(-6px); }
            75%     { transform: translateX(6px); }
        }

        .field { margin-bottom: 20px; }
        .field label {
            display: block; font-size: 0.7rem; font-weight: 500;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 7px;
        }
        .field input {
            width: 100%; padding: 12px 14px;
            border: 1.5px solid #e5dfd4; border-radius: 3px;
            font-family: 'DM Sans', sans-serif; font-size: 0.975rem;
            color: var(--ink); background: var(--cream);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .field input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(200,151,58,0.12);
            background: #fff;
        }
        .field input::placeholder { color: #bfb8ac; }

        /* Toggle password */
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 46px; }
        .pw-toggle {
            position: absolute; right: 13px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--muted); font-size: 1rem; padding: 4px;
            line-height: 1; transition: color 0.2s;
        }
        .pw-toggle:hover { color: var(--ink); }

        .btn-login {
            width: 100%; padding: 14px;
            background: var(--ink); color: #fff;
            border: none; border-radius: 3px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem; font-weight: 500;
            letter-spacing: 0.12em; text-transform: uppercase;
            cursor: pointer; margin-top: 8px;
            transition: background 0.2s, transform 0.1s;
            position: relative; overflow: hidden;
        }
        .btn-login:hover  { background: #2e200e; }
        .btn-login:active { transform: scale(0.99); }
        .btn-login::after {
            content: ''; position: absolute;
            bottom: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, var(--gold), var(--gold2), var(--gold));
        }

        .card-footer {
            text-align: center; padding-bottom: 28px;
            font-size: 0.75rem; color: var(--muted);
        }

        @media (max-width: 480px) {
            .card-header { padding: 32px 24px 28px; }
            .card-body   { padding: 28px 24px 32px; }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <div class="logo-ring">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M2 21h18v-2H2v2zm6-4h6a6 6 0 0 0 6-6V3H2v8a6 6 0 0 0 6 6zm-4-8V5h14v4a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4zm14-2h2a2 2 0 0 1 0 4h-2V7z"/>
            </svg>
        </div>
        <h1>RistoranteMoka</h1>
        <p>Accesso riservato al personale</p>
    </div>

    <div class="divider"></div>

    <div class="card-body">
        <?php if ($error): ?>
        <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <div class="field">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Il tuo username"
                    required
                    autofocus
                    autocomplete="username"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                >
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="pw-toggle" onClick="togglePw()" aria-label="Mostra/nascondi password">
                        <i class="fas fa-eye" id="ico-pw"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">Accedi</button>
        </form>
    </div>

    <div class="card-footer">
        &copy; <?php echo date('Y'); ?> RistoranteMoka &mdash; Gestione interna
    </div>
</div>

<script>
function togglePw() {
    var input = document.getElementById('password');
    var icon  = document.getElementById('ico-pw');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
</body>
</html>