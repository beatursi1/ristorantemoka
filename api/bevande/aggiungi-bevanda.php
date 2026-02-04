<?php
// api/bevande/aggiungi-bevanda.php
header('Content-Type: application/json; charset=utf-8');

require_once('../../config/config.php');

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

// verifica JSON valido
if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON non valido']);
    exit;
}

if (!isset($input['bevanda_id'], $input['tavolo_id'], $input['token'], $input['condivisione'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
    exit;
}

$bevandaId = (int)$input['bevanda_id'];
$tavoloId = (int)$input['tavolo_id'];
$token = (string)$input['token'];
$condivisione = (string)$input['condivisione'];
$clienteLettera = isset($input['cliente_lettera']) ? (string)$input['cliente_lettera'] : null;

// validazione minima: id > 0
if ($bevandaId <= 0 || $tavoloId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID bevanda o tavolo non valido']);
    exit;
}

// limitazioni su token e cliente_lettera (hardening)
$token = substr($token, 0, 128); // tronca eventuale input troppo lungo
if ($clienteLettera !== null) {
    $clienteLettera = substr($clienteLettera, 0, 2);
}

// validazione minimale per 'condivisione'
$allowedCondivisione = ['personale', 'tavolo', 'parziale'];
if (!in_array($condivisione, $allowedCondivisione, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valore di condivisione non valido']);
    exit;
}

// Verifica token tavolo
$stmt = $conn->prepare("SELECT id FROM sessioni_tavolo WHERE tavolo_id = ? AND token = ? AND stato = 'attiva'");
if (!$stmt) {
    error_log('[aggiungi-bevanda] prepare failed (sessioni_tavolo) tavolo_id=' . $tavoloId);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore interno']);
    exit;
}
$stmt->bind_param('is', $tavoloId, $token);
if (!$stmt->execute()) {
    error_log('[aggiungi-bevanda] execute failed (sessioni_tavolo): ' . $stmt->error . ' tavolo_id=' . $tavoloId);
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore interno']);
    exit;
}
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token invalido']);
    exit;
}
$stmt->close();

// Recupera cliente_id se fornita la lettera
$clienteId = null;
if ($clienteLettera) {
    $stmt = $conn->prepare("SELECT id FROM sessioni_clienti WHERE tavolo_id = ? AND identificativo = ?");
    if ($stmt) {
        $stmt->bind_param('is', $tavoloId, $clienteLettera);
        if (!$stmt->execute()) {
            error_log('[aggiungi-bevanda] execute failed (sessioni_clienti): ' . $stmt->error . ' tavolo_id=' . $tavoloId . ' cliente=' . $clienteLettera);
            $stmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Errore interno']);
            exit;
        }
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $clienteId = (int)$row['id'];
        }
        $stmt->close();
    } else {
        error_log('[aggiungi-bevanda] prepare failed (sessioni_clienti) tavolo_id=' . $tavoloId . ' cliente=' . $clienteLettera);
    }
}

// Inserisci ordine bevanda gestendo cliente_id NULL correttamente
if ($clienteId === null) {
    $stmt = $conn->prepare("INSERT INTO ordini_bevande (bevanda_id, tavolo_id, cliente_id, condivisione) VALUES (?, ?, NULL, ?)");
    if (!$stmt) {
        error_log('[aggiungi-bevanda] prepare failed (insert NULL cliente) bevanda=' . $bevandaId . ' tavolo=' . $tavoloId);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Errore interno']);
        $conn->close();
        exit;
    }
    // tipi: bevanda (i), tavolo (i), condivisione (s) => 'iis'
    $stmt->bind_param('iis', $bevandaId, $tavoloId, $condivisione);
} else {
    $stmt = $conn->prepare("INSERT INTO ordini_bevande (bevanda_id, tavolo_id, cliente_id, condivisione) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        error_log('[aggiungi-bevanda] prepare failed (insert con cliente) bevanda=' . $bevandaId . ' tavolo=' . $tavoloId . ' clienteId=' . $clienteId);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Errore interno']);
        $conn->close();
        exit;
    }
    // tipi: bevanda (i), tavolo (i), cliente (i), condivisione (s) => 'iiis'
    $stmt->bind_param('iiis', $bevandaId, $tavoloId, $clienteId, $condivisione);
}

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(['success' => true]);
} else {
    error_log('[aggiungi-bevanda] execute failed (insert): ' . $stmt->error . ' bevanda=' . $bevandaId . ' tavolo=' . $tavoloId);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Impossibile inserire ordine']);
}
$stmt->close();
$conn->close();
?>