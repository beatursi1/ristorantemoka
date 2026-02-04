<?php
// sessione-info.php
// Dato un token di sessione, restituisce info sulla SessioneTavolo (tavolo, stato, ecc.)

header('Content-Type: application/json; charset=utf-8');

require_once('../../config/config.php');
$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Errore di connessione al database'
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$token = isset($data['sessione_token']) ? trim($data['sessione_token']) : '';

if ($token === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Parametro sessione_token mancante'
    ]);
    exit;
}

// Trova la sessione per token
$sql = "SELECT s.id, s.tavolo_id, s.stato, t.numero AS tavolo_numero
        FROM sessioni_tavolo s
        LEFT JOIN tavoli t ON t.id = s.tavolo_id
        WHERE s.token = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Errore preparazione query'
    ]);
    exit;
}

$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => 'Sessione non trovata'
    ]);
    $stmt->close();
    exit;
}

$row = $res->fetch_assoc();
$stmt->close();

echo json_encode([
    'success'        => true,
    'sessione_id'    => (int)$row['id'],
    'sessione_token' => $token,
    'stato'          => $row['stato'],
    'tavolo_id'      => (int)$row['tavolo_id'],
    'tavolo_numero'  => $row['tavolo_numero']
]);