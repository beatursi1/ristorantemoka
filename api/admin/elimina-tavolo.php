<?php
// api/admin/elimina-tavolo.php
header('Content-Type: application/json');
require_once('../../includes/auth.php');
require_once('../../config/config.php');

// Solo l'admin può procedere
checkAccess('admin');

$input = json_decode(file_get_contents('php://input'), true);
$tavolo_id = (int)($input['id'] ?? 0);

$conn = getDbConnection();
// PROTEZIONE DOPPIA: Elimina solo se il tavolo è LIBERO
$stmt = $conn->prepare("DELETE FROM tavoli WHERE id = ? AND stato = 'libero'");
$stmt->bind_param("i", $tavolo_id);

if ($stmt->execute() && $conn->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Impossibile eliminare un tavolo occupato o ID errato.']);
}
?>