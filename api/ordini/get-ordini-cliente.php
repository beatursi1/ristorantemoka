<?php
/**
 * get-ordini-cliente.php - VERSIONE CORRETTA
 * Mostra SOLO gli ordini della sessione tavolo ATTIVA corrente,
 * non quelli di sessioni precedenti.
 * 
 * @version 2.0.0
 */

ini_set('display_errors', 0); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('ACCESS_ALLOWED', true);
require_once('../../config/config.php');

$response = [
    'success' => false,
    'ordini'  => [],
    'error'   => null
];

$conn = getDbConnection();
if (!$conn) {
    $response['error'] = 'Errore di connessione al database';
    echo json_encode($response);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$sessioneClienteId = $input['sessione_cliente_id'] ?? $_GET['sessione_cliente_id'] ?? '';

if (empty($sessioneClienteId)) {
    echo json_encode(['success' => false, 'error' => 'sessione_cliente_id mancante']);
    exit;
}

try {
    // 1. Recupero dati Cliente
    $stmtCli = $conn->prepare("SELECT tavolo_id, identificativo FROM sessioni_clienti WHERE id = ? LIMIT 1");
    $stmtCli->bind_param('s', $sessioneClienteId);
    $stmtCli->execute();
    $resCli = $stmtCli->get_result();
    
    if ($rowCli = $resCli->fetch_assoc()) {
        $tavoloId = (int)$rowCli['tavolo_id'];
        $clienteLettera = $rowCli['identificativo'];
    } else {
        throw new Exception('Cliente non trovato nel sistema');
    }
    $stmtCli->close();

    // 2. RECUPERO SESSIONE TAVOLO ATTIVA CORRENTE
    // Cerchiamo SOLO la sessione attiva del tavolo, non quelle vecchie
    $sqlAttiva = "SELECT id FROM sessioni_tavolo 
                  WHERE tavolo_id = ? AND stato = 'attiva' 
                  ORDER BY id DESC LIMIT 1";
    $stmtA = $conn->prepare($sqlAttiva);
    $stmtA->bind_param('i', $tavoloId);
    $stmtA->execute();
    $rowAttiva = $stmtA->get_result()->fetch_assoc();
    $stmtA->close();
    
    if ($rowAttiva) {
        $sessioneId = (int)$rowAttiva['id'];
    } else {
        // Nessuna sessione attiva: restituiamo lista vuota
        echo json_encode(['success' => true, 'ordini' => []]);
        exit;
    }

    // 3. Recupero ORDINI del tavolo per la sessione ATTIVA corrente
    $sqlO = "SELECT id, cliente_lettera, stato, totale, creato_il 
             FROM ordini_tavolo 
             WHERE tavolo_id = ? AND sessione_id = ? 
             ORDER BY creato_il DESC";
    $stmtO = $conn->prepare($sqlO);
    $stmtO->bind_param('ii', $tavoloId, $sessioneId);
    $stmtO->execute();
    $ordiniRaw = $stmtO->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtO->close();

    if (empty($ordiniRaw)) {
        echo json_encode(['success' => true, 'ordini' => []]);
        exit;
    }

    // 4. Recupero RIGHE per tutti gli ordini trovati
    $ids = array_column($ordiniRaw, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sqlR = "SELECT * FROM ordini_righe WHERE ordine_id IN ($placeholders)";
    $stmtR = $conn->prepare($sqlR);
    $stmtR->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmtR->execute();
    $righeRaw = $stmtR->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtR->close();

    // 5a. Carica mappa lettera → nome_cliente per tutti i clienti del tavolo
    //     Serve per costruire nome_display dinamicamente in base a chi legge.
    $mappaClienti = []; // es: ['A' => 'Moka', 'B' => 'Anselmo']
    $stmtClienti = $conn->prepare(
        "SELECT identificativo, nome_cliente 
         FROM sessioni_clienti 
         WHERE tavolo_id = ? 
           AND identificativo IS NOT NULL 
           AND identificativo != ''"
    );
    $stmtClienti->bind_param('i', $tavoloId);
    $stmtClienti->execute();
    $resClienti = $stmtClienti->get_result();
    while ($rowC = $resClienti->fetch_assoc()) {
        $lett = $rowC['identificativo'];
        $mappaClienti[$lett] = !empty($rowC['nome_cliente']) 
            ? $rowC['nome_cliente'] 
            : 'Cliente ' . $lett;
    }
    $stmtClienti->close();

    // 5b. Organizzazione righe per ordine con nome_display calcolato
    $righePerOrdine = [];
    foreach ($righeRaw as $r) {
        $oid = (int)$r['ordine_id'];

        // Decodifica partecipanti JSON
        $pArr = [];
        if (!empty($r['partecipanti'])) {
            $dec = json_decode($r['partecipanti'], true);
            $pArr = is_array($dec) ? $dec : [];
        }

        // Estrai le lettere dei partecipanti dall'array JSON
        // Il JSON può contenere oggetti {lettera, nome} oppure stringhe semplici
        $letterePartecipanti = [];
        foreach ($pArr as $p) {
            if (is_array($p) && isset($p['lettera'])) {
                $letterePartecipanti[] = $p['lettera'];
            } elseif (is_string($p) && strlen($p) === 1) {
                $letterePartecipanti[] = $p;
            }
        }

        // Costruisce nome_display dal punto di vista di $clienteLettera (chi sta guardando)
        $nomeBase = $r['nome'];
        // Rimuove eventuale testo sporco legacy "— condivisa con..." salvato in passato
        $nomeBase = preg_replace('/\s*[—-]\s*condivisa con.*/ui', '', $nomeBase);
        $nomeBase = preg_replace('/\s*\(per tutto il tavolo\)\s*/ui', '', $nomeBase);
        $nomeBase = trim($nomeBase);

        $nomeDisplay = $nomeBase; // default: nome pulito

        if ($r['condivisione'] === 'tavolo') {
            $nomeDisplay = $nomeBase . ' (per tutto il tavolo)';

        } elseif ($r['condivisione'] === 'parziale' && !empty($letterePartecipanti)) {
            // Gli "altri" sono tutti i partecipanti tranne chi sta leggendo
            $altri = array_filter($letterePartecipanti, function($l) use ($clienteLettera) {
                return $l !== $clienteLettera;
            });

            if (!empty($altri)) {
                $nomiAltri = array_map(function($l) use ($mappaClienti) {
                    $nome = $mappaClienti[$l] ?? ('Cliente ' . $l);
                    return $nome . ' (' . $l . ')';
                }, $altri);
                $nomeDisplay = $nomeBase . ' — condivisa con ' . implode(', ', $nomiAltri);
            }
            // Se $altri è vuoto (solo il cliente corrente), mostra solo il nome base
        }

        $righePerOrdine[$oid][] = [
            'id'              => (int)$r['id'],
            'nome'            => $nomeBase,      // nome pulito, senza testo descrittivo
            'nome_display'    => $nomeDisplay,   // testo per l'utente, calcolato lato server
            'quantita'        => (int)$r['quantita'],
            'prezzo_unitario' => (float)$r['prezzo_unitario'],
            'totale_riga'     => (float)$r['totale_riga'],
            'stato'           => (int)$r['stato'],
            'tipo'            => $r['tipo'] ?? 'piatto',
            'condivisione'    => $r['condivisione'],
            'partecipanti'    => $pArr
        ];
    }

    // 6. Filtro: mostra solo ordini del cliente corrente
    $finalOrdini = [];
    foreach ($ordiniRaw as $ord) {
        $oid = (int)$ord['id'];
        $righe = $righePerOrdine[$oid] ?? [];
        
        // L'ordine è del cliente se la lettera coincide
        $isMio = ($ord['cliente_lettera'] === $clienteLettera);
        
        // Oppure se il cliente è partecipante in una riga condivisa
        $coinvolto = false;
        foreach ($righe as $r) {
            if (in_array($clienteLettera, $r['partecipanti']) || 
                in_array($sessioneClienteId, $r['partecipanti'])) {
                $coinvolto = true;
                break;
            }
        }

        if ($isMio || $coinvolto) {
            $finalOrdini[] = array_merge($ord, ['righe' => $righe]);
        }
    }

    $response['success'] = true;
    $response['ordini'] = $finalOrdini;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
$conn->close();