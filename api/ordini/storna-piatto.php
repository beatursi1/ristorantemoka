<?php
/**
 * storna-piatto.php
 * - Cliente / stato 0:    DELETE fisico (annullamento semplice)
 * - Cameriere / stato 0:  DELETE fisico (come cliente)
 * - Cameriere / stato 1-2: UPDATE stato=4, totale_riga=0 (storno visibile nel conto)
 * - Stato 3 (consegnato): bloccato per tutti
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Input non valido']); exit; }

$rigaId = isset($input['riga_id']) ? validateId($input['riga_id']) : null;
$ruolo  = isset($input['ruolo']) && $input['ruolo'] === 'cameriere' ? 'cameriere' : 'cliente';

if (!$rigaId) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'riga_id mancante']); exit; }

$conn = getDbConnection();
if (!$conn) { http_response_code(500); echo json_encode(['success' => false, 'error' => 'Errore connessione database']); exit; }

$conn->begin_transaction();

try {
    // Leggi riga attuale
    $stmt = $conn->prepare(
        "SELECT r.id, r.ordine_id, r.nome, r.quantita, r.totale_riga, r.stato, r.tipo,
                ot.tavolo_id, ot.cliente_lettera
         FROM ordini_righe r
         INNER JOIN ordini_tavolo ot ON ot.id = r.ordine_id
         WHERE r.id = ?"
    );
    $stmt->bind_param('i', $rigaId);
    $stmt->execute();
    $riga = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$riga) throw new Exception('Riga ordine non trovata');

    $stato    = (int)$riga['stato'];
    $ordineId = (int)$riga['ordine_id'];

    // Stato 3 (consegnato) — bloccato per tutti
    if ($stato === 3) {
        throw new Exception('Impossibile modificare: il piatto è già stato consegnato');
    }

    // Cliente: può agire solo su stato 0
    if ($ruolo === 'cliente' && $stato !== 0) {
        throw new Exception('Non puoi più annullare: il piatto non è più in attesa');
    }

    if ($stato === 0) {
        // DELETE fisico — sia cliente che cameriere
        $stmt = $conn->prepare("DELETE FROM ordini_righe WHERE id = ?");
        $stmt->bind_param('i', $rigaId);
        if (!$stmt->execute()) throw new Exception('Errore cancellazione: ' . $stmt->error);
        $stmt->close();
        $azione = 'eliminato';
    } else {
        // Cameriere, stato 1 o 2 — storno: stato=4, totale=0
        $stmt = $conn->prepare("UPDATE ordini_righe SET stato = 4, totale_riga = 0 WHERE id = ?");
        $stmt->bind_param('i', $rigaId);
        if (!$stmt->execute()) throw new Exception('Errore storno: ' . $stmt->error);
        $stmt->close();
        $azione = 'stornato';
    }

    // Ricalcola totale ordine (esclude righe stato 4)
    $stmt = $conn->prepare(
        "UPDATE ordini_tavolo 
         SET totale = (
             SELECT COALESCE(SUM(totale_riga), 0) 
             FROM ordini_righe 
             WHERE ordine_id = ? AND stato != 4
         )
         WHERE id = ?"
    );
    $stmt->bind_param('ii', $ordineId, $ordineId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    logSecurityEvent('storna_piatto', [
        'riga_id'         => $rigaId,
        'ordine_id'       => $ordineId,
        'piatto_nome'     => $riga['nome'],
        'azione'          => $azione,
        'ruolo'           => $ruolo,
        'stato_precedente'=> $stato,
        'tavolo_id'       => $riga['tavolo_id'],
        'cliente_lettera' => $riga['cliente_lettera'],
    ]);

    echo json_encode([
        'success'  => true,
        'message'  => 'Piatto ' . $azione . ' con successo',
        'azione'   => $azione,
        'riga_id'  => $rigaId,
        'ordine_id'=> $ordineId
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    logSecurityEvent('storna_piatto_error', ['error' => $e->getMessage(), 'riga_id' => $rigaId]);
}

$conn->close();