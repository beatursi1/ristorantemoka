<?php
// salva-cliente-manuale.php
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
$lettera   = isset($input['lettera']) ? strtoupper($input['lettera']) : '';
$nome      = isset($input['nome']) ? trim($input['nome']) : '';
// NOTA: non leggiamo più "tipo" dall'input; lo decideremo noi sotto

// Validazioni
if ($tavolo_id < 1) {
    echo json_encode(['success' => false, 'error' => 'tavolo_id mancante']);
    exit;
}

if (!preg_match('/^[A-Z]$/', $lettera)) {
    echo json_encode(['success' => false, 'error' => 'lettera non valida']);
    exit;
}

$conn = getDbConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Verifica se il tavolo esiste ed è occupato
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

// Crea session_id per cliente manuale
$session_id = 'man_' . $tavolo_id . '_' . $lettera . '_' . substr(time(), -6);
$device_id = 'MANUALE';

// Verifica se esiste già un cliente con questa lettera (anche di tipo diverso)
$check_sql = "SELECT id FROM sessioni_clienti WHERE tavolo_id = $tavolo_id AND identificativo = '$lettera'";
$check_result = $conn->query($check_sql);

if ($check_result->num_rows > 0) {
    // Cliente già esistente (QR o manuale): aggiorniamo SOLO il nome.
    $sql = "UPDATE sessioni_clienti 
            SET nome_cliente = '$nome'
            WHERE tavolo_id = $tavolo_id AND identificativo = '$lettera'";
    
    $action = 'aggiornato';
    $tipoEffettivo = null; // il tipo resta quello già presente in DB
} else {
    // Cliente non esiste: creiamo un nuovo cliente MANUALE
    $tipoNuovo = 'manuale';
    $sql = "INSERT INTO sessioni_clienti (id, tavolo_id, identificativo, nome_cliente, device_info, data_inizializzazione, tipo) 
            VALUES ('$session_id', $tavolo_id, '$lettera', '$nome', '$device_id', NOW(), '$tipoNuovo')";
    
    $action = 'creato';
    $tipoEffettivo = $tipoNuovo;
}

if ($conn->query($sql)) {
    echo json_encode([
        'success' => true,
        'lettera' => $lettera,
        'nome'    => $nome,
        // Se è stato creato nuovo, sappiamo che è manuale; se aggiornato, il tipo resta quello esistente in DB
        'tipo'    => $tipoEffettivo,
        'action'  => $action,
        'message' => 'Cliente ' . $action . ' come ' . $lettera
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Errore database: ' . $conn->error]);
}

$conn->close();
?>