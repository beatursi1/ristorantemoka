<?php
/**
 * elimina-tavolo.php - VERSIONE SICURA
 * Elimina un tavolo solo se è libero.
 * Solo admin autorizzati.
 * 
 * @version 2.0.0 - SECURE
 */

ob_start();
define('ACCESS_ALLOWED', true);
require_once('../../config/config.php');
require_once('../../includes/security.php');
require_once('../../includes/auth.php');
ob_clean();

ini_set('display_errors', 0);
error_reporting(0);

initSecureSession();
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

// Solo admin
checkAccess('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tavoloId = isset($input['id']) ? validateId($input['id']) : null;

if (!$tavoloId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID tavolo mancante']);
    exit;
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

try {
    // Verifica che il tavolo esista e sia libero
    $stmt = $conn->prepare("SELECT id, numero, stato FROM tavoli WHERE id = ?");
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Tavolo non trovato');
    }

    $tavolo = $result->fetch_assoc();
    $stmt->close();

    if ($tavolo['stato'] !== 'libero') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Impossibile eliminare un tavolo occupato. Liberalo prima.'
        ]);
        exit;
    }

    // Elimina il tavolo
    $stmt = $conn->prepare("DELETE FROM tavoli WHERE id = ? AND stato = 'libero'");
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('Eliminazione fallita');
    }
    $stmt->close();

    logSecurityEvent('elimina_tavolo', [
        'tavolo_id' => $tavoloId,
        'tavolo_numero' => $tavolo['numero'],
        'ip' => getClientIP()
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Tavolo ' . $tavolo['numero'] . ' eliminato con successo'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);

    logSecurityEvent('elimina_tavolo_error', [
        'error' => $e->getMessage(),
        'tavolo_id' => $tavoloId
    ]);
}

$conn->close();