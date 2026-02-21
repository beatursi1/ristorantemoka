<?php
// api/menu/crea-piatto.php
// Crea un nuovo piatto nella tabella "piatti"

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

// Solo POST
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

// Campi richiesti
$categoria_id      = isset($input['categoria_id']) ? (int)$input['categoria_id'] : 0;
$sottocategoria_id = isset($input['sottocategoria_id']) && $input['sottocategoria_id'] !== ''
    ? (int)$input['sottocategoria_id']
    : null;
$nome      = isset($input['nome']) ? trim($input['nome']) : '';
$descr     = isset($input['descrizione']) ? trim($input['descrizione']) : '';
$prezzo    = isset($input['prezzo']) ? str_replace(',', '.', $input['prezzo']) : null;
$tempo     = isset($input['tempo_preparazione']) ? $input['tempo_preparazione'] : null;
$punti     = isset($input['punti_fedelta']) ? $input['punti_fedelta'] : null;
$allergeni = isset($input['allergeni']) ? trim($input['allergeni']) : null;

// Validazioni base
if ($categoria_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Categoria non valida.'
    ]);
    exit;
}

if ($nome === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Il nome del piatto è obbligatorio.'
    ]);
    exit;
}

if ($prezzo === null || $prezzo === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Il prezzo è obbligatorio.'
    ]);
    exit;
}

$prezzoVal = floatval($prezzo);
if (!is_numeric($prezzoVal) || $prezzoVal < 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Prezzo non valido.'
    ]);
    exit;
}

// Tempo preparazione (nullable)
$tempoVal = null;
if ($tempo !== null && $tempo !== '') {
    $tempoVal = (int)$tempo;
    if ($tempoVal < 0) {
        $tempoVal = 0;
    }
}

// Punti fedeltà (se vuoto -> 1 punto per euro)
if ($punti === null || $punti === '') {
    $puntiVal = (int) round($prezzoVal);
} else {
    $puntiVal = (int)$punti;
    if ($puntiVal < 0) {
        $puntiVal = 0;
    }
}

// Allergeni vuoti -> NULL
if ($allergeni === '') {
    $allergeni = null;
}

// Per semplicità, nuovo piatto è disponibile = 1 (TRUE)
$disponibile = 1;

// INSERT
$sql = "
    INSERT INTO piatti
        (categoria_id, sottocategoria_id, nome, descrizione, prezzo, tempo_preparazione, punti_fedelta, allergeni, disponibile)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?)
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

// categoria_id (i), sottocategoria_id (i/null), nome (s), descrizione (s), prezzo (d),
// tempo_preparazione (i/null), punti_fedelta (i), allergeni (s/null), disponibile (i)
$tempoBind = $tempoVal;
$stmt->bind_param(
    'iissdiisi',
    $categoria_id,
    $sottocategoria_id,
    $nome,
    $descr,
    $prezzoVal,
    $tempoBind,
    $puntiVal,
    $allergeni,
    $disponibile
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Errore esecuzione query: ' . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$newId = $stmt->insert_id;

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Piatto creato correttamente.',
    'id'      => $newId
]);