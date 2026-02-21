<?php
/**
 * Security Helper Functions
 * 
 * Funzioni di sicurezza centralizzate per RistoranteMoka.
 * Questo file AGGIUNGE sicurezza senza modificare file esistenti.
 * 
 * @version 1.0.0
 */

// Previeni accesso diretto
if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

// ============================================
// CSRF PROTECTION
// ============================================

/**
 * Genera token CSRF
 */
function generateCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    
    return $token;
}

/**
 * Valida token CSRF
 */
function validateCsrfToken(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Token mancante
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Token scaduto (2 ore)
    if (isset($_SESSION['csrf_token_time']) && 
        (time() - $_SESSION['csrf_token_time']) > 7200) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    // Confronto sicuro
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * HTML field per CSRF token
 */
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
}

// ============================================
// INPUT SANITIZATION
// ============================================

/**
 * Sanitizza input per prevenire XSS
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Valida e sanitizza email
 */
function sanitizeEmail(string $email): ?string {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

/**
 * Valida e sanitizza intero
 */
function sanitizeInt($value): int {
    return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Valida e sanitizza float
 */
function sanitizeFloat($value): float {
    return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

// ============================================
// SQL INJECTION PREVENTION
// ============================================

/**
 * Escape string per mysqli (backward compatibility)
 * 
 * NOTA: Usa prepared statements quando possibile!
 * Questa è solo per codice legacy.
 */
function escapeString($value): string {
    $conn = getDbConnection();
    if (!$conn) {
        return addslashes($value);
    }
    return $conn->real_escape_string($value);
}

/**
 * Valida ID numerico
 */
function validateId($id): ?int {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    return ($id && $id > 0) ? $id : null;
}

// ============================================
// SESSION SECURITY
// ============================================

/**
 * Inizializza sessione sicura
 */
function initSecureSession(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    
    // Configurazione sicura
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1'); // Solo HTTPS
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    
    session_start();
    
    // Rigenera ID periodicamente
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) { // 30 minuti
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Session fixation protection
    // NOTA: User agent check disabilitato per compatibilità API
    // Le app mobile/PWA possono cambiare user agent legittimamente
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    // User agent check DISABILITATO - troppo restrittivo per API/mobile
    /*
    else if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        session_destroy();
        die('Session hijacking detected');
    }
    */
}

/**
 * Distruggi sessione in modo sicuro
 */
function destroySecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

// ============================================
// RATE LIMITING
// ============================================

/**
 * Check rate limit (semplice implementazione basata su sessione)
 */
function checkRateLimit(string $action, int $maxAttempts = 5, int $timeWindow = 300): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $key = 'rate_limit_' . $action;
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start' => $now];
        return true;
    }
    
    $data = $_SESSION[$key];
    
    // Reset se finestra scaduta
    if ($now - $data['start'] > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'start' => $now];
        return true;
    }
    
    // Incrementa contatore
    $_SESSION[$key]['count']++;
    
    // Check limite
    return $_SESSION[$key]['count'] <= $maxAttempts;
}

// ============================================
// PASSWORD SECURITY
// ============================================

/**
 * Hash password in modo sicuro
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 2
    ]);
}

/**
 * Verifica password
 */
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Check se password necessita rehash
 */
function needsRehash(string $hash): bool {
    return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 2
    ]);
}

// ============================================
// SECURITY LOGGING
// ============================================

/**
 * Log evento di sicurezza
 */
function logSecurityEvent(string $event, array $context = []): void {
    $logDir = dirname(__DIR__) . '/logs';
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $logEntry = sprintf(
        "[%s] IP: %s | Event: %s | Context: %s | UA: %s\n",
        $timestamp,
        $ip,
        $event,
        json_encode($context),
        $userAgent
    );
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// ============================================
// HEADERS SECURITY
// ============================================

/**
 * Imposta security headers
 */
function setSecurityHeaders(): void {
    // Previeni clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Previeni MIME sniffing
    header('X-Content-Type-Options: nosniff');
    
    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Content Security Policy (base)
    header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:;");
    
    // HSTS (solo se HTTPS è configurato)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ============================================
// IP VALIDATION
// ============================================

/**
 * Ottieni IP reale del client
 */
function getClientIP(): string {
    $headers = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            // Se X-Forwarded-For contiene multipli IP, prendi il primo
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}

/**
 * Verifica se IP è nella blacklist
 */
function isBlacklistedIP(string $ip): bool {
    // TODO: Implementare check su database/file blacklist
    $blacklist = [
        // Aggiungi IP da bloccare qui
    ];
    
    return in_array($ip, $blacklist);
}