<?php
/**
 * api/fedelta/associa-passphrase.php
 * Riceve la passphrase del cliente, verifica univocità,
 * hasha con SHA-256 e associa al profilo fedeltà.
 *
 * Struttura reale DB:
 * - gioco_profilo_cliente: usa cliente_identificativo come chiave,
 *   passphrase_hash come chiave cross-sessione permanente
 * - sessioni_clienti: passphrase_hash per lookup rapido di serata
 *
 * @version 1.1.0
 */
session_start();
define('ACCESS_ALLOWED', true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

require_once('../../config/config.php');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Body non valido']);
    exit;
}

$passphrase     = trim($input['passphrase']            ?? '');
$sessioneCliId  = trim($input['sessione_cliente_id']   ?? '');
$tavoloId       = isset($input['tavolo_id'])            ? (int)$input['tavolo_id'] : 0;
$clienteLettera = strtoupper(trim($input['cliente_lettera'] ?? ''));

// Validazione base
if (strlen($passphrase) < 3) {
    echo json_encode(['success' => false, 'error' => 'Passphrase troppo corta (minimo 3 caratteri)']);
    exit;
}
if (!$sessioneCliId || !$tavoloId || !$clienteLettera) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
    exit;
}

// Sanifica: solo lettere, numeri, spazi e punteggiatura base — max 60 chars
$passphrase = mb_substr(preg_replace('/[^\p{L}\p{N}\s\-_\.!@]/u', '', $passphrase), 0, 60);
$passphrase = trim($passphrase);
if (strlen($passphrase) < 3) {
    echo json_encode(['success' => false, 'error' => 'Passphrase non valida — usa solo lettere e numeri']);
    exit;
}

// ── RATE LIMITING (sessione PHP) ─────────────────────────
$rateKey  = 'pp_attempts_' . md5($sessioneCliId);
$attempts = $_SESSION[$rateKey] ?? ['count' => 0, 'first' => time()];
if (time() - $attempts['first'] > 600) {
    $attempts = ['count' => 0, 'first' => time()];
}
if ($attempts['count'] >= 5) {
    $rimanenti = 600 - (time() - $attempts['first']);
    echo json_encode([
        'success' => false,
        'error'   => 'Troppi tentativi. Riprova tra ' . ceil($rimanenti / 60) . ' minuti.'
    ]);
    exit;
}
$attempts['count']++;
$_SESSION[$rateKey] = $attempts;

// ── HASH SHA-256 ──────────────────────────────────────────
// Lowercase per case-insensitive: "Pizza" = "pizza" = "PIZZA"
$hash = hash('sha256', mb_strtolower($passphrase));

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

try {
    // ── 1. Cerca profilo con questa passphrase_hash ───────
    $stmt = $conn->prepare(
        "SELECT id, cliente_identificativo
         FROM gioco_profilo_cliente
         WHERE passphrase_hash = ?
         LIMIT 1"
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $profiloEsistente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($profiloEsistente) {
        // La passphrase esiste — è di questo cliente o di un altro?
        if ($profiloEsistente['cliente_identificativo'] !== $sessioneCliId) {
            // È di qualcun altro
            echo json_encode([
                'success' => false,
                'error'   => 'Parola già usata da un altro cliente. Scegline una più personale!'
            ]);
            $conn->close();
            exit;
        }

        // È già sua — aggiorna sessione_cliente_id e tavolo per la serata corrente
        $stmt = $conn->prepare(
            "UPDATE gioco_profilo_cliente
             SET sessione_cliente_id = ?,
                 tavolo_id           = ?,
                 cliente_lettera     = ?,
                 aggiornato_il       = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('sisi', $sessioneCliId, $tavoloId, $clienteLettera, $profiloEsistente['id']);
        $stmt->execute();
        $stmt->close();

        // Aggiorna anche sessioni_clienti per lookup rapido
        aggiornaSessioneCliente($conn, $sessioneCliId, $hash);

        $_SESSION[$rateKey]['count'] = 0;
        echo json_encode([
            'success'    => true,
            'message'    => 'Bentornato! Punti ripristinati.',
            'profilo_id' => (int)$profiloEsistente['id'],
            'nuova'      => false,
            'bentornato' => true
        ]);
        $conn->close();
        exit;
    }

    // ── 2. Controlla se questo cliente ha già un profilo ─
    //    (profilo senza passphrase o con passphrase diversa)
    $stmt = $conn->prepare(
        "SELECT id FROM gioco_profilo_cliente
         WHERE cliente_identificativo = ?
         LIMIT 1"
    );
    $stmt->bind_param('s', $sessioneCliId);
    $stmt->execute();
    $profiloCliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($profiloCliente) {
        // Aggiorna il profilo esistente con la nuova passphrase
        $stmt = $conn->prepare(
            "UPDATE gioco_profilo_cliente
             SET passphrase_hash      = ?,
                 sessione_cliente_id  = ?,
                 tavolo_id            = ?,
                 cliente_lettera      = ?,
                 aggiornato_il        = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('ssisi', $hash, $sessioneCliId, $tavoloId, $clienteLettera, $profiloCliente['id']);
        $stmt->execute();
        $stmt->close();
        $profiloId = (int)$profiloCliente['id'];

    } else {
        // ── 3. Crea nuovo profilo ─────────────────────────
        $stmt = $conn->prepare(
            "INSERT INTO gioco_profilo_cliente
                (cliente_identificativo, passphrase_hash, sessione_cliente_id,
                 tavolo_id, cliente_lettera,
                 nome, punti_totali, punti_disponibili,
                 posizione_corrente, giri_completati, avatar, livello)
             VALUES (?, ?, ?, ?, ?, 'Cliente Moka', 0, 0, 0, 0, 'chicco', 'ospite')"
        );
        $stmt->bind_param(
            'ssssi',
            $sessioneCliId, $hash, $sessioneCliId,
            $tavoloId, $clienteLettera
        );
        if (!$stmt->execute()) {
            throw new Exception("Errore creazione profilo: " . $stmt->error);
        }
        $profiloId = $conn->insert_id;
        $stmt->close();
    }

    // ── 4. Salva hash in sessioni_clienti per lookup rapido
    aggiornaSessioneCliente($conn, $sessioneCliId, $hash);

    // Reset rate limit su successo
    $_SESSION[$rateKey]['count'] = 0;

    echo json_encode([
        'success'    => true,
        'message'    => 'Chiave Moka attivata! Punti in accumulo.',
        'profilo_id' => $profiloId,
        'nuova'      => true,
        'bentornato' => false
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();

// ── HELPER ───────────────────────────────────────────────
function aggiornaSessioneCliente($conn, $sessioneCliId, $hash) {
    $stmt = $conn->prepare(
        "UPDATE sessioni_clienti SET passphrase_hash = ? WHERE id = ?"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $hash, $sessioneCliId);
        $stmt->execute();
        $stmt->close();
    }
}