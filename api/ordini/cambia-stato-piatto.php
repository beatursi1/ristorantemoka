<?php
/**
 * Cambia Stato Piatto - VERSIONE SICURA
 * 
 * Permette di cambiare lo stato di un piatto in cucina:
 * 0 = In attesa
 * 1 = In preparazione  
 * 2 = Pronto
 * 3 = Servito
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

$rigaId = isset($input['riga_id']) ? validateId($input['riga_id']) : null;
$nuovoStato = isset($input['nuovo_stato']) ? intval($input['nuovo_stato']) : null;

if (!$rigaId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'riga_id mancante']);
    exit;
}

if ($nuovoStato === null || $nuovoStato < 0 || $nuovoStato > 3) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'nuovo_stato non valido (deve essere 0-3)',
        'debug' => ['nuovo_stato_ricevuto' => $nuovoStato]
    ]);
    exit;
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

try {
    // Verifica che la riga esista
    $stmt = $conn->prepare("SELECT id, stato FROM ordini_righe WHERE id = ?");
    $stmt->bind_param('i', $rigaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Riga ordine non trovata: $rigaId");
    }
    
    $riga = $result->fetch_assoc();
    $vecchioStato = (int)$riga['stato'];
    $stmt->close();
    
    // Aggiorna lo stato
    $stmt = $conn->prepare("UPDATE ordini_righe SET stato = ? WHERE id = ?");
    $stmt->bind_param('ii', $nuovoStato, $rigaId);
    
    if (!$stmt->execute()) {
        throw new Exception("Errore aggiornamento stato: " . $stmt->error);
    }
    
    $stmt->close();
    
    // Log dell'operazione
    logSecurityEvent('cambia_stato_piatto', [
        'riga_id' => $rigaId,
        'stato_precedente' => $vecchioStato,
        'nuovo_stato' => $nuovoStato,
        'ip' => getClientIP()
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Stato aggiornato con successo',
        'riga_id' => $rigaId,
        'stato_precedente' => $vecchioStato,
        'nuovo_stato' => $nuovoStato
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    logSecurityEvent('cambia_stato_piatto_error', [
        'error' => $e->getMessage(),
        'riga_id' => $rigaId
    ]);
}

$conn->close();