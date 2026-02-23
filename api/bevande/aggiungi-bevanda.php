<?php
/**
 * aggiungi-bevanda.php - VERSIONE SICURA
 * 
 * Aggiunge una bevanda a un ordine esistente o ne crea uno nuovo.
 * Supporta condivisione tra più clienti con divisione del costo.
 * 
 * Usa ordini_tavolo + ordini_righe (stesso flusso dei piatti).
 * La cucina NON vede le bevande grazie al filtro tipo='piatto'.
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

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON non valido']);
    exit;
}

// Parametri richiesti
$bevandaId     = isset($input['bevanda_id']) ? validateId($input['bevanda_id']) : null;
$tavoloId      = isset($input['tavolo_id']) ? validateId($input['tavolo_id']) : null;
$token         = isset($input['token']) ? sanitizeInput($input['token']) : null;
$condivisione  = isset($input['condivisione']) ? sanitizeInput($input['condivisione']) : 'personale';
$clienteLettera = isset($input['cliente_lettera']) ? strtoupper(sanitizeInput($input['cliente_lettera'])) : null;
$partecipanti  = isset($input['partecipanti']) && is_array($input['partecipanti']) ? $input['partecipanti'] : [];
$quantita      = isset($input['quantita']) ? max(1, (int)$input['quantita']) : 1;

// Validazioni
if (!$bevandaId || !$tavoloId || !$token) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parametri mancanti: bevanda_id, tavolo_id, token']);
    exit;
}

$condivisioniValide = ['personale', 'tavolo', 'gruppo'];
if (!in_array($condivisione, $condivisioniValide, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valore condivisione non valido']);
    exit;
}

if ($clienteLettera && !preg_match('/^[A-Z]$/', $clienteLettera)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'cliente_lettera non valida']);
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
    // 1. Verifica token sessione tavolo
    $stmt = $conn->prepare(
        "SELECT id FROM sessioni_tavolo 
         WHERE tavolo_id = ? AND token = ? AND stato = 'attiva'"
    );
    $stmt->bind_param('is', $tavoloId, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Token sessione non valido o sessione non attiva');
    }
    $sessioneTavoloId = (int)$result->fetch_assoc()['id'];
    $stmt->close();

    // 2. Recupera sessione cliente
    if (!$clienteLettera) {
        throw new Exception('cliente_lettera obbligatorio');
    }

    $stmt = $conn->prepare(
        "SELECT id FROM sessioni_clienti 
         WHERE tavolo_id = ? AND identificativo = ?"
    );
    $stmt->bind_param('is', $tavoloId, $clienteLettera);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Cliente $clienteLettera non trovato per questo tavolo");
    }
    $sessioneClienteId = $result->fetch_assoc()['id'];
    $stmt->close();

    // 3. Recupera info bevanda
    $stmt = $conn->prepare(
        "SELECT id, nome, prezzo FROM piatti WHERE id = ? AND disponibile = 1"
    );
    $stmt->bind_param('i', $bevandaId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Bevanda non trovata o non disponibile: $bevandaId");
    }
    $bevanda = $result->fetch_assoc();
    $stmt->close();

    // 4. Calcola tipo condivisione
    $tipo = ($condivisione === 'personale') ? 'bevanda' : 'bevanda_condivisa';

    // 5. Calcola prezzo
    $prezzoUnitario = (float)$bevanda['prezzo'];
    $totaleRiga = $prezzoUnitario * $quantita;

    // 6. Sanitizza partecipanti
    $partecipantiClean = [];
    foreach ($partecipanti as $p) {
        $p = strtoupper(sanitizeInput((string)$p));
        if (preg_match('/^[A-Z]$/', $p)) {
            $partecipantiClean[] = $p;
        }
    }
    // Assicura che il cliente ordinante sia sempre nei partecipanti
    if ($tipo === 'bevanda_condivisa' && !in_array($clienteLettera, $partecipantiClean)) {
        array_unshift($partecipantiClean, $clienteLettera);
    }
    $partecipantiJson = !empty($partecipantiClean) ? json_encode($partecipantiClean) : null;

    // 7. Crea ordine per questa bevanda
    $stmt = $conn->prepare(
        "INSERT INTO ordini_tavolo 
         (sessione_id, tavolo_id, cliente_lettera, stato, totale, creato_il) 
         VALUES (?, ?, ?, 'inviato', ?, NOW())"
    );
    $stmt->bind_param('iiss', $sessioneTavoloId, $tavoloId, $clienteLettera, $totaleRiga);
    $stmt->execute();
    $ordineId = $conn->insert_id;
    $stmt->close();

    // 8. Inserisci riga bevanda
    $stmt = $conn->prepare(
        "INSERT INTO ordini_righe 
         (ordine_id, piatto_id, nome, prezzo_unitario, quantita, totale_riga, tipo, condivisione, partecipanti, stato) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
    );
    $stmt->bind_param(
        'iisdiisss',
        $ordineId,
        $bevandaId,
        $bevanda['nome'],
        $prezzoUnitario,
        $quantita,
        $totaleRiga,
        $tipo,
        $condivisione,
        $partecipantiJson
    );
    $stmt->execute();
    $rigaId = $conn->insert_id;
    $stmt->close();

    $conn->commit();

    logSecurityEvent('aggiungi_bevanda', [
        'bevanda_id' => $bevandaId,
        'bevanda_nome' => $bevanda['nome'],
        'tavolo_id' => $tavoloId,
        'cliente_lettera' => $clienteLettera,
        'condivisione' => $condivisione,
        'partecipanti' => $partecipantiClean,
        'totale' => $totaleRiga
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Bevanda aggiunta con successo',
        'ordine_id' => $ordineId,
        'riga_id' => $rigaId,
        'bevanda' => $bevanda['nome'],
        'totale' => $totaleRiga,
        'partecipanti' => $partecipantiClean
    ]);

} catch (Exception $e) {
    $conn->rollback();

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);

    logSecurityEvent('aggiungi_bevanda_error', [
        'error' => $e->getMessage(),
        'bevanda_id' => $bevandaId,
        'tavolo_id' => $tavoloId
    ]);
}

$conn->close();