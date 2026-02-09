<?php
// api/menu/menu.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Permette chiamate da qualsiasi dominio (per testing)
header('Access-Control-Allow-Methods: GET');

require_once('../../config/config.php');

$response = [];
$conn = getDbConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    // 1) Leggiamo tutte le categorie visibili
    $sqlCategorie = "
        SELECT
            c.id,
            c.nome,
            c.ordine
        FROM categorie_menu c
        WHERE c.visibile = TRUE
        ORDER BY c.ordine
    ";

    $resCat = $conn->query($sqlCategorie);
    if (!$resCat) {
        throw new Exception("Query categorie failed: " . $conn->error);
    }

    $menu = [];
    while ($row = $resCat->fetch_assoc()) {
        $catId = (int)$row['id'];
        $menu[$catId] = [
            'id' => $catId,
            'nome' => $row['nome'],
            'ordine' => (int)$row['ordine'],
            'piatti' => [],          // piatti SENZA sottocategoria
            'sottocategorie' => []   // sottocategorie con relativi piatti
        ];
    }

    // Se non ci sono categorie, restituiamo subito
    if (empty($menu)) {
        $response = [
            'success' => true,
            'data' => [],
            'timestamp' => date('Y-m-d H:i:s'),
            'count_categorie' => 0,
            'count_piatti_totali' => 0
        ];
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2) Leggiamo le sottocategorie per le categorie trovate
    $catIds = array_keys($menu);
    $inCat = implode(',', array_map('intval', $catIds));

    $sqlSottocategorie = "
        SELECT
            s.id,
            s.categoria_id,
            s.nome,
            s.ordine,
            s.visibile
        FROM sottocategorie s
        WHERE s.categoria_id IN ($inCat)
        ORDER BY s.categoria_id, s.ordine, s.nome
    ";

    $resSub = $conn->query($sqlSottocategorie);
    if (!$resSub) {
        throw new Exception("Query sottocategorie failed: " . $conn->error);
    }

    // Mappa delle sottocategorie per categoria
    // $menu[catId]['sottocategorie'][subId] = [ ... ]
    while ($row = $resSub->fetch_assoc()) {
        $catId = (int)$row['categoria_id'];
        $subId = (int)$row['id'];

        if (!isset($menu[$catId])) {
            continue; // categoria non visibile o inconsistente
        }

        if (!isset($menu[$catId]['sottocategorie'])) {
            $menu[$catId]['sottocategorie'] = [];
        }

        $menu[$catId]['sottocategorie'][$subId] = [
            'id' => $subId,
            'nome' => $row['nome'],
            'ordine' => (int)$row['ordine'],
            'visibile' => (int)$row['visibile'],
            'piatti' => []
        ];
    }

    // 3) Leggiamo tutti i piatti disponibili per le categorie
    //    ORDINATI per categoria, sottocategoria, ordine piatto, nome
    $sqlPiatti = "
        SELECT
            p.id,
            p.categoria_id,
            p.sottocategoria_id,
            p.nome,
            p.descrizione,
            p.prezzo,
            p.ordine,
            p.immagine,
            p.tempo_preparazione,
            p.punti_fedelta,
            p.allergeni,
            p.disponibile
        FROM piatti p
        WHERE p.categoria_id IN ($inCat)
          AND p.disponibile = TRUE
        ORDER BY
            p.categoria_id,
            p.sottocategoria_id,
            p.ordine,
            p.nome
    ";

    $resPiatti = $conn->query($sqlPiatti);
    if (!$resPiatti) {
        throw new Exception("Query piatti failed: " . $conn->error);
    }

    $countPiatti = 0;

    while ($row = $resPiatti->fetch_assoc()) {
        $catId = (int)$row['categoria_id'];
        if (!isset($menu[$catId])) {
            continue; // categoria non visibile o inconsistente
        }

        $piatto = [
            'id' => (int)$row['id'],
            'nome' => $row['nome'],
            'descrizione' => $row['descrizione'],
            'prezzo' => (float)$row['prezzo'],
            'prezzo_formattato' => '€' . number_format($row['prezzo'], 2, ',', '.'),
            'tempo_preparazione' => $row['tempo_preparazione'] !== null ? (int)$row['tempo_preparazione'] : null,
            'punti_fedelta' => $row['punti_fedelta'] !== null ? (int)$row['punti_fedelta'] : 0,
            'immagine' => $row['immagine'],
            'allergeni' => $row['allergeni'],
            'ordine' => $row['ordine'] !== null ? (int)$row['ordine'] : 0
        ];

        $countPiatti++;

        $subId = $row['sottocategoria_id'] !== null ? (int)$row['sottocategoria_id'] : null;

        if ($subId !== null && isset($menu[$catId]['sottocategorie'][$subId])) {
            $menu[$catId]['sottocategorie'][$subId]['piatti'][] = $piatto;
        } else {
            $menu[$catId]['piatti'][] = $piatto;
        }
    }

    // 4) Convertiamo sottocategorie da array associativo (per id) a array numerico
    foreach ($menu as $catId => $catData) {
        if (!empty($catData['sottocategorie']) && is_array($catData['sottocategorie'])) {
            $menu[$catId]['sottocategorie'] = array_values($catData['sottocategorie']);
        }
    }

    // 5) Converti array associativo di categorie in array numerico
    $menu_array = array_values($menu);

    $response = [
        'success' => true,
        'data' => $menu_array,
        'timestamp' => date('Y-m-d H:i:s'),
        'count_categorie' => count($menu_array),
        'count_piatti_totali' => $countPiatti
    ];

} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

$conn->close();
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);