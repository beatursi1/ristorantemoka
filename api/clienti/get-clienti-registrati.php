<?php
/**
 * get-clienti-registrati.php - VERSIONE SICURA E PULITA
 * Restituisce i clienti registrati per un tavolo.
 * 
 * @version 2.0.0 - SECURE
 */

ob_start();
define('ACCESS_ALLOWED', true);
require_once('../../config/config.php');
require_once('../../includes/security.php');
ob_clean();

ini_set('display_errors', 0);
error_reporting(0);

setSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Leggi tavolo_id da GET, POST form, POST JSON
$tavoloId = null;

if (isset($_GET['tavolo_id']) && is_numeric($_GET['tavolo_id'])) $tavoloId = (int)$_GET['tavolo_id'];
elseif (isset($_GET['tavolo']) && is_numeric($_GET['tavolo'])) $tavoloId = (int)$_GET['tavolo'];

if (!$tavoloId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tavolo_id']) && is_numeric($_POST['tavolo_id'])) $tavoloId = (int)$_POST['tavolo_id'];
    elseif (isset($_POST['tavolo']) && is_numeric($_POST['tavolo'])) $tavoloId = (int)$_POST['tavolo'];

    if (!$tavoloId) {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                if (isset($decoded['tavolo_id']) && is_numeric($decoded['tavolo_id'])) $tavoloId = (int)$decoded['tavolo_id'];
                elseif (isset($decoded['tavolo']) && is_numeric($decoded['tavolo'])) $tavoloId = (int)$decoded['tavolo'];
            }
        }
    }
}

if (!$tavoloId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parametro tavolo mancante']);
    exit;
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT id,
                identificativo as lettera,
                nome_cliente as nome,
                tipo,
                DATE_FORMAT(data_inizializzazione, '%H:%i') as ora,
                data_inizializzazione as timestamp
         FROM sessioni_clienti
         WHERE tavolo_id = ?
           AND identificativo IS NOT NULL
           AND identificativo != ''
         ORDER BY data_inizializzazione ASC"
    );
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $result = $stmt->get_result();

    $clienti = [];
    while ($row = $result->fetch_assoc()) {
        $lettera = $row['lettera'];
        $clienti[$lettera] = [
            'id' => $row['id'],
            'lettera' => $lettera,
            'nome' => !empty($row['nome']) ? $row['nome'] : 'Cliente ' . $lettera,
            'tipo' => !empty($row['tipo']) ? $row['tipo'] : 'qr',
            'ora' => $row['ora'],
            'timestamp' => $row['timestamp']
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'clienti' => $clienti,
        'count' => count($clienti),
        'tavolo_id' => $tavoloId,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();