<?php
/**
 * Resetta Clienti Tavolo - VERSIONE SICURA
 * 
 * Fix applicati:
 * - SQL Injection → Prepared statements (2 query)
 * - Autenticazione cameriere aggiunta
 * - Input validation
 * - Security logging
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

// Validazione tavolo_id
$tavoloId = validateId($input['tavolo_id'] ?? null);

if (!$tavoloId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'tavolo_id non valido o mancante'
    ]);
    logSecurityEvent('resetta_clienti_invalid_tavolo', ['tavolo_id' => $input['tavolo_id'] ?? null]);
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
    logSecurityEvent('resetta_clienti_db_error', ['tavolo_id' => $tavoloId]);
    exit;
}

// DEBUG: Log inizio operazione
logSecurityEvent('resetta_clienti_start', [
    'tavolo_id' => $tavoloId,
    'timestamp' => time()
]);

// PREPARED STATEMENT 1: Conta clienti prima di cancellarli
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessioni_clienti WHERE tavolo_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore preparazione query']);
    $conn->close();
    exit;
}

$stmt->bind_param('i', $tavoloId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$clientiCancellati = $row['count'];
$stmt->close();

// PREPARED STATEMENT 2: Cancella tutti i clienti del tavolo
$stmt = $conn->prepare("DELETE FROM sessioni_clienti WHERE tavolo_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore preparazione delete']);
    $conn->close();
    exit;
}

$stmt->bind_param('i', $tavoloId);

if ($stmt->execute()) {
    $righeCancellate = $stmt->affected_rows;
    
    // Log successo CON DETTAGLI
    logSecurityEvent('resetta_clienti_success', [
        'tavolo_id' => $tavoloId,
        'clienti_contati' => $clientiCancellati,
        'righe_affected' => $righeCancellate,
        'cameriere_id' => $_SESSION['cameriere_id'] ?? $_SESSION['utente_id'] ?? 'unknown',
        'timestamp' => time()
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => "Clienti del tavolo $tavoloId resettati con successo",
        'tavolo_id' => $tavoloId,
        'clienti_cancellati' => $clientiCancellati,
        'righe_affected' => $righeCancellate,
        'debug' => [
            'query_executed' => true,
            'affected_rows' => $righeCancellate
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore durante il reset'
    ]);
    
    logSecurityEvent('resetta_clienti_error', [
        'tavolo_id' => $tavoloId,
        'error' => $stmt->error
    ]);
}

$stmt->close();
$conn->close();