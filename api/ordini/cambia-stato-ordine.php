<?php
// cambia-stato-ordine.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once('../../config/config.php');

// Leggi i dati inviati
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['tavolo_id']) || empty($input['session_id']) || empty($input['nuovo_stato'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Dati mancanti: tavolo_id, session_id e nuovo_stato sono obbligatori'
    ]);
    exit;
}

$tavolo_id = (int)$input['tavolo_id'];
$session_id = $input['session_id'];
$nuovo_stato = $input['nuovo_stato'];

// Stati validi
$stati_validi = ['attesa', 'in_preparazione', 'pronto', 'consegnato'];
if (!in_array($nuovo_stato, $stati_validi)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Stato non valido. Usa: ' . implode(', ', $stati_validi)
    ]);
    exit;
}

$conn = getDbConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    // Inizia transazione
    $conn->begin_transaction();
    
    // 1. Aggiorna stato di tutti gli ordini di quella sessione/tavolo
    $stmt = $conn->prepare("UPDATE ordini SET stato = ?, updated_at = CURRENT_TIMESTAMP 
                           WHERE tavolo_id = ? AND session_id = ? 
                           AND stato NOT IN ('consegnato', 'cancellato')");
    $stmt->bind_param("sis", $nuovo_stato, $tavolo_id, $session_id);
    $stmt->execute();
    $righe_aggiornate = $stmt->affected_rows;
    $stmt->close();
    
    // 2. Se stato è "pronto", aggiorna anche i punti (se non già accreditati)
    if ($nuovo_stato === 'pronto') {
        $stmt = $conn->prepare("
            UPDATE ordini o
            JOIN piatti p ON o.piatto_id = p.id
            SET o.punti_accreditati = TRUE
            WHERE o.tavolo_id = ? 
            AND o.session_id = ?
            AND o.punti_accreditati = FALSE
        ");
        $stmt->bind_param("is", $tavolo_id, $session_id);
        $stmt->execute();
        $stmt->close();
        
        // Calcola punti totali da accreditare
        $stmt = $conn->prepare("
            SELECT SUM(p.punti_fedelta * o.quantita) as punti_totali
            FROM ordini o
            JOIN piatti p ON o.piatto_id = p.id
            WHERE o.tavolo_id = ? 
            AND o.session_id = ?
            AND o.punti_accreditati = TRUE
        ");
        $stmt->bind_param("is", $tavolo_id, $session_id);
        $stmt->execute();
        $stmt->bind_result($punti_totali);
        $stmt->fetch();
        $stmt->close();
        
        // Aggiorna punti nella sessione cliente
        if ($punti_totali > 0) {
            $stmt = $conn->prepare("
                UPDATE sessioni_clienti 
                SET punti_accumulati = punti_accumulati + ? 
                WHERE id = ?
            ");
            $stmt->bind_param("is", $punti_totali, $session_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // 3. Registra nel log
    $stmt = $conn->prepare("INSERT INTO log_attivita (tipo, descrizione, session_id, tavolo_id, dettagli) 
                           VALUES ('cambio_stato', 'Cambio stato ordine', ?, ?, ?)");
    $dettagli = json_encode([
        'nuovo_stato' => $nuovo_stato,
        'righe_aggiornate' => $righe_aggiornate,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    $stmt->bind_param("sis", $session_id, $tavolo_id, $dettagli);
    $stmt->execute();
    $stmt->close();
    
    // Commit transazione
    $conn->commit();
    
    // Risposta di successo
    $response = [
        'success' => true,
        'message' => 'Stato ordine aggiornato con successo',
        'righe_aggiornate' => $righe_aggiornate,
        'nuovo_stato' => $nuovo_stato,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Se sono stati accreditati punti, aggiungi all'response
    if (isset($punti_totali) && $punti_totali > 0) {
        $response['punti_accreditati'] = $punti_totali;
    }
    
} catch (Exception $e) {
    // Rollback in caso di errore
    $conn->rollback();
    
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Errore nell\'aggiornamento: ' . $e->getMessage()
    ];
}

$conn->close();
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>