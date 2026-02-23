<?php
// api/tavoli/crea-tavolo.php
// Crea un nuovo tavolo con numero autogenerato e restituisce JSON.
// Adattare il percorso di config se necessario.

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Path al file di config - modifica se la struttura del tuo progetto è diversa
define('ACCESS_ALLOWED', true);
require_once('../../config/config.php');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Controllo semplice: solo utenti loggati (adatta se hai ruoli più specifici)
if (empty($_SESSION['utente_id']) || !in_array($_SESSION['utente_ruolo'] ?? '', ['admin', 'cameriere'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit;
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Calcola next available numero: trova primo buco nella sequenza 1..N
$used = [];
$query = "SELECT numero FROM tavoli ORDER BY numero ASC";
if ($stmt = $conn->prepare($query)) {
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $used[] = (int)$row['numero'];
        }
    }
    $stmt->close();
} else {
    // In casi rari, proseguiamo con un numero casuale
    $used = [];
}

// Trova il primo numero disponibile (1..)
$nextNumero = 1;
if (!empty($used)) {
    $i = 1;
    foreach ($used as $n) {
        if ($n != $i) break;
        $i++;
    }
    $nextNumero = $i;
}

// Inserimento record
try {
    // Versione Professionale: Usiamo created_at (Assicurati che la colonna esista nel DB!)
    $insertSql = "INSERT INTO tavoli (numero, stato, created_at) VALUES (?, 'libero', NOW())";
    $stmt = $conn->prepare($insertSql);
    
    if (!$stmt) {
        throw new Exception('Errore preparazione Database: ' . $conn->error);
    }

    $stmt->bind_param('i', $nextNumero);
    $ok = $stmt->execute();

    // Se l'inserimento fallisce (es. numero duplicato per millisecondi), riprova con l'ultimo + 1
    if (!$ok) {
        $stmt->close();
        $resMax = $conn->query("SELECT MAX(numero) as max_n FROM tavoli");
        $nextNumero = ($resMax->fetch_assoc()['max_n'] ?? 0) + 1;
        
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param('i', $nextNumero);
        $ok = $stmt->execute();
    }

    if ($ok) {
        $insertedId = $conn->insert_id;
        $stmt->close();

        // Recupera i dati appena inseriti per confermare al frontend
        $select = $conn->prepare("SELECT id, numero, stato, created_at FROM tavoli WHERE id = ?");
        $select->bind_param('i', $insertedId);
        $select->execute();
        $row = $select->get_result()->fetch_assoc();
        $select->close();

        echo json_encode([
            'success' => true,
            'tavolo' => [
                'id' => (int)$row['id'],
                'numero' => (int)$row['numero'],
                'stato' => $row['stato'],
                'created_at' => $row['created_at']
            ]
        ]);
        exit;
    } else {
        throw new Exception('Impossibile inserire il tavolo: ' . $conn->error);
    }

} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
    exit;
}
} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
    exit;
} finally {
    $conn->close();
}
?>