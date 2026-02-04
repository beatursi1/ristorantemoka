<?php
// sessione-tavolo.php
// Gestisce la creazione di una nuova SessioneTavolo per un tavolo (sempre nuovo token).

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once('../../config/config.php');
$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'Errore di connessione al database'
    ]);
    exit;
}

// Helper risposta JSON
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'success' => false,
        'error'   => 'Metodo non consentito. Usa POST.'
    ], 405);
}

$azione = isset($_POST['azione']) ? $_POST['azione'] : '';

if ($azione === 'crea_o_recupera') {
    $tavolo_id = isset($_POST['tavolo_id']) ? intval($_POST['tavolo_id']) : 0;
    if ($tavolo_id <= 0) {
        json_response([
            'success' => false,
            'error'   => 'Parametro tavolo_id mancante o non valido.'
        ], 400);
    }

    // 0) Chiudi eventuali sessioni ancora "attive" per questo tavolo
    $sql_chiudi = "UPDATE sessioni_tavolo 
                   SET stato = 'chiusa'
                   WHERE tavolo_id = ? AND stato = 'attiva'";
    if ($stmt_chiudi = $conn->prepare($sql_chiudi)) {
        $stmt_chiudi->bind_param('i', $tavolo_id);
        $stmt_chiudi->execute();
        $stmt_chiudi->close();
    }

    // 1) Genera SEMPRE una nuova SessioneTavolo (nuovo token ogni volta)
    function genera_token_sessione($length = 16) {
        return bin2hex(random_bytes($length)); // 16 byte = 32 caratteri hex
    }

    $token = genera_token_sessione(16);

    // ID cameriere se esiste in sessione, altrimenti NULL
    $aperta_da = isset($_SESSION['cameriere_id']) ? intval($_SESSION['cameriere_id']) : null;

    $sql_ins = "INSERT INTO sessioni_tavolo (tavolo_id, token, stato, aperta_il, aperta_da)
                VALUES (?, ?, 'attiva', NOW(), ?)";
    $stmt_ins = $conn->prepare($sql_ins);
    if (!$stmt_ins) {
        json_response([
            'success' => false,
            'error'   => 'Errore nella preparazione della query (insert): ' . $conn->error
        ], 500);
    }

    // Bind param: sempre 'iss' (tavolo_id int, token string, aperta_da string/NULL)
    $stmt_ins->bind_param('iss', $tavolo_id, $token, $aperta_da);

    if (!$stmt_ins->execute()) {
        $errore = $stmt_ins->error;
        $stmt_ins->close();

        // Log di debug su file
        file_put_contents(__DIR__ . '/debug_sessione_tavolo.log',
            date('Y-m-d H:i:s') . ' - Errore insert sessioni_tavolo: ' . $errore . PHP_EOL,
            FILE_APPEND
        );

        json_response([
            'success' => false,
            'error'   => 'Errore nell\'inserimento della SessioneTavolo: ' . $errore
        ], 500);
    }

    $sessione_id = $stmt_ins->insert_id;
    $stmt_ins->close();

    json_response([
        'success'        => true,
        'sessione_id'    => (int)$sessione_id,
        'sessione_token' => $token,
        'gia_esistente'  => false
    ]);
}

// Azione non riconosciuta
json_response([
    'success' => false,
    'error'   => 'Azione non valida.'
], 400);
?>