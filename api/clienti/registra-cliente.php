<?php
// registra-cliente.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once('../../config/config.php');

// Input semplificato
$input = json_decode(file_get_contents('php://input'), true);
$tavolo_id = isset($input['tavolo_id']) ? (int)$input['tavolo_id'] : 0;

if ($tavolo_id < 1) {
    echo json_encode(['success' => false, 'error' => 'tavolo_id mancante']);
    exit;
}

$conn = getDbConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Verifica se tavolo è occupato
$result = $conn->query("SELECT stato FROM tavoli WHERE id = $tavolo_id");
if ($row = $result->fetch_assoc()) {
    if ($row['stato'] !== 'occupato') {
        echo json_encode(['success' => false, 'error' => 'Tavolo non occupato']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Tavolo non trovato']);
    exit;
}

// Trova lettera libera (cerca sia clienti QR che manuali nel DB)
$lettere = range('A', 'Z');
foreach ($lettere as $lettera) {
    // Controlla se è già occupata nel database (qualsiasi tipo)
    $result = $conn->query("SELECT COUNT(*) FROM sessioni_clienti WHERE tavolo_id = $tavolo_id AND identificativo = '$lettera'");
    $count = $result->fetch_array()[0];
    
    if ($count == 0) {
        $lettera_assegnata = $lettera;
        break;
    }
}

if (!isset($lettera_assegnata)) {
    echo json_encode(['success' => false, 'error' => 'Nessuna lettera disponibile']);
    exit;
}

// Crea session_id
$session_id = 'tavolo_' . $tavolo_id . '_cliente_' . $lettera_assegnata . '_' . time();

// Inserisce (tipo default 'qr' per registrazione QR)
$device_id = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$tipo = isset($input['tipo']) ? $input['tipo'] : 'qr';
$sql = "INSERT INTO sessioni_clienti (id, tavolo_id, identificativo, nome_cliente, device_info, data_inizializzazione, tipo) 
        VALUES ('$session_id', $tavolo_id, '$lettera_assegnata', '', '$device_id', NOW(), '$tipo')";

if ($conn->query($sql)) {
    echo json_encode([
        'success' => true,
        'lettera' => $lettera_assegnata,
        'session_id' => $session_id,
        'nome' => 'Cliente ' . $lettera_assegnata,
        'message' => 'Cliente registrato come ' . $lettera_assegnata
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Errore inserimento: ' . $conn->error]);
}

$conn->close();
?>