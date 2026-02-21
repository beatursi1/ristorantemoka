<?php
/**
 * api/tavoli/get-tavoli-occupati.php
 * Restituisce i tavoli con sessione attiva — usato dalla cassa admin
 * Esclude sessioni fantasma: aperte da più di 2 ore senza clienti NÉ ordini
 * @version 1.1.0
 */
session_start();
define('ACCESS_ALLOWED', true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['utente_id']) || $_SESSION['utente_ruolo'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit;
}

require_once('../../config/config.php');
$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT
            t.id,
            t.numero,
            t.stato,
            st.id                             AS sessione_id,
            st.aperta_il,
            COUNT(DISTINCT sc.identificativo) AS num_clienti,
            COUNT(DISTINCT ot.id)             AS num_ordini,
            COALESCE(SUM(r.totale_riga), 0)   AS totale_provvisorio
         FROM tavoli t
         INNER JOIN sessioni_tavolo st
                ON st.tavolo_id = t.id AND st.stato = 'attiva'
         LEFT JOIN sessioni_clienti sc
                ON sc.tavolo_id = t.id
         LEFT JOIN ordini_tavolo ot
                ON ot.tavolo_id = t.id AND ot.sessione_id = st.id
         LEFT JOIN ordini_righe r
                ON r.ordine_id = ot.id
         GROUP BY t.id, t.numero, t.stato, st.id, st.aperta_il
         HAVING
             -- Includi sempre se ha clienti O ordini
             num_clienti > 0
             OR num_ordini > 0
             -- Includi anche se è stata aperta da meno di 2 ore (sessione recente, cliente in arrivo)
             OR st.aperta_il >= DATE_SUB(NOW(), INTERVAL 3 HOUR)
         ORDER BY CAST(t.numero AS UNSIGNED) ASC"
    );
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $tavoli = [];
    while ($row = $res->fetch_assoc()) {
        $minutiAperti = null;
        if ($row['aperta_il']) {
            $minutiAperti = (int)round((time() - strtotime($row['aperta_il'])) / 60);
        }
        $tavoli[] = [
            'id'                 => (int)$row['id'],
            'numero'             => $row['numero'],
            'stato'              => $row['stato'],
            'sessione_id'        => (int)$row['sessione_id'],
            'aperta_il'          => $row['aperta_il'],
            'minuti_aperti'      => $minutiAperti,
            'num_clienti'        => (int)$row['num_clienti'],
            'totale_provvisorio' => round((float)$row['totale_provvisorio'], 2)
        ];
    }

    echo json_encode([
        'success' => true,
        'tavoli'  => $tavoli,
        'count'   => count($tavoli)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();