<?php
// get-ordini-cliente.php
// ATTIVA errori (solo per debugging; puoi mettere display_errors a 0 in produzione)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once('../../config/config.php');

$response = [
    'success' => false,
    'ordini'  => [],
    'error'   => null
];

// Ottieni connessione MySQLi (come fai in crea-ordine.php)
$conn = getDbConnection();
if (!$conn) {
    $response['error'] = 'Errore di connessione al database';
    echo json_encode($response);
    exit;
}

// Leggiamo parametri: supportiamo JSON POST (client) e fallback a GET
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) $input = [];

// Preferiamo i valori inviati via JSON POST; se non presenti, prendiamo da $_GET
$tavoloIdUrl = isset($input['tavolo_id']) ? (int)$input['tavolo_id'] : (isset($_GET['tavolo_id']) ? (int)$_GET['tavolo_id'] : 0);
$sessioneToken = isset($input['sessione']) ? trim($input['sessione']) : (isset($_GET['sessione']) ? trim($_GET['sessione']) : '');
$sessioneClienteId = isset($input['sessione_cliente_id']) ? trim($input['sessione_cliente_id']) : (isset($_GET['sessione_cliente_id']) ? trim($_GET['sessione_cliente_id']) : '');

// Validazione minima parametri
if ($tavoloIdUrl <= 0 || $sessioneToken === '' || $sessioneClienteId === '') {
    $response['error'] = 'Parametri mancanti o non validi (tavolo_id, sessione e sessione_cliente_id sono richiesti)';
    echo json_encode($response);
    $conn->close();
    exit;
}

// Recupera la sessione_cliente a partire da sessione_cliente_id
$clienteLetteraDb = null;
$tavoloIdDb       = null;

$sqlSessCliente = "SELECT id, tavolo_id, identificativo 
                   FROM sessioni_clienti 
                   WHERE id = ? 
                   LIMIT 1";
$stmtSessCli = $conn->prepare($sqlSessCliente);
if (!$stmtSessCli) {
    $response['error'] = 'Errore prepare sessioni_clienti: ' . $conn->error;
    echo json_encode($response);
    $conn->close();
    exit;
}

$stmtSessCli->bind_param('s', $sessioneClienteId);
if (!$stmtSessCli->execute()) {
    $response['error'] = 'Errore execute sessioni_clienti: ' . $stmtSessCli->error;
    echo json_encode($response);
    $stmtSessCli->close();
    $conn->close();
    exit;
}

$resSessCli = $stmtSessCli->get_result();
if ($resSessCli && $rowSessCli = $resSessCli->fetch_assoc()) {
    $tavoloIdDb      = (int)$rowSessCli['tavolo_id'];
    $clienteLetteraDb = $rowSessCli['identificativo']; // Es: 'A', 'B', ...
} else {
    $response['error'] = 'Sessione cliente non trovata o non valida';
    echo json_encode($response);
    $stmtSessCli->close();
    $conn->close();
    exit;
}
$stmtSessCli->close();

// Verifica coerenza tavolo: quello della sessione cliente deve coincidere con quello passato nell'URL
if ($tavoloIdDb !== $tavoloIdUrl) {
    $response['error'] = 'Incoerenza nei dati del tavolo per questa sessione cliente';
    echo json_encode($response);
    $conn->close();
    exit;
}

// Recupera la sessione_tavolo a partire dal token
$sessioneId = null;

$sqlSessione = "SELECT id, tavolo_id FROM sessioni_tavolo WHERE token = ? LIMIT 1";
$stmtSess = $conn->prepare($sqlSessione);
if (!$stmtSess) {
    $response['error'] = 'Errore prepare sessioni_tavolo: ' . $conn->error;
    echo json_encode($response);
    $conn->close();
    exit;
}

$stmtSess->bind_param('s', $sessioneToken);
if (!$stmtSess->execute()) {
    $response['error'] = 'Errore execute sessioni_tavolo: ' . $stmtSess->error;
    echo json_encode($response);
    $stmtSess->close();
    $conn->close();
    exit;
}

$resSess = $stmtSess->get_result();
if ($resSess && $rowSess = $resSess->fetch_assoc()) {
    $sessioneId = (int)$rowSess['id'];
} else {
    $response['error'] = 'Sessione tavolo non trovata o non valida';
    echo json_encode($response);
    $stmtSess->close();
    $conn->close();
    exit;
}
$stmtSess->close();

