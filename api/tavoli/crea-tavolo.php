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
require_once('../../config/config.php');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Controllo semplice: solo utenti loggati (adatta se hai ruoli più specifici)
if (empty($_SESSION['cameriere_id'])) {
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
    // Useremo sempre INSERT senza colonne non presenti (es. created_by)
    $insertSql = "INSERT INTO tavoli (numero, stato, created_at) VALUES (?, 'libero', NOW())";
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        throw new Exception('Errore preparazione INSERT: ' . $conn->error);
    }
    $stmt->bind_param('i', $nextNumero);

    $ok = $stmt->execute();
    if (!$ok) {
        // Se fallisce, ricalcoliamo e riproviamo una volta
        $stmt->close();

        $used = [];
        if ($s2 = $conn->prepare("SELECT numero FROM tavoli ORDER BY numero ASC")) {
            if ($s2->execute()) {
                $r2 = $s2->get_result();
                while ($rw = $r2->fetch_assoc()) {
                    $used[] = (int)$rw['numero'];
                }
            }
            $s2->close();
        }

        $i = 1;
        foreach ($used as $n) {
            if ($n != $i) break;
            $i++;
        }
        $nextNumero = $i ?: rand(1000, 9999);

        // Retry insert
        $stmt = $conn->prepare($insertSql);
        if (!$stmt) {
            throw new Exception('Errore prepare INSERT retry: ' . $conn->error);
        }
        $stmt->bind_param('i', $nextNumero);
        $ok = $stmt->execute();
        if (!$ok) {
            $stmt->close();
        }
    }

    if ($ok) {
        $insertedId = $conn->insert_id;
        $stmt->close();

        // Recupera record per restituirlo al client
        if ($select = $conn->prepare("SELECT id, numero, stato, created_at FROM tavoli WHERE id = ?")) {
            $select->bind_param('i', $insertedId);
            if ($select->execute()) {
                $res = $select->get_result();
                $row = $res->fetch_assoc();
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
            }
        }

        // Fallback minimale se SELECT fallisce
        echo json_encode([
            'success' => true,
            'tavolo' => [
                'id' => (int)$insertedId,
                'numero' => (int)$nextNumero
            ]
        ]);
        exit;
    } else {
        $err = $conn->error;
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Errore creazione tavolo', 'db_error' => $err]);
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