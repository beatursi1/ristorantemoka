<?php
/**
 * Get Lettere Occupate - VERSIONE SICURA
 * 
 * Fix applicati:
 * - SQL Injection → Prepared statement
 * - Input validation
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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Leggi tavolo_id da GET o POST
$tavoloId = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tavoloId = validateId($_GET['tavolo_id'] ?? null);
} else {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!is_array($input)) {
        $input = $_POST;
    }
    
    $tavoloId = validateId($input['tavolo_id'] ?? null);
}

if (!$tavoloId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'tavolo_id non valido o mancante',
        'lettere' => []
    ]);
    exit;
}

// Connessione DB
$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore connessione database',
        'lettere' => []
    ]);
    exit;
}

// PREPARED STATEMENT - FIX SQL INJECTION
$stmt = $conn->prepare(
    "SELECT identificativo 
     FROM sessioni_clienti 
     WHERE tavolo_id = ? AND identificativo IS NOT NULL 
     ORDER BY identificativo"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore preparazione query',
        'lettere' => []
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param('i', $tavoloId);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore esecuzione query',
        'lettere' => []
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$result = $stmt->get_result();
$lettere = [];

while ($row = $result->fetch_assoc()) {
    if (!empty($row['identificativo'])) {
        $lettere[] = $row['identificativo'];
    }
}

echo json_encode([
    'success' => true,
    'tavolo_id' => $tavoloId,
    'lettere' => $lettere,
    'count' => count($lettere)
]);

$stmt->close();
$conn->close();