<?php
// sessione-clienti.php
// API: sessione-clienti.php
// Restituisce la lista dei clienti (lettere/nome) per una data sessione/tavolo/sessione_cliente_id
// Input JSON (POST): { sessione: string|null, tavolo_id: number|null, sessione_cliente_id: string|null }
// Output JSON: { success: true, clienti: [ { id: "A", nome: "Cliente A" }, ... ] } oppure { success:false, error: "..." }

header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../../config/config.php'); // deve fornire getDbConnection()
$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

// Read input
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$sessioneToken = isset($input['sessione']) ? trim($input['sessione']) : null;
$tavolo_id = isset($input['tavolo_id']) ? $input['tavolo_id'] : null;
$sessione_cliente_id = isset($input['sessione_cliente_id']) ? trim($input['sessione_cliente_id']) : null;

// Normalize tavolo_id to int if provided
if ($tavolo_id !== null && $tavolo_id !== '') {
    $tavolo_id = is_numeric($tavolo_id) ? intval($tavolo_id) : null;
}

// Try to resolve sessione_id and tavolo_id from sessione token if given
$resolvedSessioneId = null;
$resolvedTavoloId = $tavolo_id;

if ($sessioneToken) {
    $sql = "SELECT s.id AS sessione_id, s.tavolo_id, t.numero AS tavolo_numero
            FROM sessioni_tavolo s
            LEFT JOIN tavoli t ON t.id = s.tavolo_id
            WHERE s.token = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $sessioneToken);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $resolvedSessioneId = isset($row['sessione_id']) ? intval($row['sessione_id']) : null;
                if ($resolvedSessioneId === 0) $resolvedSessioneId = null;
                if (isset($row['tavolo_id']) && $row['tavolo_id'] !== null) {
                    $resolvedTavoloId = intval($row['tavolo_id']);
                }
            }
        }
        $stmt->close();
    }
}

// If sessione_cliente_id looks like an internal id, we may accept it as sessione cliente identifier,
// but we prefer to query sessioni_clienti by sessione_id (resolvedSessioneId) first.
$clients = [];

// Helper to push unique client ids
$seen = [];

// Candidate session-client tables (common variants)
$candidateSessioniClientiTables = [
    'sessioni_clienti',
    'sessione_clienti',
    'sessioni_clienti_assoc',
    'sessione_clienti_assoc',
    'sessioni_clienti_rel',
    'sessioni_clienti_map',
    'sessione_clienti_map'
];

// Try to query sessioni_clienti-like tables by sessione_id or sessione_cliente_id
foreach ($candidateSessioniClientiTables as $tbl) {
    // check table exists
    $safeTbl = mysqli_real_escape_string($conn, $tbl);
    $existsRes = mysqli_query($conn, "SHOW TABLES LIKE '{$safeTbl}'");
    if (!$existsRes || mysqli_num_rows($existsRes) === 0) continue;

    // get columns
    $colsRes = mysqli_query($conn, "SHOW COLUMNS FROM `{$safeTbl}`");
    if (!$colsRes) continue;
    $cols = [];
    while ($c = mysqli_fetch_assoc($colsRes)) $cols[] = $c['Field'];

    // Determine candidate id/name/session columns
    $idCol = null;
    foreach (['lettera', 'id', 'cliente_id', 'cliente_lettera', 'codice'] as $c) {
        if (in_array($c, $cols)) { $idCol = $c; break; }
    }
    $nameCol = null;
    foreach (['nome', 'name', 'cliente_nome', 'display_name'] as $c) {
        if (in_array($c, $cols)) { $nameCol = $c; break; }
    }
    $sessionCol = null;
    foreach (['sessione_id', 'session_id', 'sessione', 'sessione_cliente_id'] as $c) {
        if (in_array($c, $cols)) { $sessionCol = $c; break; }
    }
    $tavoloCol = null;
    foreach (['tavolo_id', 'tavolo'] as $c) {
        if (in_array($c, $cols)) { $tavoloCol = $c; break; }
    }

    // Build query conditions
    $where = [];
    $params = [];
    $types = '';

    if ($resolvedSessioneId !== null && $sessionCol) {
        $where[] = "`{$sessionCol}` = ?";
        $params[] = $resolvedSessioneId;
        $types .= 'i';
    }
    if ($sessione_cliente_id && $sessionCol) {
        $where[] = "`{$sessionCol}` = ?";
        $params[] = $sessione_cliente_id;
        $types .= 's';
    }
    if ($resolvedTavoloId !== null && $tavoloCol) {
        $where[] = "`{$tavoloCol}` = ?";
        $params[] = $resolvedTavoloId;
        $types .= 'i';
    }

    if (empty($where)) continue;

    $selId = $idCol ?: (in_array('id', $cols) ? 'id' : $cols[0]);
    $selName = $nameCol ?: (in_array('nome', $cols) ? 'nome' : (count($cols) > 1 ? $cols[1] : $selId));

    $sql = "SELECT `{$selId}` AS client_id, `{$selName}` AS client_name FROM `{$safeTbl}` WHERE (" . implode(' OR ', $where) . ") ORDER BY client_name ASC LIMIT 500";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) continue;

    if (!empty($params)) {
        // bind params dynamically
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $idVal = (string)($row['client_id'] ?? '');
                $nameVal = (string)($row['client_name'] ?? $idVal);
                if ($idVal === '') continue;
                if (isset($seen[$idVal])) continue;
                $seen[$idVal] = true;
                $clients[] = ['id' => $idVal, 'nome' => $nameVal];
            }
        }
    }
    mysqli_stmt_close($stmt);

    if (!empty($clients)) break;
}

// If still empty try generic 'clienti' table filtered by tavolo
if (empty($clients)) {
    $safeTbl = 'clienti';
    $existsRes = mysqli_query($conn, "SHOW TABLES LIKE '{$safeTbl}'");
    if ($existsRes && mysqli_num_rows($existsRes) > 0) {
        $colsRes = mysqli_query($conn, "SHOW COLUMNS FROM `{$safeTbl}`");
        $cols = [];
        while ($c = mysqli_fetch_assoc($colsRes)) $cols[] = $c['Field'];
        $idCol = in_array('lettera', $cols) ? 'lettera' : (in_array('id', $cols) ? 'id' : $cols[0]);
        $nameCol = in_array('nome', $cols) ? 'nome' : (count($cols) > 1 ? $cols[1] : $idCol);

        if (in_array('tavolo_id', $cols) && $resolvedTavoloId !== null) {
            $sql = "SELECT `$idCol` AS client_id, `$nameCol` AS client_name FROM `clienti` WHERE `tavolo_id` = ? ORDER BY client_name ASC";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                $stmt->bind_param('i', $resolvedTavoloId);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    while ($row = mysqli_fetch_assoc($res)) {
                        $idVal = (string)($row['client_id'] ?? '');
                        $nameVal = (string)($row['client_name'] ?? $idVal);
                        if ($idVal === '') continue;
                        if (isset($seen[$idVal])) continue;
                        $seen[$idVal] = true;
                        $clients[] = ['id' => $idVal, 'nome' => $nameVal];
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Final fallback: placeholders A..H
if (empty($clients)) {
    $letters = ['A','B','C','D','E','F','G','H'];
    foreach ($letters as $l) {
        $clients[] = ['id' => $l, 'nome' => 'Cliente ' . $l];
    }
}

echo json_encode(['success' => true, 'clienti' => $clients], JSON_UNESCAPED_UNICODE);
exit;
?>