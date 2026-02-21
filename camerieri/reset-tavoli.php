<?php
// reset-tavoli.php
require_once('../config/config.php');
$conn = getDbConnection();

// Reset tutti i tavoli a "libero"
$conn->query("UPDATE tavoli SET stato = 'libero' WHERE id IN (1, 2, 3, 4, 5)");

echo "Tutti i tavoli sono stati resettati a 'libero'";
?>