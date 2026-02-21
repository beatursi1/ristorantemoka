<?php
/**
 * Database Configuration - ARUBA SHARED HOSTING SECURE
 * 
 * ISTRUZIONI INSTALLAZIONE:
 * ========================
 * 1. Questo file va in: /www.ilmioqrcode.it/ristorantemoka/config/config.php
 * 
 * 2. Crea .htaccess nella stessa cartella (vedi file separato)
 * 
 * 3. Imposta permessi: 644 (su Aruba condiviso non puoi usare 600)
 * 
 * 4. MODIFICA le credenziali qui sotto
 * 
 * 5. VERIFICA che https://www.ilmioqrcode.it/ristorantemoka/config/config.php
 *    ritorni "403 Forbidden" o "404 Not Found"
 * 
 * @version 2.0.1 - ARUBA SHARED HOSTING
 */

// =============================================================================
// PROTEZIONE ACCESSO DIRETTO
// =============================================================================

// Blocca accesso diretto al file via browser
// Questo file può essere incluso SOLO da altri script PHP
if (!defined('ACCESS_ALLOWED')) {
    // Se qualcuno prova ad accedere direttamente, blocca e logga
    error_log('[SECURITY] Tentativo accesso diretto a config.php da IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    http_response_code(403);
    die('Access Denied');
}

// =============================================================================
// DATABASE CREDENTIALS
// =============================================================================

define('DB_HOST', '31.11.39.241');
define('DB_NAME', 'Sql1885819_5');
define('DB_USER', 'Sql1885819');
define('DB_PASS', 'pL87@ss%jedb@W');  // ⚠️ CAMBIA QUESTO!

// =============================================================================
// SECURITY VALIDATION
// =============================================================================

/**
 * Verifica che le credenziali siano state configurate
 * Esegue automaticamente al caricamento del file
 */
function validateDatabaseConfig() {
    $errors = [];
    
    if (DB_PASS === 'INSERISCI_PASSWORD_REALE_QUI' || DB_PASS === '') {
        $errors[] = 'DB_PASS non configurato';
    }
    
    if (DB_HOST === '' || DB_NAME === '' || DB_USER === '') {
        $errors[] = 'Credenziali database incomplete';
    }
    
    if (!empty($errors)) {
        error_log('[CONFIG ERROR] ' . implode(', ', $errors));
        
        // In sviluppo mostra errore, in produzione silenzioso
        if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) {
            die('ERRORE CONFIG: ' . implode(', ', $errors));
        }
        
        return false;
    }
    
    return true;
}

// Esegui validazione
validateDatabaseConfig();

// =============================================================================
// DATABASE CONNECTION
// =============================================================================

/**
 * Ottiene connessione database con singleton pattern
 * 
 * Sicurezza:
 * - Charset UTF8MB4 (previene SQL injection multibyte)
 * - Singleton (una sola connessione riutilizzata)
 * - Error logging server-side
 * - Nessun dettaglio tecnico esposto
 * 
 * @return mysqli|null Connessione o null se fallisce
 */
function getDbConnection() {
    static $conn = null;
    
    // Riusa connessione esistente (singleton)
    if ($conn !== null && $conn->ping()) {
        return $conn;
    }
    
    try {
        // Nuova connessione
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed");
        }
        
        // UTF8MB4 per sicurezza e supporto emoji
        if (!$conn->set_charset("utf8mb4")) {
            throw new Exception("Charset setting failed");
        }
        
        return $conn;
        
    } catch (Exception $e) {
        // Log server-side (solo admin vede)
        error_log('[DB ERROR] ' . date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . ' from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'CLI'));
        
        $conn = null;
        return null;
    }
}

// =============================================================================
// ENVIRONMENT INFO (per debug - rimuovere in produzione stabile)
// =============================================================================

/**
 * Rileva se siamo in ambiente di sviluppo o produzione
 * 
 * @return bool True se ambiente di sviluppo
 */
function isDevelopment() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return (
        strpos($host, 'localhost') !== false ||
        strpos($host, '.local') !== false ||
        strpos($host, '.test') !== false ||
        strpos($host, '127.0.0.1') !== false
    );
}

// =============================================================================
// AUTO-CHECK CONNESSIONE (solo per installazione iniziale)
// =============================================================================

// Questo blocco esegue SOLO la prima volta per verificare config
// Dopo aver verificato che funziona, commenta o rimuovi questo blocco

if (defined('CONFIG_FIRST_INSTALL_CHECK') && CONFIG_FIRST_INSTALL_CHECK === true) {
    $testConn = getDbConnection();
    
    if ($testConn === null) {
        error_log('[CONFIG] Test connessione FALLITO - verifica credenziali');
        
        if (isDevelopment()) {
            die('Errore connessione database. Verifica config.php');
        }
    } else {
        error_log('[CONFIG] Test connessione OK - MySQL ' . $testConn->server_version);
    }
}
