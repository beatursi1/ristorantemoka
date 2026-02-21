<?php
/**
 * Storna Piatto - VERSIONE SICURA
 * 
 * Permette di stornare (cancellare) un piatto da un ordine.
 * Usato quando un piatto viene ordinato per errore.
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
$motivo = isset($input['motivo']) ? sanitizeInput($input['motivo']) : 'Non specificato';

if (!$rigaId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'riga_id mancante']);
    exit;
}

// Limita lunghezza motivo
if (strlen($motivo) > 255) {
    $motivo = substr($motivo, 0, 255);
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

$conn->begin_transaction();

try {
    // Recupera info riga prima di cancellarla
    $stmt = $conn->prepare(
        "SELECT r.id, r.ordine_id, r.nome, r.quantita, r.totale_riga, r.stato, r.tipo,
                ot.tavolo_id, ot.cliente_lettera
         FROM ordini_righe r
         INNER JOIN ordini_tavolo ot ON ot.id = r.ordine_id
         WHERE r.id = ?"
    );
    $stmt->bind_param('i', $rigaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Riga ordine non trovata: $rigaId");
    }
    
    $riga = $result->fetch_assoc();
    $stmt->close();
    
    // Verifica che il piatto non sia già servito (stato 3)
    if ((int)$riga['stato'] === 3) {
        throw new Exception("Impossibile stornare: il piatto è già stato servito");
    }
    
    // Cancella la riga
    $stmt = $conn->prepare("DELETE FROM ordini_righe WHERE id = ?");
    $stmt->bind_param('i', $rigaId);
    
    if (!$stmt->execute()) {
        throw new Exception("Errore cancellazione riga: " . $stmt->error);
    }
    
    $stmt->close();
    
    // Aggiorna il totale dell'ordine
    $ordineId = (int)$riga['ordine_id'];
    $stmt = $conn->prepare(
        "UPDATE ordini_tavolo 
         SET totale = (SELECT COALESCE(SUM(totale_riga), 0) FROM ordini_righe WHERE ordine_id = ?)
         WHERE id = ?"
    );
    $stmt->bind_param('ii', $ordineId, $ordineId);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    // Log dell'operazione
    logSecurityEvent('storna_piatto', [
        'riga_id' => $rigaId,
        'ordine_id' => $ordineId,
        'piatto_nome' => $riga['nome'],
        'quantita' => $riga['quantita'],
        'tavolo_id' => $riga['tavolo_id'],
        'cliente_lettera' => $riga['cliente_lettera'],
        'motivo' => $motivo,
        'ip' => getClientIP()
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Piatto stornato con successo',
        'riga_id' => $rigaId,
        'ordine_id' => $ordineId,
        'piatto_stornato' => $riga['nome'],
        'motivo' => $motivo
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    logSecurityEvent('storna_piatto_error', [
        'error' => $e->getMessage(),
        'riga_id' => $rigaId
    ]);
}

$conn->close();