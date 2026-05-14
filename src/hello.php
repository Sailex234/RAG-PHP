<?php
require __DIR__ . '/../vendor/autoload.php';

use App\OllamaClient;
use GuzzleHttp\Client as HttpClient;

// Sin API key — apunta a localhost:11434
$ollama = new OllamaClient(new HttpClient());

$respuesta = $ollama->chat([
    ['role' => 'user', 'content' => 'Decí "Hola Elías, todo funciona en local!"'],
]);

echo $respuesta . PHP_EOL;