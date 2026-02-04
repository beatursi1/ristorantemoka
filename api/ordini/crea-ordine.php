<?php
// crea-ordine.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once('../../config/config.php');

$conn = getDbConnection();
if (!$conn) {
    echo json_encode([
        'success' => false,
        'error'   => 'Errore di connessione al database'
    ]);
    exit;
}

// Leggi JSON dal body
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

// Se non riesce a leggere JSON, prova con POST normale (compatibilità)
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Dati non validi (JSON mancante o malformato)'
    ]);
    exit;
}

// Estrai parametri principali
$session_id      = isset($data['session_id']) ? $data['session_id'] : null;          // vecchia sessione cliente (se ancora usata)
$sessione_token  = isset($data['sessione_token']) ? trim($data['sessione_token']) : null; // nuovo token di SessioneTavolo
$tavolo_id       = isset($data['tavolo_id']) ? (int)$data['tavolo_id'] : 0;
$righe_ordine    = isset($data['ordine']) && is_array($data['ordine']) ? $data['ordine'] : [];

// Validazione minima
if (empty($righe_ordine)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Nessun articolo nell\'ordine'
    ]);
    exit;
}

if ($tavolo_id <= 0 && !$sessione_token) {
    echo json_encode([
        'success' => false,
        'error'   => 'Manca tavolo_id e sessione_token. Almeno uno è richiesto.'
    ]);
    exit;
}

// Trova la SessioneTavolo
$sessione_id = null;

// 1) Se ho il token di sessione, provo a usarlo per trovare la sessione_tavolo
if ($sessione_token) {
    $sql = "SELECT id, tavolo_id FROM sessioni_tavolo WHERE token = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $sessione_token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $sessione_id = (int)$row['id'];
            // Se tavolo_id non era valorizzato nel JSON, lo prendo da qui
            if ($tavolo_id <= 0 && !empty($row['tavolo_id'])) {
                $tavolo_id = (int)$row['tavolo_id'];
            }
        }
        $stmt->close();
    }
}

// 2) Se ancora sessione_id è null, si potrebbe (in futuro) creare una nuova sessione
// Per ora, richiediamo che la sessione esista se ci è stato passato un token
if ($sessione_token && !$sessione_id) {
    echo json_encode([
        'success' => false,
        'error'   => 'Sessione non trovata o non valida per il token fornito'
    ]);
    exit;
}

// 3) Se non abbiamo token ma solo tavolo_id, possiamo comunque procedere
// creando un ordine legato solo al tavolo (compatibilità).
if ($tavolo_id <= 0) {
    echo json_encode([
        'success' => false,
        'error'   => 'tavolo_id non valido'
    ]);
    exit;
}

// Per ora, il cliente è identificato dalla lettera in ogni riga ordine.
// Prendiamo la prima riga e usiamo il suo "cliente" come riferimento principale.
$cliente_lettera = null;
foreach ($righe_ordine as $r) {
    if (isset($r['cliente']) && $r['cliente'] !== '') {
        $cliente_lettera = substr($r['cliente'], 0, 1); // solo la lettera
        break;
    }
}

if (!$cliente_lettera) {
    echo json_encode([
        'success' => false,
        'error'   => 'Cliente non specificato nelle righe ordine'
    ]);
    exit;
}

// Calcola totale ordine
$totale_ordine = 0.0;
foreach ($righe_ordine as $r) {
    $prezzo   = isset($r['prezzo']) ? (float)$r['prezzo'] : 0.0;
    $quantita = isset($r['quantita']) ? (int)$r['quantita'] : 1;
    if ($quantita <= 0) $quantita = 1;
    $totale_ordine += $prezzo * $quantita;
}

// Inizio transazione
$conn->begin_transaction();

