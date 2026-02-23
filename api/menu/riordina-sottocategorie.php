<?php
// api/menu/riordina-sottocategorie.php
// Aggiorna il campo "ordine" per le sottocategorie di una singola categoria

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

define('ACCESS_ALLOWED', true);
require_once(__DIR__ . '/../../config/config.php');

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database connection failed'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Metodo non consentito. Usa POST.'
    ]);
    exit;
}

// Legge i dati (form o JSON)
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        }
    }
}

/*
 * Dal JS mandiamo:
 *   categoria_id = 3
 *   ordine_sottocategorie[0] = 10
 *   ordine_sottocategorie[1] = 12
 * ecc.
 */
$categoriaId = isset($input['categoria_id']) ? (int)$input['categoria_id'] : 0;
$ordine      = isset($input['ordine_sottocategorie']) ? $input['ordine_sottocategorie'] : null;

if ($categoriaId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'categoria_id non valido.'
    ]);
    exit;
}

if (!is_array($ordine) || empty($ordine)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Parametro ordine_sottocategorie mancante o non valido.'
    ]);
    exit;
}

$conn->begin_transaction();

try {
    // Per sicurezza, controlliamo che le sottocategorie appartengano a quella categoria
    $stmt = $conn->prepare("UPDATE sottocategorie SET ordine = ? WHERE id = ? AND categoria_id = ?");
    if (!$stmt) {
        throw new Exception('Errore preparazione query: ' . $conn->error);
    }

    $pos = 1;
    foreach ($ordine as $subIdRaw) {
        $subId = (int)$subIdRaw;
        if ($subId <= 0) {
            continue;
        }
        $ordineVal = $pos++;

        $stmt->bind_param('iii', $ordineVal, $subId, $categoriaId);
        if (!$stmt->execute()) {
            throw new Exception('Errore esecuzione query: ' . $stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ordine sottocategorie aggiornato con successo.'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

$conn->close();
