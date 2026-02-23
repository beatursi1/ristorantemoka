<?php
/**
 * Sessione Tavolo - VERSIONE SICURA (IDEMPOTENTE)
 * 
 * Crea o recupera una sessione tavolo.
 * Non distrugge sessioni esistenti.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

// Supporta sia JSON che form POST per compatibilità
$input = [];
$rawInput = file_get_contents('php://input');

if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $input = $decoded;
    } else {
        // Fallback: prova a parsare come form data
        parse_str($rawInput, $input);
    }
}

// Fallback su $_POST per compatibilità
if (empty($input)) {
    $input = $_POST;
}

$azione = $input['azione'] ?? 'crea_o_recupera';
$tavoloId = isset($input['tavolo_id']) ? validateId($input['tavolo_id']) : null;

if (!$tavoloId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'tavolo_id mancante']);
    exit;
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

try {
    if ($azione === 'crea_o_recupera') {

        // Cerca sessione attiva esistente
        $stmt = $conn->prepare(
            "SELECT id, token FROM sessioni_tavolo 
             WHERE tavolo_id = ? AND stato = 'attiva' 
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->bind_param('i', $tavoloId);
        $stmt->execute();
        $result = $stmt->get_result();
        $esistente = $result->fetch_assoc();
        $stmt->close();

        if ($esistente) {
            // Restituisce sessione esistente senza modificarla
            logSecurityEvent('sessione_tavolo_recuperata', [
                'tavolo_id' => $tavoloId,
                'sessione_id' => $esistente['id']
            ]);

            echo json_encode([
                'success' => true,
                'sessione_id' => (int)$esistente['id'],
                'sessione_token' => $esistente['token'],
                'gia_esistente' => true,
                'message' => 'Sessione esistente recuperata'
            ]);

        } else {
            // Crea nuova sessione
            $newToken = bin2hex(random_bytes(16));
            $apertoDA = $_SESSION['cameriere_id'] ?? null;

            // Assicura che il tavolo sia occupato
            $stmt = $conn->prepare("UPDATE tavoli SET stato = 'occupato' WHERE id = ?");
            $stmt->bind_param('i', $tavoloId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO sessioni_tavolo (tavolo_id, token, stato, aperta_il, aperta_da) 
                 VALUES (?, ?, 'attiva', NOW(), ?)"
            );
            $stmt->bind_param('isi', $tavoloId, $newToken, $apertoDA);

            if (!$stmt->execute()) {
                throw new Exception("Errore creazione sessione: " . $stmt->error);
            }

            $newId = $conn->insert_id;
            $stmt->close();

            logSecurityEvent('sessione_tavolo_creata', [
                'tavolo_id' => $tavoloId,
                'sessione_id' => $newId
            ]);

            echo json_encode([
                'success' => true,
                'sessione_id' => (int)$newId,
                'sessione_token' => $newToken,
                'gia_esistente' => false,
                'message' => 'Nuova sessione creata'
            ]);
        }

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Azione non valida']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);

    logSecurityEvent('sessione_tavolo_error', [
        'error' => $e->getMessage(),
        'tavolo_id' => $tavoloId
    ]);
}

$conn->close();