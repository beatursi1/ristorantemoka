<?php
/**
 * api/bevande/aggiorna-bevanda.php
 * @version 3.3.0 — nome lungo identico al sistema esistente, anti-duplicato su piatto_id
 */
session_start();
define('ACCESS_ALLOWED', true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['utente_id']) || !in_array($_SESSION['utente_ruolo'], ['cameriere', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Body non valido']);
    exit;
}

require_once('../../config/config.php');
$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

$azione = trim($input['azione'] ?? '');

try {

    // ── CAMBIA STATO ──────────────────────────────────────────
    if ($azione === 'stato') {
        $rigaId     = isset($input['riga_id'])     ? (int)$input['riga_id']     : 0;
        $nuovoStato = isset($input['nuovo_stato']) ? (int)$input['nuovo_stato'] : -1;

        if (!$rigaId || !in_array($nuovoStato, [0, 1, 2, 3])) {
            throw new Exception("Parametri non validi");
        }

        $stmt = $conn->prepare("SELECT id, tipo, stato FROM ordini_righe WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $rigaId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) throw new Exception("Bevanda non trovata");
        if (!in_array($row['tipo'], ['bevanda', 'bevanda_condivisa'])) {
            throw new Exception("La riga non è una bevanda");
        }
        if ((int)$row['stato'] === 3 && $nuovoStato === 3) {
            throw new Exception("Bevanda già servita");
        }

        $stmt = $conn->prepare("UPDATE ordini_righe SET stato = ? WHERE id = ?");
        $stmt->bind_param('ii', $nuovoStato, $rigaId);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Stato aggiornato', 'nuovo_stato' => $nuovoStato]);


    // ── STORNA ───────────────────────────────────────────────
    } elseif ($azione === 'storna') {
        $rigaId = isset($input['riga_id']) ? (int)$input['riga_id'] : 0;
        if (!$rigaId) throw new Exception("riga_id mancante");

        $stmt = $conn->prepare("SELECT id, tipo, stato FROM ordini_righe WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $rigaId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) throw new Exception("Bevanda non trovata");
        if (!in_array($row['tipo'], ['bevanda', 'bevanda_condivisa'])) {
            throw new Exception("La riga non è una bevanda");
        }
        if ((int)$row['stato'] === 3) {
            throw new Exception("Non si può stornare una bevanda già servita");
        }

        $stmt = $conn->prepare("DELETE FROM ordini_righe WHERE id = ?");
        $stmt->bind_param('i', $rigaId);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Bevanda stornata']);


    // ── AGGIUNGI ─────────────────────────────────────────────
    } elseif ($azione === 'aggiungi') {
        $tavoloId     = isset($input['tavolo_id'])       ? (int)$input['tavolo_id']        : 0;
        $cliente      = isset($input['cliente_lettera']) ? strtoupper(trim($input['cliente_lettera'])) : '';
        $nomeBevanda  = isset($input['nome'])            ? trim($input['nome'])             : '';
        $qta          = isset($input['quantita'])        ? max(1, (int)$input['quantita'])  : 1;
        $piattoId     = isset($input['piatto_id'])       ? (int)$input['piatto_id']         : 0;
        $prezzoUnit   = isset($input['prezzo_unitario']) ? (float)$input['prezzo_unitario'] : 0.00;
        $condivisione = isset($input['condivisione'])    ? trim($input['condivisione'])     : 'personale';

        // partecipanti: array di oggetti {lettera, nome} passati dal JS
        $partecipantiInput = isset($input['partecipanti']) && is_array($input['partecipanti'])
                             ? $input['partecipanti'] : [];

        if (!$tavoloId || !$cliente || !$nomeBevanda) {
            throw new Exception("Tavolo, cliente e bevanda sono obbligatori");
        }

        if (!in_array($condivisione, ['personale', 'tavolo', 'parziale'])) {
            $condivisione = 'personale';
        }

        // ── Costruisce array partecipanti come oggetti {nome, lettera}
        // identico al formato della riga 244 del sistema esistente
        $partecipantiObj = [];
        foreach ($partecipantiInput as $p) {
            if (is_array($p) && isset($p['lettera'])) {
                // già nel formato oggetto
                $lettera = strtoupper(trim($p['lettera']));
                $nome    = trim($p['nome'] ?? '');
                if (preg_match('/^[A-Z]$/', $lettera)) {
                    $partecipantiObj[] = ['nome' => $nome ?: 'Cliente ' . $lettera, 'lettera' => $lettera];
                }
            } elseif (is_string($p)) {
                // formato semplice "C" — aggiungiamo con nome generico
                $lettera = strtoupper(trim($p));
                if (preg_match('/^[A-Z]$/', $lettera)) {
                    $partecipantiObj[] = ['nome' => 'Cliente ' . $lettera, 'lettera' => $lettera];
                }
            }
        }

        // Assicura che il cliente ordinante sia sempre incluso come primo
        $letterePresenti = array_column($partecipantiObj, 'lettera');
        if (!in_array($cliente, $letterePresenti)) {
            array_unshift($partecipantiObj, ['nome' => 'Cliente ' . $cliente, 'lettera' => $cliente]);
        }

        // ── Tipo riga
        $tipo = ($condivisione === 'personale') ? 'bevanda' : 'bevanda_condivisa';

        // ── Nome lungo identico al sistema esistente:
        // "Vino Rosso — condivisa con Cliente B (B)" se ci sono altri partecipanti
        $nomeFinale = $nomeBevanda;
        if ($condivisione !== 'personale' && count($partecipantiObj) > 1) {
            // Altri partecipanti escluso l'ordinante
            $altri = array_filter($partecipantiObj, fn($p) => $p['lettera'] !== $cliente);
            $altriLabel = array_map(function($p) {
                $nome = $p['nome'] ?: 'Cliente ' . $p['lettera'];
                return $nome . ' (' . $p['lettera'] . ')';
            }, array_values($altri));
            $nomeFinale = $nomeBevanda . ' — condivisa con ' . implode(', ', $altriLabel);
        }

        // ── Prezzo dal DB se non passato
        if ($prezzoUnit <= 0 && $piattoId > 0) {
            $stmt = $conn->prepare("SELECT prezzo FROM piatti WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $piattoId);
            $stmt->execute();
            $pRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($pRow) $prezzoUnit = (float)$pRow['prezzo'];
        }
        $totaleRiga = $prezzoUnit * $qta;

        // ── Verifica tavolo
        $stmt = $conn->prepare("SELECT id FROM tavoli WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $tavoloId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) throw new Exception("Tavolo non trovato");
        $stmt->close();

        // ── Sessione attiva
        $stmt = $conn->prepare(
            "SELECT id FROM sessioni_tavolo
             WHERE tavolo_id = ? AND stato = 'attiva'
             ORDER BY aperta_il DESC LIMIT 1"
        );
        $stmt->bind_param('i', $tavoloId);
        $stmt->execute();
        $sessione = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$sessione) {
            throw new Exception("Nessuna sessione attiva per questo tavolo. Inizializza prima il tavolo.");
        }
        $sessioneId = (int)$sessione['id'];

        // ── Ordine del cliente nella sessione
        $stmt = $conn->prepare(
            "SELECT id FROM ordini_tavolo
             WHERE tavolo_id = ? AND cliente_lettera = ? AND sessione_id = ?
             ORDER BY creato_il DESC LIMIT 1"
        );
        $stmt->bind_param('isi', $tavoloId, $cliente, $sessioneId);
        $stmt->execute();
        $ordine = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ordine) {
            $stmt = $conn->prepare(
                "INSERT INTO ordini_tavolo (sessione_id, tavolo_id, cliente_lettera, stato, totale)
                 VALUES (?, ?, ?, 'inviato', 0)"
            );
            $stmt->bind_param('iis', $sessioneId, $tavoloId, $cliente);
            $stmt->execute();
            $ordineId = $conn->insert_id;
            $stmt->close();
        } else {
            $ordineId = (int)$ordine['id'];
        }

        // ── ANTI-DUPLICATO ────────────────────────────────────
        // Controlla se esiste già una riga con lo stesso piatto_id
        // nello stesso ordine e stato non servito (stato < 3).
        // Se esiste, non inseriamo nulla.
        if ($piattoId > 0) {
            $stmt = $conn->prepare(
                "SELECT id FROM ordini_righe
                 WHERE ordine_id = ?
                   AND piatto_id = ?
                   AND tipo IN ('bevanda','bevanda_condivisa')
                   AND stato < 3
                 LIMIT 1"
            );
            $stmt->bind_param('ii', $ordineId, $piattoId);
        } else {
            // Fallback su nome base (senza la parte " — condivisa con...")
            $stmt = $conn->prepare(
                "SELECT id FROM ordini_righe
                 WHERE ordine_id = ?
                   AND (nome = ? OR nome LIKE ?)
                   AND tipo IN ('bevanda','bevanda_condivisa')
                   AND stato < 3
                 LIMIT 1"
            );
            $nomeLike = $nomeBevanda . '%';
            $stmt->bind_param('iss', $ordineId, $nomeBevanda, $nomeLike);
        }
        $stmt->execute();
        $esistente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($esistente) {
            echo json_encode([
                'success'   => true,
                'message'   => 'Bevanda già presente nell\'ordine',
                'riga_id'   => (int)$esistente['id'],
                'duplicato' => true
            ]);
            $conn->close();
            exit;
        }
        // ── FINE ANTI-DUPLICATO ───────────────────────────────

        // ── Partecipanti JSON nel formato oggetti {nome, lettera}
        $partecipantiJson = !empty($partecipantiObj) ? json_encode($partecipantiObj, JSON_UNESCAPED_UNICODE) : null;

        // ── INSERT
        $stmt = $conn->prepare(
            "INSERT INTO ordini_righe
                (ordine_id, piatto_id, nome, prezzo_unitario, quantita, totale_riga,
                 tipo, condivisione, partecipanti, stato)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
        );
        $stmt->bind_param(
            'iisdiisss',
            $ordineId, $piattoId, $nomeFinale, $prezzoUnit, $qta, $totaleRiga,
            $tipo, $condivisione, $partecipantiJson
        );
        $stmt->execute();
        $nuovoId = $conn->insert_id;
        $stmt->close();

        echo json_encode([
            'success'      => true,
            'message'      => 'Bevanda aggiunta',
            'riga_id'      => $nuovoId,
            'duplicato'    => false,
            'nome_finale'  => $nomeFinale,
            'partecipanti' => $partecipantiObj
        ]);

    } else {
        throw new Exception("Azione non riconosciuta: {$azione}");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();