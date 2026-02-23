<?php
/**
 * Libera Tavolo - VERSIONE SICURA
 * 
 * Chiude la sessione del tavolo e libera tutti i clienti.
 * Usato quando il tavolo finisce il servizio.
 * 
 * @version 2.0.0 - SECURE
 */

ob_start();

define('ACCESS_ALLOWED', true);
require_once('../../config/config.php');
require_once('../../includes/security.php');

ob_clean();

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

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Input non valido']);
    exit;
}

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

$conn->begin_transaction();

try {
    // Verifica che il tavolo esista
    $stmt = $conn->prepare("SELECT numero FROM tavoli WHERE id = ?");
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Tavolo non trovato: $tavoloId");
    }
    
    $tavolo = $result->fetch_assoc();
    $tavoloNumero = $tavolo['numero'];
    $stmt->close();
    
    // Trova la sessione attiva del tavolo
    $stmt = $conn->prepare(
        "SELECT id, token FROM sessioni_tavolo 
         WHERE tavolo_id = ? AND stato = 'attiva' 
         ORDER BY aperta_il DESC LIMIT 1"
    );
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sessione = $result->fetch_assoc();
    $sessioneId = $sessione ? (int)$sessione['id'] : null;
    $sessioneToken = $sessione ? $sessione['token'] : null;
    $stmt->close();
    
    // Conta clienti e ordini prima di cancellare
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessioni_clienti WHERE tavolo_id = ?");
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $clientiCount = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    $ordiniCount = 0;
    if ($sessioneId) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ordini_tavolo WHERE tavolo_id = ? AND sessione_id = ?");
        $stmt->bind_param('ii', $tavoloId, $sessioneId);
        $stmt->execute();
        $ordiniCount = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
    }
    
    // Chiudi la sessione tavolo
    $stmt = $conn->prepare(
        "UPDATE sessioni_tavolo 
         SET stato = 'chiusa', 
             chiusa_il = NOW() 
         WHERE tavolo_id = ? AND stato = 'attiva'"
    );
    $stmt->bind_param('i', $tavoloId);
    
    if (!$stmt->execute()) {
        throw new Exception("Errore chiusura sessione: " . $stmt->error);
    }
    $stmt->close();

    // ⭐ Aggiorna stato tavolo a "libero"
    $stmt = $conn->prepare("UPDATE tavoli SET stato = 'libero' WHERE id = ?");
    $stmt->bind_param('i', $tavoloId);
    
    if (!$stmt->execute()) {
        throw new Exception("Errore aggiornamento stato tavolo: " . $stmt->error);
    }
    $stmt->close();
    
    // Cancella le sessioni clienti del tavolo
    $stmt = $conn->prepare("DELETE FROM sessioni_clienti WHERE tavolo_id = ?");
    $stmt->bind_param('i', $tavoloId);
    
    if (!$stmt->execute()) {
        throw new Exception("Errore cancellazione clienti: " . $stmt->error);
    }
    $stmt->close();
    
    // Cancella le inizializzazioni del tavolo
    $stmt = $conn->prepare("DELETE FROM inizializzazioni_tavoli WHERE tavolo_id = ?");
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    // Log dell'operazione
    logSecurityEvent('libera_tavolo', [
        'tavolo_id' => $tavoloId,
        'tavolo_numero' => $tavoloNumero,
        'sessione_id' => $sessioneId,
        'sessione_token' => $sessioneToken,
        'clienti_cancellati' => $clientiCount,
        'ordini_totali' => $ordiniCount,
        'ip' => getClientIP()
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tavolo liberato con successo',
        'tavolo_id' => $tavoloId,
        'tavolo_numero' => $tavoloNumero,
        'sessione_chiusa' => $sessioneId,
        'clienti_cancellati' => $clientiCount,
        'ordini_totali' => $ordiniCount
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    logSecurityEvent('libera_tavolo_error', [
        'error' => $e->getMessage(),
        'tavolo_id' => $tavoloId
    ]);
}

$conn->close();