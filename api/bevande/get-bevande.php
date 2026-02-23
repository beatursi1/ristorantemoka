<?php
/**
 * api/bevande/get-bevande.php
 * Restituisce tutte le bevande del giorno con contatori e tavoli attivi
 * @version 2.0.0
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

require_once('../../config/config.php');
$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

try {
    // Tutte le bevande di oggi (tutti gli stati)
    $stmt = $conn->prepare(
        "SELECT
            r.id,
            r.ordine_id,
            r.nome,
            r.quantita,
            r.stato,
            r.tipo,
            r.condivisione,
            r.partecipanti,
            ot.tavolo_id,
            ot.cliente_lettera,
            ot.creato_il,
            t.numero        AS tavolo_numero,
            sc.nome_cliente
        FROM ordini_righe r
        INNER JOIN ordini_tavolo ot ON ot.id  = r.ordine_id
        INNER JOIN tavoli t         ON t.id   = ot.tavolo_id
        LEFT JOIN  sessioni_clienti sc
               ON sc.tavolo_id      = ot.tavolo_id
              AND sc.identificativo  = ot.cliente_lettera
        WHERE r.tipo IN ('bevanda', 'bevanda_condivisa')
          AND DATE(ot.creato_il) = CURDATE()
        ORDER BY r.stato ASC, ot.creato_il ASC, r.id ASC"
    );

    if (!$stmt) throw new Exception("Errore preparazione query: " . $conn->error);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $bevande = [];
    $counts  = ['0' => 0, '1' => 0, '2' => 0, '3' => 0];
    $tavSet  = [];

    while ($row = $result->fetch_assoc()) {
        // Parse partecipanti
        $part = [];
        if (!empty($row['partecipanti'])) {
            $dec = json_decode($row['partecipanti'], true);
            if (is_array($dec)) {
                foreach ($dec as $p) {
                    if (is_string($p)) {
                        $part[] = $p;
                    } elseif (is_array($p)) {
                        $part[] = !empty($p['nome'])
                            ? $p['nome'] . ' (' . ($p['lettera'] ?? '') . ')'
                            : 'Cliente ' . ($p['lettera'] ?? '');
                    }
                }
            }
        }

        $stato = (string)$row['stato'];
        if (isset($counts[$stato])) $counts[$stato]++;
        $tavSet[$row['tavolo_id']] = $row['tavolo_numero'];

        $bevande[] = [
            'id'              => (int)$row['id'],
            'ordine_id'       => (int)$row['ordine_id'],
            'nome'            => $row['nome'],
            'quantita'        => (int)$row['quantita'],
            'stato'           => (int)$row['stato'],
            'tipo'            => $row['tipo'],
            'condivisione'    => $row['condivisione'] ?? 'personale',
            'partecipanti'    => $part,
            'tavolo_id'       => (int)$row['tavolo_id'],
            'tavolo_numero'   => $row['tavolo_numero'],
            'cliente_lettera' => $row['cliente_lettera'],
            'cliente_nome'    => $row['nome_cliente'] ?? '',
            'ora'             => $row['creato_il'] ? date('H:i', strtotime($row['creato_il'])) : ''
        ];
    }

    // Tavoli attivi oggi (per il modal aggiungi)
    $st = $conn->prepare(
        "SELECT DISTINCT t.id, t.numero
         FROM tavoli t
         INNER JOIN ordini_tavolo ot ON ot.tavolo_id = t.id
         WHERE DATE(ot.creato_il) = CURDATE()
         ORDER BY CAST(t.numero AS UNSIGNED) ASC"
    );
    $st->execute();
    $rt = $st->get_result();
    $st->close();

    $tavoli_attivi = [];
    while ($r = $rt->fetch_assoc()) {
        $tavoli_attivi[] = ['id' => (int)$r['id'], 'numero' => $r['numero']];
    }

    echo json_encode([
        'success'       => true,
        'bevande'       => $bevande,
        'counts'        => $counts,
        'tavoli_attivi' => $tavoli_attivi,
        'totale'        => count($bevande)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();