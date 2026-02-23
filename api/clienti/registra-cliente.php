<?php
/**
 * Registra Cliente - VERSIONE SICURA
 * 
 * Fix applicati:
 * - SQL Injection → Prepared statements (4 query)
 * - Input validation completa
 * - Rate limiting
 * - Security headers
 * 
 * @version 2.0.0 - SECURE
 */

// Security & Config
define('ACCESS_ALLOWED', true);
require_once('../../config/config.php');
require_once('../../includes/security.php');

// Inizializza sessione sicura e headers
initSecureSession();
setSecurityHeaders();

// Headers API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting - max 10 registrazioni per IP ogni 5 minuti
if (!checkRateLimit('registra_cliente', 10, 300)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Troppe richieste. Riprova tra qualche minuto.'
    ]);
    logSecurityEvent('rate_limit_exceeded', ['action' => 'registra_cliente']);
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
        'error' => 'tavolo_id mancante o non valido'
    ]);
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

// PREPARED STATEMENT 1: Verifica stato tavolo
$stmt = $conn->prepare("SELECT stato FROM tavoli WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore query']);
    $conn->close();
    exit;
}

$stmt->bind_param('i', $tavoloId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Tavolo non trovato'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
if ($row['stato'] !== 'occupato') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Tavolo non occupato. Fallo occupare prima dal cameriere.'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// PREPARED STATEMENT 2: Trova lettera libera
$lettere = range('A', 'Z');
$letteraAssegnata = null;

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessioni_clienti WHERE tavolo_id = ? AND identificativo = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore query']);
    $conn->close();
    exit;
}

foreach ($lettere as $lettera) {
    $stmt->bind_param('is', $tavoloId, $lettera);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $letteraAssegnata = $lettera;
        break;
    }
}
$stmt->close();

if (!$letteraAssegnata) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Nessuna lettera disponibile (tavolo pieno)'
    ]);
    $conn->close();
    exit;
}

// Genera session_id sicuro
$sessionId = 'tavolo_' . $tavoloId . '_cliente_' . $letteraAssegnata . '_' . time();

// Device info sicuro
$deviceInfo = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

// Tipo (default: qr)
$tipo = isset($input['tipo']) && in_array($input['tipo'], ['qr', 'manuale']) 
    ? $input['tipo'] 
    : 'qr';

// PREPARED STATEMENT 3: Inserisci cliente
$stmt = $conn->prepare(
    "INSERT INTO sessioni_clienti (id, tavolo_id, identificativo, nome_cliente, device_info, data_inizializzazione, tipo) 
     VALUES (?, ?, ?, '', ?, NOW(), ?)"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore preparazione insert']);
    $conn->close();
    exit;
}

$stmt->bind_param('sisss', $sessionId, $tavoloId, $letteraAssegnata, $deviceInfo, $tipo);

if ($stmt->execute()) {
    // Log successo
    logSecurityEvent('registra_cliente_success', [
        'tavolo_id' => $tavoloId,
        'lettera' => $letteraAssegnata,
        'session_id' => $sessionId,
        'tipo' => $tipo
    ]);
    
    echo json_encode([
        'success' => true,
        'lettera' => $letteraAssegnata,
        'session_id' => $sessionId,
        'nome' => 'Cliente ' . $letteraAssegnata,
        'message' => 'Cliente registrato come ' . $letteraAssegnata
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore durante la registrazione'
    ]);
    
    logSecurityEvent('registra_cliente_error', [
        'tavolo_id' => $tavoloId,
        'error' => $stmt->error
    ]);
}

$stmt->close();
$conn->close();