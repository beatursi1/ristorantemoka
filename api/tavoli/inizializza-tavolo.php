<?php
/**
 * Inizializza Tavolo - VERSIONE SICURA
 * 
 * Inizializza un tavolo con i clienti configurati dal cameriere.
 * Crea sessioni clienti e salva la configurazione iniziale.
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

// Verifica cameriere o admin loggato
if (empty($_SESSION['utente_id']) || !in_array($_SESSION['utente_ruolo'] ?? '', ['admin', 'cameriere'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Accesso non autorizzato']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Input non valido']);
    exit;
}

$tavoloId = isset($input['tavolo_id']) ? validateId($input['tavolo_id']) : null;
$clienti = $input['clienti'] ?? [];

if (!$tavoloId || empty($clienti)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'tavolo_id e clienti sono obbligatori']);
    exit;
}

$cameriereId = (int)$_SESSION['utente_id'];

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Verifica che il tavolo esista
    $stmt = $conn->prepare("SELECT id, numero FROM tavoli WHERE id = ?");
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Tavolo non trovato: $tavoloId");
    }
    $tavolo = $result->fetch_assoc();
    $stmt->close();

    // 2. Aggiorna stato tavolo a "occupato"
    $stmt = $conn->prepare("UPDATE tavoli SET stato = 'occupato' WHERE id = ?");
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $stmt->close();

    // 3. Crea o recupera sessione tavolo
    $stmt = $conn->prepare(
        "SELECT id, token FROM sessioni_tavolo 
         WHERE tavolo_id = ? AND stato = 'attiva' 
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessioneTavolo = $result->fetch_assoc();
    $stmt->close();

    if (!$sessioneTavolo) {
        $newToken = bin2hex(random_bytes(16));
        $stmt = $conn->prepare(
            "INSERT INTO sessioni_tavolo (tavolo_id, token, stato, aperta_il, aperta_da) 
             VALUES (?, ?, 'attiva', NOW(), ?)"
        );
        $stmt->bind_param('isi', $tavoloId, $newToken, $cameriereId);
        $stmt->execute();
        $sessioneTavoloId = $conn->insert_id;
        $sessioneToken = $newToken;
        $stmt->close();
    } else {
        $sessioneTavoloId = (int)$sessioneTavolo['id'];
        $sessioneToken = $sessioneTavolo['token'];
    }

    // 4. Cancella sessioni clienti precedenti del tavolo
    $stmt = $conn->prepare("DELETE FROM sessioni_clienti WHERE tavolo_id = ?");
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $stmt->close();

    // 5. Crea sessioni per i clienti attivi
    $sessioniConfigurate = [];

    foreach ($clienti as $lettera => $cliente) {
        if (!isset($cliente['attivo']) || !$cliente['attivo']) continue;

        $lettera = strtoupper(sanitizeInput($lettera));
        if (!preg_match('/^[A-Z]$/', $lettera)) continue;

        $nomeCliente = sanitizeInput($cliente['nome'] ?? '');
        $sessionId = 'man_' . $tavoloId . '_' . $lettera . '_' . time();

        $stmt = $conn->prepare(
            "INSERT INTO sessioni_clienti 
             (id, tavolo_id, identificativo, nome_cliente, inizializzato_da, data_inizializzazione, tipo) 
             VALUES (?, ?, ?, ?, ?, NOW(), 'manuale')"
        );
        $stmt->bind_param('sissi', $sessionId, $tavoloId, $lettera, $nomeCliente, $cameriereId);
        $stmt->execute();
        $stmt->close();

        $sessioniConfigurate[$lettera] = [
            'session_id' => $sessionId,
            'nome' => $nomeCliente,
            'lettera' => $lettera
        ];
    }

    if (empty($sessioniConfigurate)) {
        throw new Exception("Nessun cliente attivo configurato");
    }

    // 6. Salva configurazione iniziale
    $sessioniJson = json_encode($sessioniConfigurate);
    $bevandeJson = json_encode($input['bevande'] ?? []);

    $stmt = $conn->prepare(
        "INSERT INTO inizializzazioni_tavoli 
         (tavolo_id, cameriere_id, sessioni_configurate, bevande_iniziali) 
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('iiss', $tavoloId, $cameriereId, $sessioniJson, $bevandeJson);
    $stmt->execute();
    $inizializzazioneId = $conn->insert_id;
    $stmt->close();

    // 7. Log attività
    $dettagli = json_encode([
        'cameriere_id' => $cameriereId,
        'clienti_attivi' => count($sessioniConfigurate),
        'inizializzazione_id' => $inizializzazioneId
    ]);
    $stmt = $conn->prepare(
        "INSERT INTO log_attivita (tipo, descrizione, tavolo_id, dettagli) 
         VALUES ('inizializzazione', 'Tavolo inizializzato dal cameriere', ?, ?)"
    );
    $stmt->bind_param('is', $tavoloId, $dettagli);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // URL QR code
    $baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
    $qrCodeUrl = $baseUrl . '/app/home.html?tavolo=' . $tavoloId . '&sessione=' . $sessioneToken;

    logSecurityEvent('inizializza_tavolo', [
        'tavolo_id' => $tavoloId,
        'tavolo_numero' => $tavolo['numero'],
        'cameriere_id' => $cameriereId,
        'clienti_attivi' => count($sessioniConfigurate),
        'sessione_token' => $sessioneToken
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Tavolo inizializzato con successo',
        'tavolo_id' => $tavoloId,
        'clienti_attivi' => count($sessioniConfigurate),
        'sessioni_configurate' => $sessioniConfigurate,
        'sessione_token' => $sessioneToken,
        'qr_code_url' => $qrCodeUrl,
        'inizializzazione_id' => $inizializzazioneId,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $conn->rollback();

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);

    logSecurityEvent('inizializza_tavolo_error', [
        'error' => $e->getMessage(),
        'tavolo_id' => $tavoloId
    ]);
}

$conn->close();