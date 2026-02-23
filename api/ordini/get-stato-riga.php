<?php
/**
 * get-stato-riga.php
 * Ritorna lo stato attuale di una singola riga ordine.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

define('ACCESS_ALLOWED', true);
require_once('../../config/config.php');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$rigaId = isset($input['riga_id']) ? (int)$input['riga_id'] : 0;

if (!$rigaId) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'riga_id mancante']); exit; }

$conn = getDbConnection();
if (!$conn) { http_response_code(500); echo json_encode(['success' => false, 'error' => 'DB error']); exit; }

$stmt = $conn->prepare("SELECT stato FROM ordini_righe WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $rigaId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) { echo json_encode(['success' => false, 'error' => 'Riga non trovata']); exit; }

echo json_encode(['success' => true, 'stato' => (int)$row['stato']]);