try {
    // NOTE: Prima si recuperano TUTTI gli ordini del tavolo per la sessione_tavolo,
    // poi si filtrano lato server quelli rilevanti per il cliente corrente (cliente_lettera
    // oppure partecipanti che contengono la sua lettera o sessione_cliente_id).
    $sqlOrdini = "
        SELECT 
            id,
            sessione_id,
            tavolo_id,
            cliente_lettera,
            stato,
            totale,
            creato_il,
            aggiornato_il
        FROM ordini_tavolo
        WHERE tavolo_id = ?
          AND sessione_id = ?
        ORDER BY creato_il DESC
    ";

    $stmtOrdini = $conn->prepare($sqlOrdini);
    if (!$stmtOrdini) {
        throw new Exception('Errore prepare ordini_tavolo: ' . $conn->error);
    }

    $stmtOrdini->bind_param('ii', $tavoloIdDb, $sessioneId);
    if (!$stmtOrdini->execute()) {
        throw new Exception('Errore execute ordini_tavolo: ' . $stmtOrdini->error);
    }

    $result = $stmtOrdini->get_result();
    $ordini = [];
    while ($row = $result->fetch_assoc()) {
        $ordini[] = $row;
    }
    $stmtOrdini->close();

    // Se non ci sono ordini, restituiamo lista vuota
    if (!$ordini) {
        $response['success'] = true;
        $response['ordini']  = [];
        echo json_encode($response);
        $conn->close();
        exit;
    }

    // 2) Recupera tutte le righe per questi ordini in un colpo solo (includiamo 'partecipanti')
    $ordineIds = array_column($ordini, 'id');
    $placeholders = implode(',', array_fill(0, count($ordineIds), '?'));

    $sqlRighe = "
        SELECT 
            id,
            ordine_id,
            piatto_id,
            nome,
            prezzo_unitario,
            quantita,
            totale_riga,
            tipo,
            condivisione,
            partecipanti
        FROM ordini_righe
        WHERE ordine_id IN ($placeholders)
        ORDER BY ordine_id ASC, id ASC
    ";

    $stmtRighe = $conn->prepare($sqlRighe);
    if (!$stmtRighe) {
        throw new Exception('Errore prepare ordini_righe: ' . $conn->error);
    }

    // bind_param dinamico: tutti gli id sono int
    $types = str_repeat('i', count($ordineIds));
    $bindParams = array_merge([$types], $ordineIds);
    $refs = [];
    foreach ($bindParams as $key => $value) {
        $refs[$key] = &$bindParams[$key];
    }
    call_user_func_array([$stmtRighe, 'bind_param'], $refs);

    if (!$stmtRighe->execute()) {
        throw new Exception('Errore execute ordini_righe: ' . $stmtRighe->error);
    }

    $resRighe = $stmtRighe->get_result();
    $righePerOrdine = [];
    while ($row = $resRighe->fetch_assoc()) {
        $oid = (int)$row['ordine_id'];
        if (!isset($righePerOrdine[$oid])) {
            $righePerOrdine[$oid] = [];
        }

        // Decodifica partecipanti (se presenti)
        $partecipanti = null;
        if (isset($row['partecipanti']) && $row['partecipanti'] !== null && $row['partecipanti'] !== '') {
            $raw = $row['partecipanti'];
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $normalized = [];
                foreach ($decoded as $p) {
                    if (is_string($p)) {
                        $normalized[] = $p;
                    } elseif (is_array($p)) {
                        if (!empty($p['lettera'])) $normalized[] = $p['lettera'];
                        elseif (!empty($p['sessione_cliente_id'])) $normalized[] = $p['sessione_cliente_id'];
                        else $normalized[] = $p;
                    } else {
                        $normalized[] = (string)$p;
                    }
                }
                $partecipanti = $normalized;
            } else {
                // fallback: prova CSV
                $parts = array_map('trim', explode(',', $raw));
                $parts = array_filter($parts, function($v){ return $v !== ''; });
                if (!empty($parts)) $partecipanti = array_values($parts);
            }
        }

        $righePerOrdine[$oid][] = [
            'id'             => isset($row['id']) ? (int)$row['id'] : null,
            'piatto_id'      => isset($row['piatto_id']) ? (int)$row['piatto_id'] : null,
            'nome'           => $row['nome'],
            'prezzo_unitario'=> (float)$row['prezzo_unitario'],
            'quantita'       => (int)$row['quantita'],
            'totale_riga'    => (float)$row['totale_riga'],
            'tipo'           => $row['tipo'],
            'condivisione'   => $row['condivisione'],
            'partecipanti'   => $partecipanti
        ];
    }
    $stmtRighe->close();

    // 3) Costruisci output finale per ogni ordine, filtrando per il cliente corrente
    $ordiniOutput = [];
    foreach ($ordini as $ord) {
        $idOrdine = (int)$ord['id'];
        $righe = $righePerOrdine[$idOrdine] ?? [];

        // Determina se questo ordine è rilevante per il client corrente:
        // - è stato creato per la sua lettera (cliente_lettera)
        // OR
        // - una qualsiasi riga contiene partecipanti che includono la sua lettera o il suo sessione_cliente_id
        $isRelevant = false;
        if (isset($ord['cliente_lettera']) && $ord['cliente_lettera'] === $clienteLetteraDb) {
            $isRelevant = true;
        } else {
            // controlla partecipanti nelle righe
            foreach ($righe as $r) {
                if (!empty($r['partecipanti']) && is_array($r['partecipanti'])) {
                    // match sia per lettera che per sessione_cliente_id
                    if (in_array($clienteLetteraDb, $r['partecipanti'], true) || in_array($sessioneClienteId, $r['partecipanti'], true)) {
                        $isRelevant = true;
                        break;
                    }
                }
            }
        }

        if (!$isRelevant) {
            // non includere ordini non rilevanti
            continue;
        }

        // Calcolo totale da righe (anche se in ordini_tavolo hai già "totale")
        $totaleCalcolato = 0.0;
        foreach ($righe as $r) {
            $totaleCalcolato += $r['totale_riga'];
        }

        $ordiniOutput[] = [
            'id'              => $idOrdine,
            'sessione_id'     => (int)$ord['sessione_id'],
            'tavolo_id'       => (int)$ord['tavolo_id'],
            'cliente_lettera' => $ord['cliente_lettera'],
            'stato'           => $ord['stato'],
            'totale'          => (float)$ord['totale'],  // totale salvato
            'totale_righe'    => $totaleCalcolato,       // totale ricalcolato
            'creato_il'       => $ord['creato_il'],
            'aggiornato_il'   => $ord['aggiornato_il'],
            'righe'           => $righe,
        ];
    }

    $response['success'] = true;
    $response['ordini']  = $ordiniOutput;

} catch (Throwable $e) {
    $response['success'] = false;
    $response['error']   = 'Eccezione: ' . $e->getMessage();
}

echo json_encode($response);
$conn->close();
exit;