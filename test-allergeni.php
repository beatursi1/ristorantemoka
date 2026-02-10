<?php
// test-allergeni.php
// Script di test per chiamare via POST l'API update-piatto-allergeni.php

// Modifica questi valori per provare con un altro piatto o altri allergeni
$id_piatto = 1;
$allergeni = 'Glutine, Latte';

// URL dell'API
$url = 'https://www.ilmioqrcode.it/ristorantemoka/api/menu/update-piatto-allergeni.php';

// Prepara i dati da inviare in POST
$data = [
    'id_piatto' => $id_piatto,
    'allergeni' => $allergeni,
];

$options = [
    CURLOPT_URL            => $url,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($data),
    CURLOPT_RETURNTRANSFER => true,
];

$ch = curl_init();
curl_setopt_array($ch, $options);
$response = curl_exec($ch);

if ($response === false) {
    echo 'Errore cURL: ' . curl_error($ch);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo $response;
}

curl_close($ch);