<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configura il percorso base del tuo sito
$base_url = "/ristorantemoka";

function checkAccess($requiredRole = null) {
    global $base_url;
    
    // 1. Controllo se l'utente è loggato
    if (!isset($_SESSION['utente_id'])) {
        header("Location: $base_url/login.php");
        exit;
    }

    // 2. Recupero il ruolo (cerco in diverse chiavi per sicurezza)
    $userRole = $_SESSION['utente_ruolo'] ?? $_SESSION['ruolo'] ?? null;

    // 3. L'Admin ha sempre accesso a tutto
    if ($userRole === 'admin') {
        return true;
    }

    // 4. Se è richiesto un ruolo specifico e l'utente non lo ha
    if ($requiredRole !== null && $userRole !== $requiredRole) {
        http_response_code(403);
        // Design pulito per l'errore 403
        die("<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Accesso Negato</title><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='bg-light d-flex align-items-center justify-content-center vh-100'><div class='text-center p-5 bg-white rounded shadow-sm'><h1 class='display-1 text-danger'>403</h1><h3>Accesso Negato</h3><p class='text-muted'>Il tuo account ($userRole) non ha i permessi per accedere a quest'area.</p><a href='$base_url/login.php' class='btn btn-primary'>Torna al Login</a></div></body></html>");
    }

    return true;
}
?>