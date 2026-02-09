<?php
// api/menu/crea-sottocategoria.php
// Crea una nuova sottocategoria per una data categoria

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

$categoriaId = isset($input['categoria_id']) ? (int)$input['categoria_id'] : 0;
$nome        = isset($input['nome']) ? trim($input['nome']) : '';
$visibile    = isset($input['visibile']) ? (int)$input['visibile'] : 1;

if ($categoriaId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'categoria_id non valido.'
    ]);
    exit;
}

if ($nome === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Il nome della sottocategoria è obbligatorio.'
    ]);
    exit;
}

// Troviamo il prossimo ordine disponibile per le sottocategorie di questa categoria
$sqlMax = "SELECT COALESCE(MAX(ordine), 0) AS max_ordine FROM sottocategorie WHERE categoria_id = ?";
$stmtMax = $conn->prepare($sqlMax);
if (!$stmtMax) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Errore preparazione query max ordine: ' . $conn->error
    ]);
    exit;
}

$stmtMax->bind_param('i', $categoriaId);
if (!$stmtMax->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Errore esecuzione query max ordine: ' . $stmtMax->error
    ]);
    $stmtMax->close();
    exit;
}

$resMax = $stmtMax->get_result();
$rowMax = $resMax->fetch_assoc();
$stmtMax->close();

$nextOrdine = (int)$rowMax['max_ordine'] + 1;

// Inseriamo la sottocategoria
$sqlInsert = "
    INSERT INTO sottocategorie (categoria_id, nome, ordine, visibile)
    VALUES (?, ?, ?, ?)
";

$stmt = $conn->prepare($sqlInsert);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Errore preparazione insert: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param('isii', $categoriaId, $nome, $nextOrdine, $visibile);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Errore esecuzione insert: ' . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$newId = $stmt->insert_id;

echo json_encode([
    'success' => true,
    'message' => 'Sottocategoria creata correttamente.',
    'id'      => $newId,
    'ordine'  => $nextOrdine
]);

$stmt->close();
$conn->close();