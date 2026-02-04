<?php
// login.php
session_start();
require_once('../config/config.php');

// Se già loggato, reindirizza
if (isset($_SESSION['cameriere_id'])) {
    header('Location: inizializza.php');
    exit;
}

// Processa login se inviato
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codice = $_POST['codice'] ?? '';
    $pin = $_POST['pin'] ?? '';
    
    if (!empty($codice) && !empty($pin)) {
        $conn = getDbConnection();
        
        if ($conn) {
            $stmt = $conn->prepare("SELECT id, nome, codice FROM camerieri WHERE codice = ? AND pin_code = ? AND attivo = TRUE");
            $stmt->bind_param("ss", $codice, $pin);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($cameriere = $result->fetch_assoc()) {
                $_SESSION['cameriere_id'] = $cameriere['id'];
                $_SESSION['cameriere_nome'] = $cameriere['nome'];
                $_SESSION['cameriere_codice'] = $cameriere['codice'];
                
                header('Location: inizializza.php');
                exit;
            } else {
                $error = 'Codice o PIN errati';
            }
            
            $stmt->close();
            $conn->close();
        } else {
            $error = 'Errore di connessione al database';
        }
    } else {
        $error = 'Inserisci codice e PIN';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Camerieri - RistoranteMoka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #2c3e50, #4a6491);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-body {
            padding: 40px;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: bold;
            width: 100%;
            border-radius: 10px;
            transition: transform 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 12px;
            color: #6c757d;
        }
        .input-icon input {
            padding-left: 45px;
        }
        .cameriere-demo {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-card">
                    <div class="login-header">
                        <i class="fas fa-utensils fa-3x mb-3"></i>
                        <h1 class="h3">RistoranteMoka</h1>
                        <p class="mb-0">Accesso Camerieri</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-4 input-icon">
                                <i class="fas fa-id-card"></i>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       name="codice" 
                                       placeholder="Codice cameriere" 
                                       required
                                       autofocus>
                            </div>
                            
                            <div class="mb-4 input-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" 
                                       class="form-control form-control-lg" 
                                       name="pin" 
                                       placeholder="PIN (4 cifre)" 
                                       required
                                       maxlength="4"
                                       pattern="\d{4}">
                            </div>
                            
                            <button type="submit" class="btn btn-login btn-lg mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>ACCEDI
                            </button>
                            
                            <div class="cameriere-demo">
                                <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Credenziali demo:</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Marco:</strong><br>
                                        Codice: <code>CAM001</code><br>
                                        PIN: <code>1234</code>
                                    </div>
                                    <div class="col-6">
                                        <strong>Laura:</strong><br>
                                        Codice: <code>CAM002</code><br>
                                        PIN: <code>5678</code>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-white">
                    <small>
                        <i class="fas fa-tablet-alt me-1"></i> 
                        Sistema di gestione ristorante - Versione 1.0
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Focus sul campo codice all'apertura
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="codice"]').focus();
        });
        
        // Valida che il PIN sia solo numeri
        document.querySelector('input[name="pin"]').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>