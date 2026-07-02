<?php

require __DIR__ . '/../vendor/autoload.php';

use App\{OllamaClient, EmbeddingClient, VectorStore};
use Dotenv\Dotenv;
use GuzzleHttp\Client as HttpClient;

Dotenv::createImmutable(__DIR__ . '/../')->load();

$http = new HttpClient();

// 1. Test de chat con Ollama
$ollama = new OllamaClient($http);
$respuesta = $ollama->chat([['role' => 'user', 'content' => 'Hola Elias!']]);
echo 'Chat OK: ' . substr($respuesta, 0, 60) . '...' . PHP_EOL;

// 2. Test de embedding (768 dims)
$embedder = new EmbeddingClient($http);
$chunk = 'The 2026 student enrollment period opens on March 3rd and closes on April 30th.';
$vectors = $embedder->embed([$chunk]);
echo 'Dimensiones: ' . count($vectors[0]) . PHP_EOL;

// 3. Guardar en pgvector
$pdo = new PDO('pgsql:host=localhost;port=5436;dbname=rag_db', 'rag_user', 'rag_pass');
$store = new VectorStore($pdo);
$id = $store->insert('test-en', 0, $chunk, $vectors[0]);
echo "Chunk insertado ID: {$id}" . PHP_EOL;

// 4. Buscar por similitud
$queryVec = $embedder->embed(['What is the exact date enrollment opens?'])[0];
$results = $store->search($queryVec, 3);
echo 'Similitud top-1: ' . $results[0]['similitud'] . PHP_EOL;
