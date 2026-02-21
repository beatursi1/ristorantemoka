<?php
/**
 * Crea Ordine - VERSIONE CORRETTA
 * 
 * Struttura DB:
 * - ordini_tavolo: ordine principale
 * - ordini_righe: righe dell'ordine (piatti)
 * 
 * @version 3.0.0 - CORRECT STRUCTURE
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
header('Access-Control-Allow-Headers: Content-Type, Authorization');
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

$sessioneId = $input['sessione_id'] ?? $input['sessione_cliente_id'] ?? $input['session_id'] ?? null;
$tavoloId = isset($input['tavolo_id']) ? validateId($input['tavolo_id']) : null;
$clienteLettera = $input['cliente'] ?? $input['cliente_lettera'] ?? null; // Può essere null, lo prendiamo dalla sessione
$ordine = $input['ordine'] ?? [];

// Log input per debug
logSecurityEvent('crea_ordine_input', [
    'has_sessione' => !!$sessioneId,
    'has_tavolo' => !!$tavoloId,
    'has_cliente' => !!$clienteLettera,
    'sessione_id' => $sessioneId,
    'tavolo_id' => $tavoloId
]);

if (!$sessioneId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'sessione_id mancante',
        'debug' => ['received_keys' => array_keys($input)]
    ]);
    exit;
}

if (!is_array($ordine) || empty($ordine)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ordine vuoto']);
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
    // Cerca sessione nella tabella sessioni_clienti
    // Il frontend potrebbe passare: session_id (stringa) o tavolo_id (numero)
    
    logSecurityEvent('crea_ordine_debug_ricerca', [
        'sessioneId_ricevuto' => $sessioneId,
        'tipo' => gettype($sessioneId),
        'is_numeric' => is_numeric($sessioneId)
    ]);
    
    $stmt = $conn->prepare("SELECT id, tavolo_id, identificativo FROM sessioni_clienti WHERE id = ?");
    $stmt->bind_param('s', $sessioneId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    logSecurityEvent('crea_ordine_debug_risultato', [
        'num_rows' => $result->num_rows
    ]);
    
    // Se non trovata e sessioneId è numerico, prova a cercare per tavolo_id
    if ($result->num_rows === 0 && is_numeric($sessioneId)) {
        $stmt->close();
        
        // Probabilmente hanno passato tavolo_id invece di session_id
        $tavoloIdTemp = (int)$sessioneId;
        
        logSecurityEvent('crea_ordine_fallback_tavolo', [
            'sessioneId_ricevuto' => $sessioneId,
            'cercando_per_tavolo' => $tavoloIdTemp
        ]);
        
        // Cerca l'ultima sessione attiva per questo tavolo
        $stmt = $conn->prepare(
            "SELECT id, tavolo_id, identificativo 
             FROM sessioni_clienti 
             WHERE tavolo_id = ? 
             ORDER BY created_at DESC 
             LIMIT 1"
        );
        $stmt->bind_param('i', $tavoloIdTemp);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    if ($result->num_rows === 0) {
        throw new Exception("Sessione cliente non trovata. Ricevuto: $sessioneId");
    }
    
    $sessioneData = $result->fetch_assoc();
    $sessioneIdCorretto = $sessioneData['id'];
    $tavoloIdDaSessione = (int)$sessioneData['tavolo_id'];
    $clienteLett = $sessioneData['identificativo'];
    $stmt->close();
    
    // SEMPRE usa il cliente dalla sessione (non dal parametro passato)
    $clienteLettera = $clienteLett;
    
    // Usa il tavolo dalla sessione se non passato o diverso
    if (!$tavoloId || $tavoloId !== $tavoloIdDaSessione) {
        $tavoloId = $tavoloIdDaSessione;
    }
    
    logSecurityEvent('crea_ordine_sessione_trovata', [
        'sessioneId_ricevuto' => $sessioneId,
        'sessioneId_corretto' => $sessioneIdCorretto,
        'tavolo_id' => $tavoloId,
        'cliente_lettera' => $clienteLettera
    ]);
    
    // Ora trova la sessione_tavolo per questo tavolo
    $stmt = $conn->prepare("SELECT id FROM sessioni_tavolo WHERE tavolo_id = ? AND stato = 'attiva' ORDER BY aperta_il DESC LIMIT 1");
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Nessuna sessione tavolo attiva per tavolo $tavoloId");
    }
    
    $sessioneTavoloData = $result->fetch_assoc();
    $sessioneTavoloId = (int)$sessioneTavoloData['id'];
    $stmt->close();
    
    logSecurityEvent('crea_ordine_sessioni_trovate', [
        'sessione_cliente_id' => $sessioneId,
        'sessione_tavolo_id' => $sessioneTavoloId,
        'tavolo_id' => $tavoloId,
        'cliente_lettera' => $clienteLettera
    ]);
    
    // Calcola totale ordine
    $totaleOrdine = 0;
    foreach ($ordine as $piatto) {
        $prezzo = floatval($piatto['prezzo'] ?? $piatto['price'] ?? 0);
        $quantita = intval($piatto['quantita'] ?? 1);
        $totaleOrdine += $prezzo * $quantita;
    }
    
    // INSERT ordine principale in ordini_tavolo
    $stmt = $conn->prepare(
        "INSERT INTO ordini_tavolo (sessione_id, tavolo_id, cliente_lettera, stato, totale) 
         VALUES (?, ?, ?, 'inviato', ?)"
    );
    $stmt->bind_param('iisd', $sessioneTavoloId, $tavoloId, $clienteLettera, $totaleOrdine);
    
    if (!$stmt->execute()) {
        throw new Exception("Errore creazione ordine: " . $stmt->error);
    }
    
    $ordineId = $conn->insert_id;
    $stmt->close();
    
    logSecurityEvent('crea_ordine_tavolo', [
        'ordine_id' => $ordineId,
        'sessione_cliente_id' => $sessioneId,
        'sessione_tavolo_id' => $sessioneTavoloId,
        'tavolo_id' => $tavoloId,
        'cliente_lettera' => $clienteLettera,
        'totale' => $totaleOrdine
    ]);
    
    // INSERT righe ordine in ordini_righe
    $piattiInseriti = 0;
    $stmt = $conn->prepare(
        "INSERT INTO ordini_righe (ordine_id, piatto_id, nome, prezzo_unitario, quantita, totale_riga, tipo, condivisione, partecipanti) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    foreach ($ordine as $piatto) {
        $piattoId = validateId($piatto['id'] ?? null);
        $nome = sanitizeInput($piatto['nome'] ?? 'Piatto');
        $prezzoUnitario = floatval($piatto['prezzo'] ?? $piatto['price'] ?? 0);
        $quantita = intval($piatto['quantita'] ?? 1);
        $totaleRiga = $prezzoUnitario * $quantita;
        
        // Determina il tipo corretto
        $tipo = 'piatto'; // default
        if (isset($piatto['tipo'])) {
            $tipoInput = strtolower(trim($piatto['tipo']));
            if ($tipoInput === 'bevanda') {
                $tipo = 'bevanda';
            } elseif ($tipoInput === 'bevanda_condivisa') {
                $tipo = 'bevanda_condivisa';
            }
        }
        
$valoriCondivisioneAmmessi = ['personale', 'tavolo', 'parziale'];
        $condivisioneRaw = strtolower(trim($piatto['condivisione'] ?? 'personale'));
        // Normalizza 'gruppo' (vecchio nome frontend) in 'parziale'
        if ($condivisioneRaw === 'gruppo') $condivisioneRaw = 'parziale';
        $condivisione = in_array($condivisioneRaw, $valoriCondivisioneAmmessi) ? $condivisioneRaw : 'personale';
        $partecipanti = isset($piatto['partecipanti']) ? json_encode($piatto['partecipanti']) : null;
        
        if (!$piattoId || $quantita < 1) {
            continue;
        }
        
        if ($quantita > 50) $quantita = 50;
        
        $stmt->bind_param(

            'iisdiisss',
            $ordineId,
            $piattoId,
            $nome,
            $prezzoUnitario,
            $quantita,
            $totaleRiga,
            $tipo,
            $condivisione,
            $partecipanti
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Errore inserimento piatto: " . $stmt->error);
        }
        
        $piattiInseriti++;
    }
    
    $stmt->close();
    
    if ($piattiInseriti === 0) {
        throw new Exception("Nessun piatto valido inserito");
    }
    
    $conn->commit();
    
    logSecurityEvent('crea_ordine_success', [
        'ordine_id' => $ordineId,
        'piatti_inseriti' => $piattiInseriti,
        'totale' => $totaleOrdine
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ordine creato con successo',
        'ordine_id' => $ordineId,
        'piatti_inseriti' => $piattiInseriti,
        'totale' => $totaleOrdine,
        'tavolo_id' => $tavoloId,
        'cliente_lettera' => $clienteLettera
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    logSecurityEvent('crea_ordine_error', ['error' => $e->getMessage()]);
}

$conn->close();