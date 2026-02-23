<?php
/**
 * Authentication Helper - VERSIONE MIGLIORATA
 * 
 * Fix applicati:
 * - API chiamate ritornano JSON invece di redirect HTML
 * - Session timeout handling
 * - Security improvements
 * 
 * @version 2.0.0 - SECURE
 */

// Previeni accesso diretto
if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Check access con gestione API
 * 
 * @param string|null $requiredRole Ruolo richiesto (null = qualsiasi autenticato)
 * @param bool $isApi Se true, ritorna JSON invece di redirect
 */
function checkAccess($requiredRole = null, $isApi = null) {
    // Auto-detect se è una chiamata API
    if ($isApi === null) {
        $isApi = (
            strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ||
            strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        );
    }
    
    // Avvia sessione se non già avviata
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check se utente è loggato
    if (!isset($_SESSION['utente_id'])) {
        handleUnauthorized($isApi, 'Non autenticato. Effettua il login.');
        return;
    }
    
    // Check session timeout (2 ore)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 7200) {
        session_unset();
        session_destroy();
        handleUnauthorized($isApi, 'Sessione scaduta. Effettua nuovamente il login.');
        return;
    }
    
    // Aggiorna last activity
    $_SESSION['last_activity'] = time();
    
    // Se non è richiesto un ruolo specifico, qualsiasi autenticato va bene
    if ($requiredRole === null) {
        return true;
    }
    
    // Determina il ruolo dell'utente (gestisce vari nomi di sessione)
    $userRole = $_SESSION['utente_ruolo'] ?? 
                $_SESSION['ruolo'] ?? 
                $_SESSION['permessi'] ?? 
                null;
    
    // Admin ha accesso a tutto
    if ($userRole === 'admin') {
        return true;
    }
    
    // Check ruolo specifico
if ($requiredRole === 'cameriere') {
        if ($userRole === 'cameriere') {
            return true;
        }
    }

    if ($requiredRole === 'cucina') {
        if ($userRole === 'cucina') {
            return true;
        }
    }
    
    // Ruolo non autorizzato
    handleUnauthorized($isApi, "Accesso negato. Ruolo richiesto: $requiredRole");
}

/**
 * Gestisce risposta non autorizzata
 */
function handleUnauthorized($isApi, $message) {
    if ($isApi) {
        // Risposta JSON per API
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'unauthorized',
            'message' => $message,
            'requires_login' => true
        ]);
        exit;
    } else {
        // Redirect HTML per pagine web
        $loginUrl = determineLoginUrl();
        header("Location: $loginUrl");
        exit;
    }
}

/**
 * Determina URL di login appropriato
 */
function determineLoginUrl() {
    return '/ristorantemoka/login.php';
}

/**
 * Check se utente è loggato (senza redirect)
 * 
 * @return bool
 */
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['utente_id']);
}

/**
 * Get user info
 * 
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['utente_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'nome' => $_SESSION['nome'] ?? null,
        'ruolo' => $_SESSION['utente_ruolo'] ?? $_SESSION['ruolo'] ?? null,
        'cameriere_id' => $_SESSION['cameriere_id'] ?? null,
        'codice_cameriere' => $_SESSION['codice_cameriere'] ?? null
    ];
}

/**
 * Check se utente ha ruolo specifico
 * 
 * @param string $role
 * @return bool
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = $_SESSION['utente_ruolo'] ?? 
                $_SESSION['ruolo'] ?? 
                $_SESSION['permessi'] ?? 
                null;
    
    // Admin ha tutti i ruoli
    if ($userRole === 'admin') {
        return true;
    }
    
    return $userRole === $role;
}

/**
 * Logout user
 */
function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Log evento
    if (function_exists('logSecurityEvent')) {
        logSecurityEvent('logout', [
            'user_id' => $_SESSION['utente_id'] ?? null,
            'ruolo' => $_SESSION['ruolo'] ?? null
        ]);
    }
    
    // Pulisci sessione
    $_SESSION = [];
    
    // Distruggi cookie di sessione
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Distruggi sessione
    session_destroy();
}

/**
 * Refresh session (anti-fixation)
 */
function refreshSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}