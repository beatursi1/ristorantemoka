<?php
// ordini-nuovo.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once('../../config/config.php');

$conn = getDbConnection();

if (!$conn) {
    echo json_encode([
        'success' => false,
        'error'   => 'Database connection failed'
    ]);
    exit;
}

/**
 * Questa API restituisce gli ordini dal nuovo modello:
 * - ordini_tavolo (testata)
 * - ordini_righe (dettaglio)
 * 
 * Formato risposta:
 * {
 *   success: true,
 *   data: [
 *     {
 *       id: 1,
 *       sessione_id: 5,
 *       tavolo_id: 3,
 *       tavolo_numero: "3",
 *       cliente_lettera: "B",
 *       stato: "inviato",
 *       totale: 42.50,
 *       creato_il: "2026-01-20 21:30:00",
 *       righe: [
 *         {
 *           id: 10,
 *           piatto_id: 7,
 *           nome: "Spaghetti alla carbonara",
 *           prezzo_unitario: 12.50,
 *           quantita: 2,
 *           totale_riga: 25.00,
 *           tipo: "piatto",
 *           condivisione: "personale"
 *         },
 *         ...
 *       ]
 *     },
 *     ...
 *   ],
 *   count: N,
 *   timestamp: "..."
 * }
 */

// Legge solo gli ordini non consegnati/annullati
$sqlOrdini = "
    SELECT 
        o.id,
        o.sessione_id,
        o.tavolo_id,
        t.numero AS tavolo_numero,
        o.cliente_lettera,
        o.stato,
        o.totale,
        o.creato_il,
        o.aggiornato_il
    FROM ordini_tavolo o
    LEFT JOIN tavoli t ON t.id = o.tavolo_id
    WHERE o.stato NOT IN ('consegnato', 'annullato')
    ORDER BY o.creato_il DESC
";

$resultOrdini = $conn->query($sqlOrdini);

if (!$resultOrdini) {
    echo json_encode([
        'success' => false,
        'error'   => 'Query ordini_tavolo failed: ' . $conn->error
    ]);
    exit;
}

$ordini = [];
$ordiniIds = [];

while ($row = $resultOrdini->fetch_assoc()) {
    $ordineId = (int)$row['id'];
    $ordiniIds[] = $ordineId;

    $ordini[$ordineId] = [
        'id'             => $ordineId,
        'sessione_id'    => isset($row['sessione_id']) ? (int)$row['sessione_id'] : null,
        'tavolo_id'      => isset($row['tavolo_id']) ? (int)$row['tavolo_id'] : null,
        'tavolo_numero'  => $row['tavolo_numero'],
        'cliente_lettera'=> $row['cliente_lettera'],
        'stato'          => $row['stato'],
        'totale'         => (float)$row['totale'],
        'creato_il'      => $row['creato_il'],
        'aggiornato_il'  => $row['aggiornato_il'],
        'righe'          => []
    ];
}

// Se non ci sono ordini, restituisce lista vuota
if (empty($ordiniIds)) {
    echo json_encode([
        'success'   => true,
        'data'      => [],
        'count'     => 0,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug'     => ['sql_ordini' => $sqlOrdini]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

// Carica le righe per tutti gli ordini trovati
$idsPlaceholders = implode(',', array_fill(0, count($ordiniIds), '?'));
$sqlRighe = "
    SELECT 
        r.id,
        r.ordine_id,
        r.piatto_id,
        r.nome,
        r.prezzo_unitario,
        r.quantita,
        r.totale_riga,
        r.tipo,
        r.condivisione
    FROM ordini_righe r
    WHERE r.ordine_id IN ($idsPlaceholders)
    ORDER BY r.id ASC
";

$stmtRighe = $conn->prepare($sqlRighe);
if (!$stmtRighe) {
    echo json_encode([
        'success' => false,
        'error'   => 'Prepare ordini_righe failed: ' . $conn->error
    ]);
    $conn->close();
    exit;
}

// Bind dinamico dei parametri (tutti int)
$types = str_repeat('i', count($ordiniIds));
$stmtRighe->bind_param($types, ...$ordiniIds);

$stmtRighe->execute();
$resRighe = $stmtRighe->get_result();

while ($r = $resRighe->fetch_assoc()) {
    $ordineId = (int)$r['ordine_id'];
    if (!isset($ordini[$ordineId])) {
        continue; // sicurezza
    }

    $ordini[$ordineId]['righe'][] = [
        'id'             => (int)$r['id'],
        'piatto_id'      => (int)$r['piatto_id'],
        'nome'           => $r['nome'],
        'prezzo_unitario'=> (float)$r['prezzo_unitario'],
        'quantita'       => (int)$r['quantita'],
        'totale_riga'    => (float)$r['totale_riga'],
        'tipo'           => $r['tipo'],
        'condivisione'   => $r['condivisione']
    ];
}

$stmtRighe->close();
$conn->close();

// Converti array associativo in indicizzato per l'output
$ordiniList = array_values($ordini);

$response = [
    'success'   => true,
    'data'      => $ordiniList,
    'count'     => count($ordiniList),
    'timestamp' => date('Y-m-d H:i:s'),
    'debug'     => [
        'sql_ordini' => $sqlOrdini,
        'ordini_ids' => $ordiniIds
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);