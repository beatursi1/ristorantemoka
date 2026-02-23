<?php
// config.php
// CONFIGURAZIONE DATABASE - MODIFICARE QUESTI VALORI
define('DB_HOST', '31.11.39.241');  // Di solito è 'localhost' su Aruba
define('DB_NAME', 'Sql1885819_5');  // Lo scopriremo dopo
define('DB_USER', 'Sql1885819');      // Lo scopriremo dopo  
define('DB_PASS', 'pL87@ss%jedb@W');    // Lo scopriremo dopo

// Connessione al database
function getDbConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connessione fallita: " . $conn->connect_error);
        }
        
        // Imposta il charset a UTF8
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        // In produzione, loggheresti l'errore senza mostrarlo all'utente
        error_log("Database error: " . $e->getMessage());
        return null;
    }
}

// Funzione di test connessione (da rimuovere in produzione)
function testDbConnection() {
    $conn = getDbConnection();
    if ($conn) {
        return ['status' => 'success', 'message' => 'Connesso a MySQL ' . $conn->server_version];
    } else {
        return ['status' => 'error', 'message' => 'Impossibile connettersi al database'];
    }
}
?>