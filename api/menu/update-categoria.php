<?php
// api/menu/update-categoria.php
// Aggiorna i dati principali di una categoria (nome, visibile)

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

$id       = isset($input['id']) ? (int)$input['id'] : 0;
$nome     = isset($input['nome']) ? trim($input['nome']) : '';
$visibile = isset($input['visibile']) ? (int)$input['visibile'] : 1;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'ID categoria non valido.'
    ]);
    exit;
}

if ($nome === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Il nome della categoria è obbligatorio.'
    ]);
    exit;
}

$sql = "
    UPDATE categorie_menu
    SET nome = ?, visibile = ?
    WHERE id = ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Errore preparazione query: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param('sii', $nome, $visibile, $id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Errore esecuzione query: ' . $stmt->error
    ]);
    $stmt->close();
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Categoria aggiornata correttamente.'
]);

$stmt->close();
$conn->close();