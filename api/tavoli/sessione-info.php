<?php
/**
 * Sessione Info - VERSIONE CORRETTA
 * 
 * Ritorna info complete:
 * - sessione_tavolo.id (numerico)
 * - sessioni_clienti.id (stringa) per l'ordine
 * 
 * @version 2.0.0
 */

header('Content-Type: application/json; charset=utf-8');
define('ACCESS_ALLOWED', true);
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

// Query per prendere SOLO info sessione_tavolo (NO sessioni_clienti)
$sql = "SELECT 
            st.id as sessione_tavolo_id,
            st.tavolo_id,
            st.stato,
            st.token,
            t.numero AS tavolo_numero
        FROM sessioni_tavolo st
        LEFT JOIN tavoli t ON t.id = st.tavolo_id
        WHERE st.token = ?
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

// Risposta con info sessione_tavolo (NO dati cliente per evitare confusione)
echo json_encode([
    'success'                => true,
    'sessione_tavolo_id'     => (int)$row['sessione_tavolo_id'],
    'sessione_token'         => $token,
    'stato'                  => $row['stato'],
    'tavolo_id'              => (int)$row['tavolo_id'],
    'tavolo_numero'          => $row['tavolo_numero']
]);