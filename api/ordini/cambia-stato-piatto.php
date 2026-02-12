<?php
// api/ordini/cambia-stato-piatto.php
header('Content-Type: application/json; charset=utf-8');
require_once('../../config/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$riga_id = isset($data['riga_id']) ? (int)$data['riga_id'] : 0;
$nuovo_stato = isset($data['nuovo_stato']) ? (int)$data['nuovo_stato'] : 0;

if ($riga_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID piatto non valido']);
    exit;
}

$conn = getDbConnection();
$stmt = $conn->prepare("UPDATE ordini_righe SET stato = ? WHERE id = ?");
$stmt->bind_param('ii', $nuovo_stato, $riga_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$stmt->close();
$conn->close();