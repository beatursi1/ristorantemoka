<?php
// cancella-cliente-singolo.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once('../../config/config.php');

$input = json_decode(file_get_contents('php://input'), true);

$tavolo_id = isset($input['tavolo_id']) ? (int)$input['tavolo_id'] : 0;
$lettera = isset($input['lettera']) ? strtoupper($input['lettera']) : '';

if ($tavolo_id < 1 || !preg_match('/^[A-Z]$/', $lettera)) {
    echo json_encode(['success' => false, 'error' => 'Parametri non validi']);
    exit;
}

$conn = getDbConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Cancella il cliente specifico
$sql = "DELETE FROM sessioni_clienti WHERE tavolo_id = $tavolo_id AND identificativo = '$lettera'";

if ($conn->query($sql)) {
    $righeCancellate = $conn->affected_rows;
    echo json_encode([
        'success' => true,
        'message' => "Cliente $lettera cancellato",
        'tavolo_id' => $tavolo_id,
        'lettera' => $lettera,
        'righe_cancellate' => $righeCancellate
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Errore database: ' . $conn->error]);
}

$conn->close();
?>