<?php
/**
 * api/ordini/get-conto-tavolo.php
 * Calcola il conto completo di un tavolo con tre modalità di split:
 * - totale: un importo unico per tutto il tavolo
 * - per_cliente: ogni cliente paga esattamente ciò che ha ordinato (con quote condivisioni)
 * - equo: totale diviso equamente per numero clienti
 *
 * @version 1.0.0
 */
session_start();
define('ACCESS_ALLOWED', true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['utente_id']) || $_SESSION['utente_ruolo'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit;
}

require_once('../../config/config.php');
$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

$tavoloId = isset($_GET['tavolo_id']) ? (int)$_GET['tavolo_id'] : 0;
if (!$tavoloId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'tavolo_id mancante']);
    exit;
}

try {
    // 1. Verifica tavolo
    $stmt = $conn->prepare("SELECT id, numero FROM tavoli WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $tavolo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$tavolo) throw new Exception("Tavolo non trovato");

    // 2. Sessione attiva
    $stmt = $conn->prepare(
        "SELECT id FROM sessioni_tavolo
         WHERE tavolo_id = ? AND stato = 'attiva'
         ORDER BY aperta_il DESC LIMIT 1"
    );
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $sessione = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$sessione) throw new Exception("Nessuna sessione attiva per questo tavolo");
    $sessioneId = (int)$sessione['id'];

    // 3. Clienti al tavolo
    $stmt = $conn->prepare(
        "SELECT identificativo, nome_cliente
         FROM sessioni_clienti
         WHERE tavolo_id = ? AND identificativo IS NOT NULL AND identificativo != ''
         ORDER BY identificativo ASC"
    );
    $stmt->bind_param('i', $tavoloId);
    $stmt->execute();
    $resClienti = $stmt->get_result();
    $stmt->close();

    $clientiMap = []; // lettera => nome
    while ($c = $resClienti->fetch_assoc()) {
        $lett = $c['identificativo'];
        $clientiMap[$lett] = !empty($c['nome_cliente']) ? $c['nome_cliente'] : 'Cliente ' . $lett;
    }
    $numClienti = count($clientiMap);
    if ($numClienti === 0) throw new Exception("Nessun cliente registrato al tavolo");

    // 4. Tutti gli ordini della sessione
    $stmt = $conn->prepare(
        "SELECT ot.id, ot.cliente_lettera
         FROM ordini_tavolo ot
         WHERE ot.tavolo_id = ? AND ot.sessione_id = ?
         ORDER BY ot.creato_il ASC"
    );
    $stmt->bind_param('ii', $tavoloId, $sessioneId);
    $stmt->execute();
    $ordiniRes = $stmt->get_result();
    $stmt->close();

    $ordiniIds = [];
    $ordiniClienti = []; // ordine_id => cliente_lettera
    while ($o = $ordiniRes->fetch_assoc()) {
        $ordiniIds[] = (int)$o['id'];
        $ordiniClienti[(int)$o['id']] = $o['cliente_lettera'];
    }
    if (empty($ordiniIds)) throw new Exception("Nessun ordine trovato per questo tavolo");

    // 5. Tutte le righe
    $ph = implode(',', array_fill(0, count($ordiniIds), '?'));
    $stmt = $conn->prepare(
        "SELECT r.id, r.ordine_id, r.nome, r.prezzo_unitario, r.quantita,
                r.totale_riga, r.tipo, r.condivisione, r.partecipanti, r.stato
         FROM ordini_righe r
         WHERE r.ordine_id IN ($ph)
         ORDER BY r.id ASC"
    );
    $types = str_repeat('i', count($ordiniIds));
    $stmt->bind_param($types, ...$ordiniIds);
    $stmt->execute();
    $righeRes = $stmt->get_result();
    $stmt->close();

    $righe = [];
    while ($r = $righeRes->fetch_assoc()) {
        $righe[] = $r;
    }

    // 6. Calcolo split per cliente
    // Ogni cliente inizia con 0
    $splitPerCliente = [];
    foreach ($clientiMap as $lett => $nome) {
        $splitPerCliente[$lett] = [
            'lettera'    => $lett,
            'nome'       => $nome,
            'importo'    => 0.0,
            'righe'      => []
        ];
    }

    $totaleGenerale = 0.0;
    $righeDettaglio = []; // per il riepilogo completo

    foreach ($righe as $r) {
        $ordineId    = (int)$r['ordine_id'];
        $ordinante   = $ordiniClienti[$ordineId] ?? null;
        $prezzo      = (float)$r['prezzo_unitario'];
        $qta         = (int)$r['quantita'];
        $totRiga     = (float)$r['totale_riga'];
        $condivisione = $r['condivisione'] ?? 'personale';

        // Decodifica partecipanti
        $pArr = [];
        if (!empty($r['partecipanti'])) {
            $dec = json_decode($r['partecipanti'], true);
            if (is_array($dec)) $pArr = $dec;
        }
        $letterePartecipanti = [];
        foreach ($pArr as $p) {
            if (is_array($p) && isset($p['lettera'])) {
                $letterePartecipanti[] = $p['lettera'];
            } elseif (is_string($p) && strlen($p) === 1) {
                $letterePartecipanti[] = $p;
            }
        }

        // Costruisce nome pulito (rimuove eventuale testo legacy "— condivisa con...")
        $nomePulito = preg_replace('/\s*[—\-]\s*condivisa con.*/ui', '', $r['nome']);
        $nomePulito = trim($nomePulito);

        $totaleGenerale += $totRiga;

        // Determina chi paga e quanto
        $rigaDettaglio = [
            'id'           => (int)$r['id'],
            'nome'         => $nomePulito,
            'tipo'         => $r['tipo'],
            'condivisione' => $condivisione,
            'qta'          => $qta,
            'prezzo_unit'  => $prezzo,
            'totale'       => $totRiga,
            'stato'        => (int)$r['stato'],
            'quote'        => [] // lettera => importo
        ];

        if ($condivisione === 'personale') {
            // Paga solo l'ordinante
            if ($ordinante && isset($splitPerCliente[$ordinante])) {
                $splitPerCliente[$ordinante]['importo'] += $totRiga;
                $splitPerCliente[$ordinante]['righe'][] = [
                    'nome'   => $nomePulito,
                    'tipo'   => $r['tipo'],
                    'qta'    => $qta,
                    'prezzo' => $prezzo,
                    'totale' => $totRiga,
                    'nota'   => ''
                ];
                $rigaDettaglio['quote'][$ordinante] = $totRiga;
            }

        } elseif ($condivisione === 'tavolo') {
            // Diviso per tutti i clienti del tavolo
            if ($numClienti > 0) {
                $quota = round($totRiga / $numClienti, 2);
                // Corregge arrotondamento sull'ultimo
                $sommaQuote = $quota * ($numClienti - 1);
                $quotaUltimo = round($totRiga - $sommaQuote, 2);
                $i = 0;
                foreach ($clientiMap as $lett => $nome) {
                    $q = ($i === $numClienti - 1) ? $quotaUltimo : $quota;
                    $splitPerCliente[$lett]['importo'] += $q;
                    $splitPerCliente[$lett]['righe'][] = [
                        'nome'   => $nomePulito,
                        'tipo'   => $r['tipo'],
                        'qta'    => $qta,
                        'prezzo' => $prezzo,
                        'totale' => $q,
                        'nota'   => 'quota 1/' . $numClienti . ' del tavolo'
                    ];
                    $rigaDettaglio['quote'][$lett] = $q;
                    $i++;
                }
            }

        } elseif ($condivisione === 'parziale') {
            // Diviso tra i partecipanti esplicitati
            $partecipantiValidi = array_filter($letterePartecipanti, fn($l) => isset($clientiMap[$l]));
            $numPart = count($partecipantiValidi);
            if ($numPart > 0) {
                $quota = round($totRiga / $numPart, 2);
                $sommaQuote = $quota * ($numPart - 1);
                $quotaUltimo = round($totRiga - $sommaQuote, 2);
                $i = 0;
                foreach ($partecipantiValidi as $lett) {
                    $q = ($i === $numPart - 1) ? $quotaUltimo : $quota;
                    $splitPerCliente[$lett]['importo'] += $q;
                    $nomiAltri = array_map(
                        fn($l) => ($clientiMap[$l] ?? 'Cliente ' . $l) . ' (' . $l . ')',
                        array_filter($partecipantiValidi, fn($l2) => $l2 !== $lett)
                    );
                    $splitPerCliente[$lett]['righe'][] = [
                        'nome'   => $nomePulito,
                        'tipo'   => $r['tipo'],
                        'qta'    => $qta,
                        'prezzo' => $prezzo,
                        'totale' => $q,
                        'nota'   => 'condiviso con ' . implode(', ', $nomiAltri)
                    ];
                    $rigaDettaglio['quote'][$lett] = $q;
                    $i++;
                }
            } elseif ($ordinante && isset($splitPerCliente[$ordinante])) {
                // Nessun partecipante valido: paga l'ordinante
                $splitPerCliente[$ordinante]['importo'] += $totRiga;
                $splitPerCliente[$ordinante]['righe'][] = [
                    'nome'   => $nomePulito,
                    'tipo'   => $r['tipo'],
                    'qta'    => $qta,
                    'prezzo' => $prezzo,
                    'totale' => $totRiga,
                    'nota'   => ''
                ];
                $rigaDettaglio['quote'][$ordinante] = $totRiga;
            }
        }

        $righeDettaglio[] = $rigaDettaglio;
    }

    // 7. Split equo
    $splitEquo = [];
    $quotaEqua = round($totaleGenerale / $numClienti, 2);
    $sommaEqua = $quotaEqua * ($numClienti - 1);
    $quotaEquaUltimo = round($totaleGenerale - $sommaEqua, 2);
    $i = 0;
    foreach ($clientiMap as $lett => $nome) {
        $splitEquo[$lett] = [
            'lettera' => $lett,
            'nome'    => $nome,
            'importo' => ($i === $numClienti - 1) ? $quotaEquaUltimo : $quotaEqua
        ];
        $i++;
    }

    // 8. Arrotonda importi per cliente
    foreach ($splitPerCliente as $lett => &$dati) {
        $dati['importo'] = round($dati['importo'], 2);
    }

    echo json_encode([
        'success'         => true,
        'tavolo'          => ['id' => $tavoloId, 'numero' => $tavolo['numero']],
        'clienti'         => $clientiMap,
        'num_clienti'     => $numClienti,
        'totale_generale' => round($totaleGenerale, 2),
        'split_per_cliente' => array_values($splitPerCliente),
        'split_equo'        => array_values($splitEquo),
        'righe_dettaglio'   => $righeDettaglio
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();