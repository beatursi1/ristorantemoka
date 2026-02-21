<?php
/**
 * api/bevande/get-lista-bevande.php
 * Restituisce le bevande disponibili dal menu (categoria_id = 6)
 * @version 1.0.0
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
    $stmt = $conn->prepare(
        "SELECT id, nome, prezzo, descrizione
         FROM piatti
         WHERE categoria_id = 6
           AND disponibile = 1
         ORDER BY ordine ASC, nome ASC"
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $bevande = [];
    while ($row = $result->fetch_assoc()) {
        $bevande[] = [
            'id'          => (int)$row['id'],
            'nome'        => $row['nome'],
            'prezzo'      => (float)$row['prezzo'],
            'descrizione' => $row['descrizione'] ?? ''
        ];
    }

    echo json_encode([
        'success' => true,
        'bevande' => $bevande
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();