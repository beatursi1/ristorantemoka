<?php
/**
 * Aggiorna Stato Tavolo - VERSIONE SICURA
 * 
 * Aggiorna lo stato di un tavolo e gestisce le sessioni.
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

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Input non valido']);
    exit;
}

$tavoloId = isset($input['tavolo_id']) ? validateId($input['tavolo_id']) : null;
$nuovoStato = isset($input['nuovo_stato']) ? sanitizeInput($input['nuovo_stato']) : null;

if (!$tavoloId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'tavolo_id mancante']);
    exit;
}

// Valida lo stato
$statiValidi = ['occupato', 'libero', 'prenotato', 'fuori_servizio'];
if (!$nuovoStato || !in_array($nuovoStato, $statiValidi)) {
    $nuovoStato = 'occupato'; // default
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

$conn->begin_transaction();

try {
    // Verifica che il tavolo esista
    $stmt = $conn->prepare("SELECT id, numero, stato FROM tavoli WHERE id = ?");
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Tavolo non trovato: $tavoloId");
    }

    $tavolo = $result->fetch_assoc();
    $vecchioStato = $tavolo['stato'];
    $stmt->close();

    $sessioneToken = null;
    $messaggio = '';

    if ($nuovoStato === 'occupato') {

        // Aggiorna stato tavolo
        $stmt = $conn->prepare("UPDATE tavoli SET stato = 'occupato' WHERE id = ?");
        $stmt->bind_param('i', $tavoloId);
        $stmt->execute();
        $stmt->close();

        // Cerca sessione attiva esistente
        $stmt = $conn->prepare(
            "SELECT id, token FROM sessioni_tavolo 
             WHERE tavolo_id = ? AND stato = 'attiva' 
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->bind_param('i', $tavoloId);
        $stmt->execute();
        $result = $stmt->get_result();
        $sessioneEsistente = $result->fetch_assoc();
        $stmt->close();

        if ($sessioneEsistente) {
            // Recupera sessione esistente
            $sessioneToken = $sessioneEsistente['token'];
            $messaggio = 'Sessione recuperata';
        } else {
            // Crea nuova sessione
            $newToken = bin2hex(random_bytes(16));
            $apertoDA = $_SESSION['cameriere_id'] ?? null;

            $stmt = $conn->prepare(
                "INSERT INTO sessioni_tavolo (tavolo_id, token, stato, aperta_il, aperta_da) 
                 VALUES (?, ?, 'attiva', NOW(), ?)"
            );
            $stmt->bind_param('isi', $tavoloId, $newToken, $apertoDA);
            $stmt->execute();
            $stmt->close();

            $sessioneToken = $newToken;
            $messaggio = 'Nuova sessione creata';
        }

    } else {
        // Libera tavolo
        $stmt = $conn->prepare("UPDATE tavoli SET stato = ? WHERE id = ?");
        $stmt->bind_param('si', $nuovoStato, $tavoloId);
        $stmt->execute();
        $stmt->close();

        // Chiudi sessioni attive
        $stmt = $conn->prepare(
            "UPDATE sessioni_tavolo 
             SET stato = 'chiusa', chiusa_il = NOW() 
             WHERE tavolo_id = ? AND stato = 'attiva'"
        );
        $stmt->bind_param('i', $tavoloId);
        $stmt->execute();
        $stmt->close();

        $messaggio = 'Tavolo ' . $nuovoStato;
    }

    $conn->commit();

    logSecurityEvent('aggiorna_stato_tavolo', [
        'tavolo_id' => $tavoloId,
        'tavolo_numero' => $tavolo['numero'],
        'vecchio_stato' => $vecchioStato,
        'nuovo_stato' => $nuovoStato
    ]);

    echo json_encode([
        'success' => true,
        'tavolo_id' => $tavoloId,
        'tavolo_numero' => $tavolo['numero'],
        'nuovo_stato' => $nuovoStato,
        'vecchio_stato' => $vecchioStato,
        'sessione_token' => $sessioneToken,
        'message' => $messaggio
    ]);

} catch (Exception $e) {
    $conn->rollback();

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);

    logSecurityEvent('aggiorna_stato_tavolo_error', [
        'error' => $e->getMessage(),
        'tavolo_id' => $tavoloId
    ]);
}

$conn->close();