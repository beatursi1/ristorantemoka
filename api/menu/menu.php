<?php
// menu.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Permette chiamate da qualsiasi dominio (per testing)
header('Access-Control-Allow-Methods: GET');

require_once('../../config/config.php');

$response = [];
$conn = getDbConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    // Query per ottenere categorie con piatti
    $query = "
        SELECT 
            c.id as categoria_id,
            c.nome as categoria_nome,
            c.ordine as categoria_ordine,
            p.id as piatto_id,
            p.nome as piatto_nome,
            p.descrizione,
            p.prezzo,
            p.immagine,
            p.tempo_preparazione,
            p.punti_fedelta,
            p.disponibile
        FROM categorie_menu c
        LEFT JOIN piatti p ON c.id = p.categoria_id
        WHERE c.visibile = TRUE 
        AND (p.disponibile = TRUE OR p.id IS NULL)
        ORDER BY c.ordine, p.nome
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $menu = [];
    $current_category = null;
    
    while ($row = $result->fetch_assoc()) {
        $cat_id = $row['categoria_id'];
        
        // Se è una nuova categoria
        if (!isset($menu[$cat_id])) {
            $menu[$cat_id] = [
                'id' => $cat_id,
                'nome' => $row['categoria_nome'],
                'ordine' => $row['categoria_ordine'],
                'piatti' => []
            ];
        }
        
        // Aggiungi piatto se esiste (LEFT JOIN potrebbe restituire NULL per piatti)
        if ($row['piatto_id']) {
            $menu[$cat_id]['piatti'][] = [
                'id' => $row['piatto_id'],
                'nome' => $row['piatto_nome'],
                'descrizione' => $row['descrizione'],
                'prezzo' => (float)$row['prezzo'],
                'prezzo_formattato' => '€' . number_format($row['prezzo'], 2, ',', '.'),
                'tempo_preparazione' => (int)$row['tempo_preparazione'],
                'punti_fedelta' => (int)$row['punti_fedelta'],
                'immagine' => $row['immagine']
            ];
        }
    }
    
    // Converti array associativo in array numerico per JSON
    $menu_array = array_values($menu);
    
    $response = [
        'success' => true,
        'data' => $menu_array,
        'timestamp' => date('Y-m-d H:i:s'),
        'count_categorie' => count($menu_array),
        'count_piatti_totali' => array_sum(array_map(function($cat) {
            return count($cat['piatti']);
        }, $menu_array))
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
?>