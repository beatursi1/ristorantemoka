<?php
// resetta-clienti-tavolo.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once('../../config/config.php');

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['tavolo_id'])) {
    echo json_encode(['success' => false, 'error' => 'tavolo_id mancante']);
    exit;
}

$tavolo_id = (int)$input['tavolo_id'];

$conn = getDbConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Conta clienti prima della cancellazione
$result = $conn->query("SELECT COUNT(*) as count FROM sessioni_clienti WHERE tavolo_id = $tavolo_id");
$row = $result->fetch_assoc();
$clienti_cancellati = $row['count'];

// Cancella i clienti
if ($conn->query("DELETE FROM sessioni_clienti WHERE tavolo_id = $tavolo_id")) {
    $response = [
        'success' => true,
        'message' => 'Clienti resettati',
        'tavolo_id' => $tavolo_id,
        'clienti_cancellati' => $clienti_cancellati
    ];
} else {
    $response = ['success' => false, 'error' => 'Errore cancellazione: ' . $conn->error];
}

$conn->close();

echo json_encode($response);
?>