<?php
/**
 * Ordini Cucina
 * Restituisce i piatti in lavorazione per la schermata cucina
 *
 * @version 3.1.0
 */

define('ACCESS_ALLOWED', true);
require_once('../../config/config.php');
require_once('../../includes/auth.php');
require_once('../../includes/security.php');

initSecureSession();
checkAccess('cucina');
setSecurityHeaders();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

try {
    // Prepared statement — filtro r.tipo = 'piatto' esclude bevande dalla cucina
    $stmt = $conn->prepare(
        "SELECT
            ot.id           AS ordine_id,
            ot.stato        AS stato_ordine,
            ot.cliente_lettera,
            ot.creato_il,
            ot.aggiornato_il,
            t.numero        AS tavolo_numero,
            t.id            AS tavolo_id,
            r.id            AS riga_id,
            r.piatto_id,
            r.nome          AS piatto_nome,
            r.quantita,
            r.prezzo_unitario,
            r.totale_riga,
            r.stato         AS stato_riga,
            r.tipo,
            p.descrizione   AS piatto_descrizione,
            p.tempo_preparazione,
            sc.nome_cliente
        FROM ordini_tavolo ot
        INNER JOIN ordini_righe r
               ON r.ordine_id = ot.id
          LEFT JOIN piatti p
               ON p.id = r.piatto_id
          LEFT JOIN tavoli t
               ON t.id = ot.tavolo_id
          LEFT JOIN sessioni_clienti sc
               ON sc.tavolo_id     = ot.tavolo_id
              AND sc.identificativo = ot.cliente_lettera
        WHERE ot.stato IN ('inviato', 'in_preparazione', 'pronto')
          AND r.tipo = 'piatto'
        ORDER BY ot.creato_il ASC, ot.id ASC"
    );

    if (!$stmt) {
        throw new Exception("Errore preparazione query: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $ordini = [];

    while ($row = $result->fetch_assoc()) {
        $tempoAttesa = null;
        if ($row['creato_il']) {
            $tempoAttesa = round((time() - strtotime($row['creato_il'])) / 60);
        }

        $ordini[] = [
            'id'                  => (int)$row['riga_id'],
            'riga_id'             => (int)$row['riga_id'],
            'ordine_id'           => (int)$row['ordine_id'],
            'piatto_id'           => (int)$row['piatto_id'],
            'piatto_nome'         => $row['piatto_nome'],
            'piatto_descrizione'  => $row['piatto_descrizione'],
            'quantita'            => (int)$row['quantita'],
            'prezzo_unitario'     => (float)$row['prezzo_unitario'],
            'totale_riga'         => (float)$row['totale_riga'],
            'stato'               => $row['stato_ordine'],
            'piatto_stato'        => (int)$row['stato_riga'],
            'stato_riga'          => (int)$row['stato_riga'],
            'tempo_preparazione'  => $row['tempo_preparazione'] ? (int)$row['tempo_preparazione'] : null,
            'tempo_attesa_minuti' => $tempoAttesa,
            'created_at'          => $row['creato_il'],
            'data_ordine'         => $row['creato_il'],
            'updated_at'          => $row['aggiornato_il'],
            'ora_creazione'       => $row['creato_il']    ? date('H:i', strtotime($row['creato_il']))    : null,
            'ora_aggiornamento'   => $row['aggiornato_il'] ? date('H:i', strtotime($row['aggiornato_il'])) : null,
            'tavolo_numero'       => $row['tavolo_numero'],
            'tavolo' => [
                'id'     => $row['tavolo_id'] ? (int)$row['tavolo_id'] : null,
                'numero' => $row['tavolo_numero']
            ],
            'cliente_lettera' => $row['cliente_lettera'],
            'cliente_nome'    => $row['nome_cliente'] ?: ('Cliente ' . $row['cliente_lettera']),
            'cliente' => [
                'lettera' => $row['cliente_lettera'],
                'nome'    => $row['nome_cliente'] ?: ('Cliente ' . $row['cliente_lettera'])
            ],
            'is_ritardo' => $tempoAttesa !== null && $tempoAttesa > ($row['tempo_preparazione'] ?? 15),
            'priorita'   => $row['stato_ordine'] === 'in_preparazione' ? 'alta' : 'normale'
        ];
    }

    $stats = [
        'totale'          => count($ordini),
        'in_attesa'       => count(array_filter($ordini, fn($o) => $o['stato'] === 'inviato')),
        'in_preparazione' => count(array_filter($ordini, fn($o) => $o['stato'] === 'in_preparazione')),
        'pronti'          => count(array_filter($ordini, fn($o) => $o['stato'] === 'pronto')),
        'in_ritardo'      => count(array_filter($ordini, fn($o) => $o['is_ritardo']))
    ];

    echo json_encode([
        'success' => true,
        'data'    => $ordini,   // la cucina si aspetta 'data'
        'ordini'  => $ordini,   // mantenuto per compatibilità
        'stats'   => $stats,
        'count'   => count($ordini),
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();