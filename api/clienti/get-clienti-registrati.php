<?php
// get-clienti-registrati.php
// Esteso per accettare tavolo_id da GET, POST (form o JSON) o sessione PHP.
// Restituisce JSON: { success: true, clienti: {...}, count: N, tavolo_id: X, timestamp: ... }

header('Content-Type: application/json; charset=utf-8');
// Nota: Access-Control-Allow-Origin: * può avere implicazioni di sicurezza se esponi dati sensibili.
// Manteniamo l'header per compatibilità con chiamate cross-origin leggere (modifica se richiesto).
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Rispondiamo subito alle preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once('../../config/config.php');

// Helper per output JSON errore
function json_err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// Tentativi di lettura di tavolo_id da più fonti
$tavolo_id = null;

// 1) GET param (es. ?tavolo_id=2 o ?tavolo=2)
if (isset($_GET['tavolo_id']) && is_numeric($_GET['tavolo_id'])) {
    $tavolo_id = (int) $_GET['tavolo_id'];
} elseif (isset($_GET['tavolo']) && is_numeric($_GET['tavolo'])) {
    $tavolo_id = (int) $_GET['tavolo'];
} elseif (isset($_GET['id_tavolo']) && is_numeric($_GET['id_tavolo'])) {
    $tavolo_id = (int) $_GET['id_tavolo'];
}

// 2) POST form-urlencoded / multipart (es. tavolo_id, tavolo)
if (!$tavolo_id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tavolo_id']) && is_numeric($_POST['tavolo_id'])) {
        $tavolo_id = (int) $_POST['tavolo_id'];
    } elseif (isset($_POST['tavolo']) && is_numeric($_POST['tavolo'])) {
        $tavolo_id = (int) $_POST['tavolo'];
    } elseif (isset($_POST['id_tavolo']) && is_numeric($_POST['id_tavolo'])) {
        $tavolo_id = (int) $_POST['id_tavolo'];
    }
}

// 3) POST JSON body
if (!$tavolo_id && ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT')) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (isset($decoded['tavolo_id']) && is_numeric($decoded['tavolo_id'])) {
                $tavolo_id = (int) $decoded['tavolo_id'];
            } elseif (isset($decoded['tavolo']) && is_numeric($decoded['tavolo'])) {
                $tavolo_id = (int) $decoded['tavolo'];
            } elseif (isset($decoded['id_tavolo']) && is_numeric($decoded['id_tavolo'])) {
                $tavolo_id = (int) $decoded['id_tavolo'];
            }
        }
    }
}

// 4) Se non abbiamo tavolo_id, proviamo a risolvere da sessione PHP (se esistente)
if (!$tavolo_id) {
    // Avviamo la sessione se presente una cookie PHPSESSID o se non lo fosse ma vogliamo tentare.
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (!empty($_SESSION['tavolo_id']) && is_numeric($_SESSION['tavolo_id'])) {
        $tavolo_id = (int) $_SESSION['tavolo_id'];
    }
}

// 5) Se ancora mancante, fallback: se presente un token 'sessione' in GET/POST/JSON proviamo a ricavare tavolo.
//    Attenzione: questo dipende da come è modellata la tua applicazione; prova una query minimale su sessioni_clienti.
if (!$tavolo_id) {
    $session_token = null;
    if (isset($_GET['sessione'])) $session_token = $_GET['sessione'];
    elseif (isset($_GET['session'])) $session_token = $_GET['session'];
    elseif (isset($_POST['sessione'])) $session_token = $_POST['sessione'];
    elseif (isset($_POST['session'])) $session_token = $_POST['session'];
    else {
        // da JSON
        if (empty($decoded)) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
        }
        if (!empty($decoded) && is_array($decoded)) {
            if (!empty($decoded['sessione'])) $session_token = $decoded['sessione'];
            elseif (!empty($decoded['session'])) $session_token = $decoded['session'];
            elseif (!empty($decoded['sessione_token'])) $session_token = $decoded['sessione_token'];
        }
    }

    if ($session_token) {
        // Proviamo a risalire al tavolo consultando sessioni_clienti (se la tua struttura è differente adattare la query)
        $conn_test = getDbConnection();
        if ($conn_test) {
            // Tentativo: cerchiamo una riga che contenga questo session token in sessioni_clienti.id o session_id
            $q = "
                SELECT tavolo_id
                FROM sessioni_clienti
                WHERE (id = ? OR session_id = ? OR sessione_token = ?)
                LIMIT 1
            ";
            if ($stmt_test = $conn_test->prepare($q)) {
                $stmt_test->bind_param('sss', $session_token, $session_token, $session_token);
                $stmt_test->execute();
                $res_test = $stmt_test->get_result();
                if ($row_test = $res_test->fetch_assoc()) {
                    if (!empty($row_test['tavolo_id']) && is_numeric($row_test['tavolo_id'])) {
                        $tavolo_id = (int) $row_test['tavolo_id'];
                    }
                }
                $stmt_test->close();
            }
            $conn_test->close();
        }
    }
}

// Se dopo tutti i tentativi non abbiamo tavolo_id valido -> errore
if (!$tavolo_id || !is_numeric($tavolo_id) || $tavolo_id < 1) {
    json_err('Parametro tavolo mancante');
}

// Otteniamo connessione DB
$conn = getDbConnection();
if (!$conn) {
    json_err('Database connection failed', 500);
}

// Query con prepared statement per sicurezza
// Manteniamo la tua query originale (usa 'identificativo' come lettera)
$stmt = $conn->prepare("
    SELECT id,
           identificativo as lettera, 
           nome_cliente as nome, 
           tipo,
           DATE_FORMAT(data_inizializzazione, '%H:%i') as ora,
           data_inizializzazione as timestamp
    FROM sessioni_clienti 
    WHERE tavolo_id = ? 
      AND identificativo IS NOT NULL
      AND identificativo != ''
    ORDER BY data_inizializzazione ASC
");

if (!$stmt) {
    $conn->close();
    json_err('Errore preparazione query', 500);
}

$stmt->bind_param('i', $tavolo_id);
$stmt->execute();
$result = $stmt->get_result();

$clienti = [];
while ($row = $result->fetch_assoc()) {
    // Garantiamo valori coerenti e formattazione sicura
    $lettera = isset($row['lettera']) ? $row['lettera'] : null;
    if (!$lettera) continue;
    $clienti[$lettera] = [
        'id'        => $row['id'],
        'lettera'   => $lettera,
        'nome'      => !empty($row['nome']) ? $row['nome'] : 'Cliente ' . $lettera,
        'tipo'      => !empty($row['tipo']) ? $row['tipo'] : 'qr',
        'ora'       => $row['ora'],
        'timestamp' => $row['timestamp']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'clienti' => $clienti,
    'count' => count($clienti),
    'tavolo_id' => (int) $tavolo_id,
    'timestamp' => date('Y-m-d H:i:s')
]);
exit;
?>