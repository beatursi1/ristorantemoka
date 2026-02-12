<?php
// api/ordini/ordini-cucina.php
header('Content-Type: application/json; charset=utf-8');
require_once('../../config/config.php');

$conn = getDbConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Errore database']);
    exit;
}

// Query corretta: Colleghiamo il cliente usando tavolo_id e identificativo (Lettera)
$sql = "
    SELECT 
        r.id AS riga_id,
        r.nome AS piatto_nome,
        r.quantita,
        r.stato AS piatto_stato,
        r.tipo,
        o.id AS ordine_id,
        o.tavolo_id,
        t.numero AS tavolo_numero,
        o.cliente_lettera,
        sc.nome_cliente AS cliente_nome,
        o.creato_il AS data_ordine
    FROM ordini_righe r
    JOIN ordini_tavolo o ON r.ordine_id = o.id
    JOIN tavoli t ON o.tavolo_id = t.id
    LEFT JOIN sessioni_clienti sc ON o.tavolo_id = sc.tavolo_id AND o.cliente_lettera = sc.identificativo
    WHERE r.tipo NOT IN ('bevanda', 'bevanda_condivisa')
      AND (r.stato < 3 OR (r.stato = 3 AND o.aggiornato_il >= DATE_SUB(NOW(), INTERVAL 1 HOUR)))
    ORDER BY r.stato ASC, o.creato_il ASC
";

$result = $conn->query($sql);
$piatti = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $piatti[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $piatti]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$conn->close();