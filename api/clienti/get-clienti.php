<?php
/**
 * get-clienti.php - VERSIONE CORRETTA
 * Restituisce i clienti registrati per un tavolo/sessione.
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
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$tavoloId = null;
$sessione = null;

// Leggi da GET
if (isset($_GET['tavolo']) && is_numeric($_GET['tavolo'])) $tavoloId = (int)$_GET['tavolo'];
if (isset($_GET['tavolo_id']) && is_numeric($_GET['tavolo_id'])) $tavoloId = (int)$_GET['tavolo_id'];
if (isset($_GET['sessione'])) $sessione = sanitizeInput($_GET['sessione']);

// Leggi da POST JSON
$raw = file_get_contents('php://input');
if (!empty($raw)) {
    $input = json_decode($raw, true);
    if (is_array($input)) {
        if (isset($input['tavolo_id']) && is_numeric($input['tavolo_id'])) $tavoloId = (int)$input['tavolo_id'];
        if (isset($input['tavolo']) && is_numeric($input['tavolo'])) $tavoloId = (int)$input['tavolo'];
        if (isset($input['sessione'])) $sessione = sanitizeInput($input['sessione']);
    }
}

// Risolvi tavolo_id da token sessione
if (!$tavoloId && $sessione) {
    $conn = getDbConnection();
    if ($conn) {
        $stmt = $conn->prepare("SELECT tavolo_id FROM sessioni_tavolo WHERE token = ? LIMIT 1");
        $stmt->bind_param('s', $sessione);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) $tavoloId = (int)$row['tavolo_id'];
        $stmt->close();
        $conn->close();
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
        "SELECT id, identificativo as lettera, nome_cliente as nome, tipo,
                DATE_FORMAT(data_inizializzazione, '%H:%i') as ora
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
    $numero_coperti = 0;

    while ($row = $result->fetch_assoc()) {
        $lettera = $row['lettera'];
        $clienti[] = [
            'lettera' => $lettera,
            'nome' => $row['nome'] ?: 'Cliente ' . $lettera,
            'tipo' => $row['tipo'] ?? 'qr',
            'ora' => $row['ora'],
            'attivo' => true
        ];
        $numero_coperti++;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'tavolo' => $tavoloId,
        'sessione' => $sessione,
        'numero_coperti' => $numero_coperti,
        'clienti' => $clienti,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();