try {
    // Inserisci testa ordine in ordini_tavolo
    $sqlOrdine = "
        INSERT INTO ordini_tavolo
            (sessione_id, tavolo_id, cliente_lettera, stato, totale, note, creato_il, aggiornato_il)
        VALUES
            (?, ?, ?, 'inviato', ?, NULL, NOW(), NOW())
    ";
    $stmtOrdine = $conn->prepare($sqlOrdine);
    if (!$stmtOrdine) {
        throw new Exception('Errore prepare ordini_tavolo: ' . $conn->error);
    }

    // sessione_id può essere null: usiamo "i" ma passiamo null come int: MySQL lo accetta come NULL
    $stmtOrdine->bind_param(
        'iisd',
        $sessione_id,
        $tavolo_id,
        $cliente_lettera,
        $totale_ordine
    );

    if (!$stmtOrdine->execute()) {
        throw new Exception('Errore insert ordini_tavolo: ' . $stmtOrdine->error);
    }

    $ordine_id = $stmtOrdine->insert_id;
    $stmtOrdine->close();

    // Inserisci righe ordine in ordini_righe (ora includiamo la colonna partecipanti JSON)
    $sqlRiga = "
        INSERT INTO ordini_righe
            (ordine_id, piatto_id, nome, prezzo_unitario, quantita, totale_riga, tipo, condivisione, partecipanti)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmtRiga = $conn->prepare($sqlRiga);
    if (!$stmtRiga) {
        throw new Exception('Errore prepare ordini_righe: ' . $conn->error);
    }

    foreach ($righe_ordine as $r) {
        $piatto_id = isset($r['id']) ? (int)$r['id'] : (isset($r['piatto_id']) ? (int)$r['piatto_id'] : 0);
        $nome      = isset($r['nome']) ? $r['nome'] : 'Voce menu';
        $prezzo    = isset($r['prezzo']) ? (float)$r['prezzo'] : (isset($r['prezzo_unitario']) ? (float)$r['prezzo_unitario'] : 0.0);
        $quantita  = isset($r['quantita']) ? (int)$r['quantita'] : 1;
        if ($quantita <= 0) $quantita = 1;
        $totale_riga = $prezzo * $quantita;

        // Tipo e condivisione per futuro uso (bevande condivise, ecc.)
        $tipo        = isset($r['tipo']) && in_array($r['tipo'], ['piatto', 'bevanda', 'bevanda_condivisa'])
                        ? $r['tipo']
                        : 'piatto';
        $condivisione = isset($r['condivisione']) && in_array($r['condivisione'], ['personale', 'tavolo', 'parziale', 'gruppo'])
                        ? $r['condivisione']
                        : 'personale';

        // Normalizza partecipanti: accettiamo sia array di stringhe (es. ["A","B"]) sia array di oggetti
        $partecipantiArr = null;
        if (!empty($r['partecipanti']) && is_array($r['partecipanti'])) {
            $partecipantiArr = [];
            foreach ($r['partecipanti'] as $p) {
                if (is_string($p)) {
                    // solo lettera (es. "A")
                    $partecipantiArr[] = ['lettera' => $p, 'sessione_cliente_id' => null];
                } elseif (is_array($p)) {
                    $partecipantiArr[] = [
                        'lettera' => $p['lettera'] ?? ($p['cliente'] ?? null),
                        'sessione_cliente_id' => $p['sessione_cliente_id'] ?? $p['session_id'] ?? null
                    ];
                } else {
                    // valore non previsto: proviamo a cast to string
                    $partecipantiArr[] = ['lettera' => (string)$p, 'sessione_cliente_id' => null];
                }
            }
        }

        $partecipantiJson = null;
        if (!empty($partecipantiArr)) {
            $partecipantiJson = json_encode($partecipantiArr, JSON_UNESCAPED_UNICODE);
        }

        // Bind parameters: tipi coerenti (i,i,s,d,i,d,s,s,s)
        // ordine_id (i), piatto_id (i), nome (s), prezzo_unitario (d), quantita (i), totale_riga (d), tipo (s), condivisione (s), partecipanti (s)
        $stmtRiga->bind_param(
            'iisdidsss',
            $ordine_id,
            $piatto_id,
            $nome,
            $prezzo,
            $quantita,
            $totale_riga,
            $tipo,
            $condivisione,
            $partecipantiJson
        );

        if (!$stmtRiga->execute()) {
            throw new Exception('Errore insert ordini_righe: ' . $stmtRiga->error);
        }
    }

    $stmtRiga->close();

    // Facoltativo: log semplice (in una tabella log_attivita, se esiste)
    if (tableExists($conn, 'log_attivita')) {
        $stmtLog = $conn->prepare("INSERT INTO log_attivita (tipo, descrizione, session_id, tavolo_id, dettagli) 
                                   VALUES ('ordine', 'Nuovo ordine inviato (nuovo sistema)', ?, ?, ?)");
        if ($stmtLog) {
            $dettagli = json_encode([
                'ordine_id'      => $ordine_id,
                'totale'         => $totale_ordine,
                'righe'          => count($righe_ordine),
                'sessione_token' => $sessione_token
            ], JSON_UNESCAPED_UNICODE);
            $session_id_log = $session_id ?? '';
            $stmtLog->bind_param("sis", $session_id_log, $tavolo_id, $dettagli);
            $stmtLog->execute();
            $stmtLog->close();
        }
    }

    // Commit
    $conn->commit();

    echo json_encode([
        'success'     => true,
        'message'     => 'Ordine registrato con successo (nuovo sistema)',
        'ordine_id'   => (int)$ordine_id,
        'totale'      => $totale_ordine,
        'sessione_id' => $sessione_id,
        'tavolo_id'   => $tavolo_id
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Errore nella creazione dell\'ordine: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Funzione helper per verificare se una tabella esiste
function tableExists(mysqli $conn, string $table): bool {
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return $res && $res->num_rows > 0;
}

$conn->close();