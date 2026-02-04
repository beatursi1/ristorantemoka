<?php
// get-lettere-occupate.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once('../../config/config.php');

$tavolo_id = isset($_GET['tavolo']) ? (int)$_GET['tavolo'] : 0;

if ($tavolo_id < 1) {
    echo json_encode(['success' => false, 'error' => 'Parametro tavolo mancante']);
    exit;
}

$conn = getDbConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Lettere occupate nel database
$lettere_db = [];
$result = $conn->query("SELECT identificativo FROM sessioni_clienti WHERE tavolo_id = $tavolo_id AND identificativo IS NOT NULL");
while ($row = $result->fetch_assoc()) {
    $lettere_db[] = $row['identificativo'];
}

// NOTE: Le lettere occupate manualmente NON sono nel database.
// Dovrebbero essere gestite in un'altra tabella o in memoria.
// Per ora restituiamo solo quelle del database.

$conn->close();

echo json_encode([
    'success' => true,
    'lettere_occupate' => $lettere_db,
    'tavolo_id' => $tavolo_id
]);
?>