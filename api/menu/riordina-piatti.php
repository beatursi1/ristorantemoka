<?php
// api/menu/riordina-piatti.php
// Aggiorna il campo "ordine" per i piatti di una categoria/sottocategoria

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

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
 *   (opzionale) sottocategoria_id = 10  // oppure vuoto / 0 per piatti senza sottocategoria
 *   ordine_piatti[0] = 5
 *   ordine_piatti[1] = 8
 * ecc.
 */
$categoriaId      = isset($input['categoria_id']) ? (int)$input['categoria_id'] : 0;
$sottocategoriaId = isset($input['sottocategoria_id']) ? (int)$input['sottocategoria_id'] : 0;
$ordine           = isset($input['ordine_piatti']) ? $input['ordine_piatti'] : null;

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
        'error'   => 'Parametro ordine_piatti mancante o non valido.'
    ]);
    exit;
}

$conn->begin_transaction();

try {
    // Se sottocategoria_id > 0, aggiorniamo solo piatti con quel sottocategoria_id
    // altrimenti piatti con sottocategoria_id NULL nella categoria indicata
    if ($sottocategoriaId > 0) {
        $stmt = $conn->prepare("UPDATE piatti SET ordine = ? WHERE id = ? AND categoria_id = ? AND sottocategoria_id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE piatti SET ordine = ? WHERE id = ? AND categoria_id = ? AND (sottocategoria_id IS NULL OR sottocategoria_id = 0)");
    }

    if (!$stmt) {
        throw new Exception('Errore preparazione query: ' . $conn->error);
    }

    $pos = 1;
    foreach ($ordine as $piattoIdRaw) {
        $piattoId = (int)$piattoIdRaw;
        if ($piattoId <= 0) {
            continue;
        }
        $ordineVal = $pos++;

        if ($sottocategoriaId > 0) {
            $stmt->bind_param('iiii', $ordineVal, $piattoId, $categoriaId, $sottocategoriaId);
        } else {
            $stmt->bind_param('iii', $ordineVal, $piattoId, $categoriaId);
        }

        if (!$stmt->execute()) {
            throw new Exception('Errore esecuzione query: ' . $stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ordine piatti aggiornato con successo.'
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
