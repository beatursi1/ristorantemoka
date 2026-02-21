<?php
/**
 * api/admin/manutenzione.php
 * Gestisce chiusura giornaliera, pulizia archivio e reset totale.
 * Solo admin autenticati.
 */
session_start();
define('ACCESS_ALLOWED', true);
header('Content-Type: application/json; charset=utf-8');

require_once('../../config/config.php');

// Protezione: solo admin
if (!isset($_SESSION['utente_id']) || $_SESSION['utente_ruolo'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'errore' => 'Accesso negato']);
    exit;
}

$adminId = (int)$_SESSION['utente_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || empty($input['operazione'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errore' => 'Parametro operazione mancante']);
    exit;
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errore' => 'Errore connessione database']);
    exit;
}

$operazione = $input['operazione'];

try {
    switch ($operazione) {

        // ================================================================
        // CHIUSURA GIORNALIERA
        // ================================================================
        case 'chiusura_giornaliera':
            $conn->begin_transaction();

            $oggi = date('Y-m-d');

            // 1. Verifica che non esista già una chiusura per oggi
            $check = $conn->prepare("SELECT id FROM giornate_chiusura WHERE data_chiusura = ?");
            $check->bind_param('s', $oggi);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $check->close();
                $conn->rollback();
                echo json_encode(['success' => false, 'errore' => 'Chiusura già eseguita per oggi (' . $oggi . ')']);
                exit;
            }
            $check->close();

            // 2. Calcola incasso: somma ordini_tavolo con stato != annullato
            $resIncasso = $conn->query(
                "SELECT COALESCE(SUM(ot.totale), 0) as incasso
                 FROM ordini_tavolo ot
                 WHERE DATE(ot.creato_il) = '$oggi'
                   AND ot.stato != 'annullato'"
            );
            $incasso = (float)$resIncasso->fetch_assoc()['incasso'];

            // 3. Archivia righe ordini
            $conn->query(
                "INSERT INTO ordini_righe_archivio 
                    (ordine_id, piatto_id, nome, prezzo_unitario, quantita, totale_riga, tipo, stato, condivisione, partecipanti, data_archiviazione)
                 SELECT 
                    orr.ordine_id, orr.piatto_id, orr.nome, orr.prezzo_unitario, 
                    orr.quantita, orr.totale_riga, orr.tipo, orr.stato, 
                    orr.condivisione, orr.partecipanti, NOW()
                 FROM ordini_righe orr
                 INNER JOIN ordini_tavolo ot ON ot.id = orr.ordine_id
                 WHERE DATE(ot.creato_il) = '$oggi'"
            );
            $righeArchiviate = $conn->affected_rows;

            // 4. Archivia ordini tavolo
            $conn->query(
                "INSERT INTO ordini_tavolo_archivio 
                    (id, sessione_id, tavolo_id, cliente_lettera, stato, totale, creato_il, data_archiviazione)
                 SELECT id, sessione_id, tavolo_id, cliente_lettera, stato, totale, creato_il, NOW()
                 FROM ordini_tavolo
                 WHERE DATE(creato_il) = '$oggi'"
            );
            $ordiniArchiviati = $conn->affected_rows;

            // 5. Elimina da tabelle attive
            $conn->query(
                "DELETE orr FROM ordini_righe orr
                 INNER JOIN ordini_tavolo ot ON ot.id = orr.ordine_id
                 WHERE DATE(ot.creato_il) = '$oggi'"
            );
            $conn->query("DELETE FROM ordini_tavolo WHERE DATE(creato_il) = '$oggi'");

            // 6. Chiudi sessioni tavolo senza ordini attivi
            $conn->query(
                "UPDATE sessioni_tavolo 
                 SET stato = 'chiusa', chiusa_il = NOW()
                 WHERE stato = 'attiva'
                   AND tavolo_id NOT IN (
                       SELECT DISTINCT tavolo_id FROM ordini_tavolo
                   )"
            );
            $sessioniChiuse = $conn->affected_rows;

            // 7. Libera tavoli senza sessione attiva
            $conn->query(
                "UPDATE tavoli 
                 SET stato = 'libero'
                 WHERE id NOT IN (
                     SELECT DISTINCT tavolo_id FROM sessioni_tavolo WHERE stato = 'attiva'
                 )"
            );
            $tavoliLiberati = $conn->affected_rows;

            // 8. Riepilogo JSON
            $riepilogo = json_encode([
                'data'             => $oggi,
                'incasso'          => $incasso,
                'ordini_archiviati'=> $ordiniArchiviati,
                'righe_archiviate' => $righeArchiviate,
                'sessioni_chiuse'  => $sessioniChiuse,
                'tavoli_liberati'  => $tavoliLiberati,
                'eseguita_da'      => $adminId,
            ]);

            // 9. Registra in giornate_chiusura
            $stmt = $conn->prepare(
                "INSERT INTO giornate_chiusura 
                    (data_chiusura, chiuso_da, ordini_archiviati, righe_archiviate, sessioni_chiuse, tavoli_liberati, totale_incasso, riepilogo_json)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('siiiidss', $oggi, $adminId, $ordiniArchiviati, $righeArchiviate, $sessioniChiuse, $tavoliLiberati, $incasso, $riepilogo);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            echo json_encode([
                'success'  => true,
                'messaggio'=> 'Chiusura giornaliera completata.',
                'dettagli' => [
                    'Data'              => $oggi,
                    'Incasso giornata'  => '€' . number_format($incasso, 2, ',', '.'),
                    'Ordini archiviati' => $ordiniArchiviati,
                    'Righe archiviate'  => $righeArchiviate,
                    'Sessioni chiuse'   => $sessioniChiuse,
                    'Tavoli liberati'   => $tavoliLiberati,
                ]
            ]);
            break;

        // ================================================================
        // PULIZIA ARCHIVIO
        // ================================================================
        case 'pulizia_archivio':
            $dataLimite = $input['data_limite'] ?? null;
            if (!$dataLimite || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataLimite)) {
                echo json_encode(['success' => false, 'errore' => 'Data limite non valida']);
                exit;
            }

            $conn->begin_transaction();

            // Elimina righe archivio più vecchie della data limite
            $stmt = $conn->prepare(
                "DELETE FROM ordini_righe_archivio 
                 WHERE data_archiviazione < ?"
            );
            $stmt->bind_param('s', $dataLimite);
            $stmt->execute();
            $righeEliminate = $stmt->affected_rows;
            $stmt->close();

            // Elimina ordini archivio più vecchi della data limite
            $stmt = $conn->prepare(
                "DELETE FROM ordini_tavolo_archivio 
                 WHERE data_archiviazione < ?"
            );
            $stmt->bind_param('s', $dataLimite);
            $stmt->execute();
            $ordiniEliminati = $stmt->affected_rows;
            $stmt->close();

            // Elimina giornate_chiusura corrispondenti
            $stmt = $conn->prepare(
                "DELETE FROM giornate_chiusura 
                 WHERE data_chiusura < ?"
            );
            $stmt->bind_param('s', $dataLimite);
            $stmt->execute();
            $giornateEliminate = $stmt->affected_rows;
            $stmt->close();

            $conn->commit();

            echo json_encode([
                'success'  => true,
                'messaggio'=> 'Pulizia archivio completata.',
                'dettagli' => [
                    'Data limite'         => $dataLimite,
                    'Ordini eliminati'    => $ordiniEliminati,
                    'Righe eliminate'     => $righeEliminate,
                    'Giornate eliminate'  => $giornateEliminate,
                ]
            ]);
            break;

        // ================================================================
        // RESET TOTALE (solo se non ci sono giornate reali)
        // ================================================================
        case 'reset_totale':
            // Doppia verifica server-side
            $r = $conn->query("SELECT COUNT(*) as tot FROM giornate_chiusura");
            if ((int)$r->fetch_assoc()['tot'] > 0) {
                echo json_encode(['success' => false, 'errore' => 'Reset non disponibile: esistono giornate di chiusura reali.']);
                exit;
            }

            $conn->begin_transaction();

            // Ordine di eliminazione rispettando i vincoli FK
            $conn->query("DELETE FROM ordini_righe");
            $righe = $conn->affected_rows;

            $conn->query("DELETE FROM ordini_tavolo");
            $ordini = $conn->affected_rows;

            $conn->query("DELETE FROM ordini_righe_archivio");
            $conn->query("DELETE FROM ordini_tavolo_archivio");

            $conn->query("DELETE FROM sessioni_clienti");
            $sessClienti = $conn->affected_rows;

            $conn->query("DELETE FROM sessioni_tavolo");
            $sessTavoli = $conn->affected_rows;

            $conn->query("DELETE FROM inizializzazioni_tavoli");
            $conn->query("DELETE FROM log_attivita");

            // Reset stato tavoli
            $conn->query("UPDATE tavoli SET stato = 'libero'");

            // Reset AUTO_INCREMENT per partire puliti
            $conn->query("ALTER TABLE ordini_righe AUTO_INCREMENT = 1");
            $conn->query("ALTER TABLE ordini_tavolo AUTO_INCREMENT = 1");
            $conn->query("ALTER TABLE sessioni_tavolo AUTO_INCREMENT = 1");

            $conn->commit();

            echo json_encode([
                'success'  => true,
                'messaggio'=> 'Reset totale completato. Il sistema è pronto per il servizio reale.',
                'dettagli' => [
                    'Ordini eliminati'          => $ordini,
                    'Righe eliminate'           => $righe,
                    'Sessioni clienti eliminate'=> $sessClienti,
                    'Sessioni tavolo eliminate' => $sessTavoli,
                    'Tavoli'                    => 'Tutti impostati a libero',
                ]
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'errore' => 'Operazione non riconosciuta']);
    }

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'errore' => $e->getMessage()]);
}

$conn->close();