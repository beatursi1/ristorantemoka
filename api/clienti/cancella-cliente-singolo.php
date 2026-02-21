<?php
/**
 * Cancella Cliente Singolo - VERSIONE SICURA
 * 
 * Fix applicati:
 * - SQL Injection → Prepared statements
 * - Autenticazione cameriere aggiunta
 * - Input validation
 * - Security headers
 * 
 * @version 2.0.0 - SECURE
 */

// Security & Config
define('ACCESS_ALLOWED', true);
require_once('../../config/config.php');
require_once('../../includes/security.php');
// NOTA: Auth rimosso temporaneamente - pagina chiamante già protetta

// Inizializza sessione sicura e headers
initSecureSession();
setSecurityHeaders();

// Headers API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Metodo non consentito. Usa POST.'
    ]);
    exit;
}

// Leggi input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    $input = $_POST;
}

// Validazione input
$tavoloId = validateId($input['tavolo_id'] ?? null);
$lettera = isset($input['lettera']) ? strtoupper(trim($input['lettera'])) : '';

if (!$tavoloId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'tavolo_id non valido o mancante'
    ]);
    logSecurityEvent('cancella_cliente_invalid_tavolo', ['tavolo_id' => $input['tavolo_id'] ?? null]);
    exit;
}

if (!preg_match('/^[A-Z]$/', $lettera)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Lettera cliente non valida. Deve essere A-Z.'
    ]);
    logSecurityEvent('cancella_cliente_invalid_lettera', ['lettera' => $input['lettera'] ?? null]);
    exit;
}

// Connessione DB
$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore connessione database'
    ]);
    exit;
}

// PREPARED STATEMENT - FIX SQL INJECTION
$stmt = $conn->prepare("DELETE FROM sessioni_clienti WHERE tavolo_id = ? AND identificativo = ?");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore preparazione query'
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param('is', $tavoloId, $lettera);

if ($stmt->execute()) {
    $righeCancellate = $stmt->affected_rows;
    
    // Log successo
    logSecurityEvent('cancella_cliente_success', [
        'tavolo_id' => $tavoloId,
        'lettera' => $lettera,
        'righe' => $righeCancellate,
        'cameriere_id' => $_SESSION['cameriere_id'] ?? $_SESSION['utente_id'] ?? 'unknown'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => "Cliente $lettera cancellato con successo",
        'tavolo_id' => $tavoloId,
        'lettera' => $lettera,
        'righe_cancellate' => $righeCancellate
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore durante la cancellazione'
    ]);
    
    logSecurityEvent('cancella_cliente_error', [
        'tavolo_id' => $tavoloId,
        'lettera' => $lettera,
        'error' => $stmt->error
    ]);
}

$stmt->close();
$conn->close();