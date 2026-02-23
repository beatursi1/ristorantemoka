<?php
/**
 * sessione-clienti.php - VERSIONE SICURA E SEMPLIFICATA
 * Restituisce la lista dei clienti per una sessione/tavolo.
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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? [];

$sessioneToken = isset($input['sessione']) ? sanitizeInput($input['sessione']) : null;
$tavoloId = isset($input['tavolo_id']) && is_numeric($input['tavolo_id']) ? (int)$input['tavolo_id'] : null;
$sessioneClienteId = isset($input['sessione_cliente_id']) ? sanitizeInput($input['sessione_cliente_id']) : null;

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

try {
    // Risolvi tavolo_id da token sessione se non fornito
    if (!$tavoloId && $sessioneToken) {
        $stmt = $conn->prepare(
            "SELECT tavolo_id FROM sessioni_tavolo WHERE token = ? LIMIT 1"
        );
        $stmt->bind_param('s', $sessioneToken);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) $tavoloId = (int)$row['tavolo_id'];
        $stmt->close();
    }

    // Risolvi tavolo_id da sessione_cliente_id se non ancora trovato
    if (!$tavoloId && $sessioneClienteId) {
        $stmt = $conn->prepare(
            "SELECT tavolo_id FROM sessioni_clienti WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('s', $sessioneClienteId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) $tavoloId = (int)$row['tavolo_id'];
        $stmt->close();
    }

    if (!$tavoloId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Impossibile determinare il tavolo']);
        exit;
    }

    // Recupera clienti del tavolo
    $stmt = $conn->prepare(
        "SELECT identificativo as lettera, nome_cliente as nome, tipo
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
        $clienti[] = [
            'id' => $lettera,
            'nome' => $row['nome'] ?: 'Cliente ' . $lettera,
            'tipo' => $row['tipo'] ?? 'qr'
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'clienti' => $clienti
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();