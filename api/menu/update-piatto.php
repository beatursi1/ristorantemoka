<?php
// api/menu/update-piatto.php
// Aggiorna tutti i campi principali di un piatto esistente, inclusa la sottocategoria

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

$id_piatto = isset($input['id_piatto']) ? (int)$input['id_piatto'] : 0;
$nome      = isset($input['nome']) ? trim($input['nome']) : '';
$descr     = isset($input['descrizione']) ? trim($input['descrizione']) : '';
$prezzo    = isset($input['prezzo']) ? str_replace(',', '.', $input['prezzo']) : null;
$tempo     = isset($input['tempo_preparazione']) ? $input['tempo_preparazione'] : null;
$punti     = isset($input['punti_fedelta']) ? $input['punti_fedelta'] : null;
$allergeni = isset($input['allergeni']) ? trim($input['allergeni']) : null;

// NUOVO: sottocategoria_id (può essere vuoto -> NULL)
if (array_key_exists('sottocategoria_id', $input)) {
    $rawSub = trim((string)$input['sottocategoria_id']);
    if ($rawSub === '') {
        $sottocategoriaId = null;
    } else {
        $sottocategoriaId = (int)$rawSub;
        if ($sottocategoriaId <= 0) {
            $sottocategoriaId = null;
        }
    }
} else {
    // se non viene passato, NON modifichiamo la colonna
    $sottocategoriaId = null;
}

if ($id_piatto <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'ID piatto non valido.'
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

// Prezzo
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

// Punti fedeltà (se vuoto -> 1 punto per euro, arrotondato)
if ($punti === null || $punti === '') {
    $puntiVal = (int) round($prezzoVal);
} else {
    $puntiVal = (int)$punti;
    if ($puntiVal < 0) {
        $puntiVal = 0;
    }
}

// Allergeni: se stringa vuota -> NULL
if ($allergeni === '') {
    $allergeni = null;
}

// Prepared statement
// Aggiungiamo la colonna sottocategoria_id
$sql = "
    UPDATE piatti
    SET
        nome = ?,
        descrizione = ?,
        prezzo = ?,
        tempo_preparazione = ?,
        punti_fedelta = ?,
        allergeni = ?,
        sottocategoria_id = ?
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

// bind_param: s = string, d = double, i = integer
// nome (s), descrizione (s), prezzo (d), tempo_preparazione (i/null), punti_fedelta (i), allergeni (s/null), sottocategoria_id (i/null), id (i)
$tempoBind = $tempoVal; // può essere null

// gestiamo sottocategoria_id: se null, usiamo NULL in bind
$sottocatBind = $sottocategoriaId; // può essere null

$stmt->bind_param(
    'ssdiisii',
    $nome,
    $descr,
    $prezzoVal,
    $tempoBind,
    $puntiVal,
    $allergeni,
    $sottocatBind,
    $id_piatto
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

if ($stmt->affected_rows === 0) {
    echo json_encode([
        'success' => true,
        'updated' => false,
        'message' => 'Nessun record aggiornato (ID inesistente o dati invariati).'
    ]);
} else {
    echo json_encode([
        'success' => true,
        'updated' => true,
        'message' => 'Piatto aggiornato correttamente.'
    ]);
}

$stmt->close();
$conn->close();