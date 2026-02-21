<?php
// api/menu/riordina-categorie.php
// Aggiorna il campo "ordine" per le categorie in base alla sequenza inviata dall'admin

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

/**
 * Dal JS mandiamo:
 * ordine_categorie[0] = 3
 * ordine_categorie[1] = 1
 * ...
 * Quindi ci aspettiamo un array in $input['ordine_categorie']
 */
$ordine = isset($input['ordine_categorie']) ? $input['ordine_categorie'] : null;

if (!is_array($ordine) || empty($ordine)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Parametro ordine_categorie mancante o non valido.'
    ]);
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("UPDATE categorie_menu SET ordine = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Errore preparazione query: ' . $conn->error);
    }

    $pos = 1;
    foreach ($ordine as $catIdRaw) {
        $catId = (int)$catIdRaw;
        if ($catId <= 0) {
            continue;
        }
        $ordineVal = $pos++;

        $stmt->bind_param('ii', $ordineVal, $catId);
        if (!$stmt->execute()) {
            throw new Exception('Errore esecuzione query: ' . $stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ordine categorie aggiornato con successo.'
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