<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use App\{EmbeddingClient, OllamaClient, RagPipeline, VectorStore};
use Dotenv\Dotenv;
use GuzzleHttp\Client as HttpClient;

Dotenv::createImmutable(__DIR__ . '/../')->load();

$pregunta = $argv[1] ?? '¿Cómo me inscribo?';

$http     = new HttpClient();
$ollama   = new OllamaClient($http);
$embedder = new EmbeddingClient($http);
$pdo      = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_NAME']),
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$store    = new VectorStore($pdo);
$pipeline = new RagPipeline($ollama, $embedder, $store);

echo $pipeline->ask($pregunta) . "\n";
