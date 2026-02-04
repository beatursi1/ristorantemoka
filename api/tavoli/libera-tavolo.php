<?php
// libera-tavolo.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once('../../config/config.php');

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['tavolo_id'])) {
    echo json_encode(['success' => false, 'error' => 'tavolo_id mancante']);
    exit;
}

$tavolo_id = (int)$input['tavolo_id'];
$motivo = $input['motivo'] ?? 'Non specificato';
$cameriere_id = $input['cameriere_id'] ?? 0;

$conn = getDbConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Inizia transazione
$conn->begin_transaction();

try {
    // 1. Conta clienti prima della cancellazione
    $result = $conn->query("SELECT COUNT(*) as count FROM sessioni_clienti WHERE tavolo_id = $tavolo_id");
    $row = $result->fetch_assoc();
    $clienti_cancellati = $row['count'];
    
    // 2. Cancella i clienti
    $conn->query("DELETE FROM sessioni_clienti WHERE tavolo_id = $tavolo_id");
    
    // 3. Aggiorna stato tavolo
    $conn->query("UPDATE tavoli SET stato = 'libero' WHERE id = $tavolo_id");
    
    // 4. Registra nel log
    $stmt = $conn->prepare("INSERT INTO log_attivita (tipo, descrizione, tavolo_id, dettagli) VALUES ('libera_tavolo', 'Tavolo liberato dal cameriere', ?, ?)");
    $dettagli = json_encode([
        'motivo' => $motivo,
        'cameriere_id' => $cameriere_id,
        'clienti_cancellati' => $clienti_cancellati,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    $stmt->bind_param("is", $tavolo_id, $dettagli);
    $stmt->execute();
    $stmt->close();
    
    // Commit
    $conn->commit();
    
    $response = [
        'success' => true,
        'message' => 'Tavolo liberato',
        'tavolo_id' => $tavolo_id,
        'clienti_cancellati' => $clienti_cancellati,
        'motivo' => $motivo
    ];
    
} catch (Exception $e) {
    // Rollback in caso di errore
    $conn->rollback();
    $response = ['success' => false, 'error' => 'Errore: ' . $e->getMessage()];
}

$conn->close();

echo json_encode($response);
?>