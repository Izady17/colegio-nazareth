<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$pregunta = trim($_POST['pregunta'] ?? '');

if (empty($pregunta)) {
    echo json_encode(['status' => 'error', 'respuesta' => 'Por favor escribe una consulta válida.']);
    exit();
}

// 1. Tu clave de API que empieza por AQ.
$api_key = "AQ.Ab8RN6J-9wJygTt6Twb4se-ueXNPIxUuA2kxUOrpHBH4-p1JQg"; 

// 2. Endpoint oficial
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent";

$system_instruction = "Eres el 'Asistente Nazareth IA', la inteligencia artificial de la Unidad Educativa 'Jesús de Nazareth'. " .
    "Responde de forma amable, clara y concisa en un máximo de 2 párrafos cortos.";

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $system_instruction . "\n\nPregunta: " . $pregunta]
            ]
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
// Las nuevas claves AQ. se pasan mediante esta cabecera oficial:
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-goog-api-key: ' . trim($api_key)
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);