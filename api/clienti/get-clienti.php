<?php
// get-clienti.php
// API: get-clienti.php
// Versione semplificata per test

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// DEBUG: Mostra errori
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Legge parametri GET
$tavolo = isset($_GET['tavolo']) ? $_GET['tavolo'] : null;
$sessione = isset($_GET['sessione']) ? $_GET['sessione'] : null;

if (!$tavolo || !$sessione) {
    http_response_code(400);
    echo json_encode(["error" => "Parametri mancanti: tavolo e sessione sono obbligatori"]);
    exit;
}

// SIMULAZIONE: Per ora restituiamo dati fissi per test
// In produzione, qui si connetterebbe al database

$clienti_base = [
    ['lettera' => 'A', 'nome' => 'Cliente A', 'attivo' => true],
    ['lettera' => 'B', 'nome' => 'Cliente B', 'attivo' => true],
    ['lettera' => 'C', 'nome' => 'Cliente C', 'attivo' => true],
    ['lettera' => 'D', 'nome' => 'Cliente D', 'attivo' => false],
    ['lettera' => 'E', 'nome' => 'Cliente E', 'attivo' => false],
    ['lettera' => 'F', 'nome' => 'Cliente F', 'attivo' => false]
];

// Risposta
echo json_encode([
    'success' => true,
    'tavolo' => $tavolo,
    'sessione' => $sessione,
    'numero_coperti' => 3, // Simulato
    'clienti' => $clienti_base,
    'max_clienti_iniziali' => 6,
    'puoi_aggiungere' => true,
    'timestamp' => date('Y-m-d H:i:s')
]);

// Per debug: log
file_put_contents('debug_get_clienti.log', 
    date('Y-m-d H:i:s') . " - tavolo=$tavolo, sessione=$sessione\n", 
    FILE_APPEND);
?>