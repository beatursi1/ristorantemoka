<?php
// inizializza-tavolo.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once('../../config/config.php');

// Verifica che sia un cameriere loggato
if (!isset($_SESSION['cameriere_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Accesso non autorizzato']);
    exit;
}

// Leggi i dati inviati
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['tavolo_id']) || empty($input['clienti'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

$tavolo_id = (int)$input['tavolo_id'];
$cameriere_id = $_SESSION['cameriere_id'];
$clienti = $input['clienti'];
$bevande = $input['bevande'] ?? [];

error_log("DEBUG: Ricevuta richiesta per tavolo $tavolo_id da cameriere $cameriere_id");
error_log("DEBUG: Bevande ricevute: " . json_encode($bevande));

$conn = getDbConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    // Inizia transazione
    $conn->begin_transaction();
    
    // 1. Aggiorna stato tavolo a "occupato"
    $stmt = $conn->prepare("UPDATE tavoli SET stato = 'occupato' WHERE id = ?");
    $stmt->bind_param("i", $tavolo_id);
    $stmt->execute();
    $stmt->close();
    
    // 2. Prepara struttura per sessioni configurate
    $sessioni_configurate = [];
    $qr_code_base = "tavolo_" . $tavolo_id . "_sessione_" . time() . "_";
    
    // 3. Crea sessioni per i clienti attivi
    foreach ($clienti as $lettera => $cliente) {
        if ($cliente['attivo']) {
            $session_id = $qr_code_base . strtolower($lettera);
            
            // Inserisci nella tabella sessioni_clienti
            $stmt = $conn->prepare("INSERT INTO sessioni_clienti 
                                   (id, tavolo_id, identificativo, nome_cliente, inizializzato_da, data_inizializzazione) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sissi", $session_id, $tavolo_id, $lettera, $cliente['nome'], $cameriere_id);
            $stmt->execute();
            $stmt->close();
            
            $sessioni_configurate[$lettera] = [
                'session_id' => $session_id,
                'nome' => $cliente['nome'] ?: '',
                'lettera' => $lettera
            ];
        }
    }
    
    // 4. Gestione bevande iniziali
    $bevande_iniziali_data = [];
    
    // 4a. Acqua per il tavolo (se richiesta)
    if (isset($bevande['acqua']) && $bevande['acqua']['quantita'] > 0) {
        $quantita_acqua = (int)$bevande['acqua']['quantita'];
        $prezzo_acqua = 2.50; // Prezzo per 1L
        
        $bevande_iniziali_data['acqua'] = [
            'quantita' => $quantita_acqua,
            'prezzo_totale' => $prezzo_acqua * $quantita_acqua,
            'tipo' => $bevande['acqua']['tipo'] ?? 'naturale'
        ];
    }
    
    // 4b. Altre bevande
    if (!empty($bevande['altre'])) {
        error_log("DEBUG: Bevande altre trovata: " . json_encode($bevande['altre']));
        
        foreach ($bevande['altre'] as $index => $bevanda) {
            error_log("DEBUG: Elaborazione bevanda $index: " . json_encode($bevanda));
            
            // Verifica struttura bevanda
            if (!isset($bevanda['id']) || !isset($bevanda['partecipanti'])) {
                error_log("ERROR: Struttura bevanda non valida");
                continue;
            }
            
            $bevande_iniziali_data['altre'][] = $bevanda;
            
            // Per ogni bevanda, crea ordini per i partecipanti
            if (!empty($bevanda['partecipanti'])) {
                foreach ($bevanda['partecipanti'] as $lettera_partecipante) {
                    if (isset($sessioni_configurate[$lettera_partecipante])) {
                        $session_id_partecipante = $sessioni_configurate[$lettera_partecipante]['session_id'];
                        
                        error_log("DEBUG: Tentativo inserimento ordine per bevanda ID " . $bevanda['id'] . " per cliente $lettera_partecipante");
                        
                        // Inserisci ordine bevanda
                        $stmt = $conn->prepare("INSERT INTO ordini 
                                               (session_id, tavolo_id, piatto_id, quantita, stato, condivisione_tipo) 
                                               VALUES (?, ?, ?, ?, 'attesa', ?)");
                        $condivisione_tipo = $bevanda['condivisione'] ?? 'personale';
                        $stmt->bind_param("siiis", 
                            $session_id_partecipante, 
                            $tavolo_id, 
                            $bevanda['id'],
                            1, // quantita
                            $condivisione_tipo
                        );
                        
                        if (!$stmt->execute()) {
                            error_log("ERROR: Query fallita: " . $stmt->error);
                            throw new Exception("Errore inserimento ordine: " . $stmt->error);
                        }
                        
                        $ordine_id = $conn->insert_id;
                        
                        // Se condivisione parziale, salva lista partecipanti
                        if ($condivisione_tipo === 'parziale' && !empty($bevanda['partecipanti'])) {
                            $partecipanti_json = json_encode($bevanda['partecipanti']);
                            $stmt2 = $conn->prepare("UPDATE ordini SET condivisione_partecipanti = ? WHERE id = ?");
                            $stmt2->bind_param("si", $partecipanti_json, $ordine_id);
                            $stmt2->execute();
                            $stmt2->close();
                        }
                        
                        $stmt->close();
                    } else {
                        error_log("WARNING: Cliente $lettera_partecipante non trovato in sessioni configurate");
                    }
                }
            } else {
                error_log("WARNING: Bevanda senza partecipanti");
            }
        }
    }
    
    // 5. Salva configurazione iniziale
    $sessioni_configurate_json = json_encode($sessioni_configurate);
    $bevande_iniziali_json = json_encode($bevande_iniziali_data);
    
    $stmt = $conn->prepare("INSERT INTO inizializzazioni_tavoli 
                           (tavolo_id, cameriere_id, sessioni_configurate, bevande_iniziali) 
                           VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $tavolo_id, $cameriere_id, $sessioni_configurate_json, $bevande_iniziali_json);
    $stmt->execute();
    $inizializzazione_id = $stmt->insert_id;
    $stmt->close();
    
    // 6. Registra nel log
    $stmt = $conn->prepare("INSERT INTO log_attivita (tipo, descrizione, tavolo_id, dettagli) 
                           VALUES ('inizializzazione', 'Tavolo inizializzato dal cameriere', ?, ?)");
    $dettagli = json_encode([
        'cameriere_id' => $cameriere_id,
        'clienti_attivi' => count($sessioni_configurate),
        'inizializzazione_id' => $inizializzazione_id
    ]);
    $stmt->bind_param("is", $tavolo_id, $dettagli);
    $stmt->execute();
    $stmt->close();
    
    // Commit transazione
    $conn->commit();
    
    // Prepara URL QR code
    $base_url = "https://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
    $qr_code_url = $base_url . "/app/menu.html?tavolo=" . $tavolo_id . "&sessione=" . $inizializzazione_id;
    
    // Risposta di successo
    $response = [
        'success' => true,
        'message' => 'Tavolo inizializzato con successo',
        'tavolo_id' => $tavolo_id,
        'clienti_attivi' => count($sessioni_configurate),
        'sessioni_configurate' => $sessioni_configurate,
        'qr_code_url' => $qr_code_url,
        'inizializzazione_id' => $inizializzazione_id,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
} catch (Exception $e) {
    // Rollback in caso di errore
    if (isset($conn)) {
        $conn->rollback();
    }
    
    error_log("ERROR CRITICO: " . $e->getMessage());
    error_log("TRACE: " . $e->getTraceAsString());
    
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Errore nell\'inizializzazione: ' . $e->getMessage()
    ];
}

if (isset($conn)) {
    $conn->close();
}

error_log("DEBUG: Invio risposta: " . json_encode($response));
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>