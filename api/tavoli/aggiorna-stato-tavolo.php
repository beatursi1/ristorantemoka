<?php
// aggiorna-stato-tavolo.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

// Abilita error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../config/config.php');

// Leggi l'input JSON
$json = file_get_contents('php://input');
$input = json_decode($json, true);

// Se non riesce a leggere JSON, logga l'errore
if ($input === null && $json !== '') {
    error_log("ERRORE JSON: " . $json);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Log per debug
error_log("API chiamata con: " . $json);

if (empty($input['tavolo_id'])) {
    echo json_encode(['success' => false, 'error' => 'tavolo_id mancante']);
    exit;
}

$tavolo_id = (int)$input['tavolo_id'];
$nuovo_stato = $input['nuovo_stato'] ?? 'occupato';

$conn = getDbConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Aggiorna stato tavolo
$stmt = $conn->prepare("UPDATE tavoli SET stato = ? WHERE id = ?");
$stmt->bind_param("si", $nuovo_stato, $tavolo_id);

if ($stmt->execute()) {
    $response = [
        'success' => true,
        'message' => 'Stato tavolo aggiornato',
        'tavolo_id' => $tavolo_id,
        'nuovo_stato' => $nuovo_stato
    ];
} else {
    $response = ['success' => false, 'error' => 'Errore aggiornamento: ' . $stmt->error];
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>