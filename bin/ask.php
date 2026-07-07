<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use App\{EmbeddingClient, OllamaClient, RagPipeline, RagResponseValidator, VectorStore};
use Dotenv\Dotenv;
use GuzzleHttp\Client as HttpClient;

Dotenv::createImmutable(__DIR__ . '/../')->load();

$pregunta = $argv[1] ?? '¿Cómo me inscribo?';

$http      = new HttpClient();
$ollama    = new OllamaClient($http);
$embedder  = new EmbeddingClient($http);
$pdo       = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_NAME']),
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$store     = new VectorStore($pdo);
$validator = new RagResponseValidator(__DIR__ . '/../schemas/respuesta_rag.json');
$pipeline  = new RagPipeline($ollama, $embedder, $store, $validator);

$resultado = $pipeline->ask($pregunta);

echo $resultado['respuesta'] . "\n";

if (!empty($resultado['citas'])) {
    echo 'Fuentes: [FRAGMENTO ' . implode('], [FRAGMENTO ', $resultado['citas']) . ']' . "\n";
}

if ($resultado['confianza_alta'] === false) {
    echo "[Confianza baja en esta respuesta]\n";
}

if (!empty($resultado['advertencia'])) {
    echo "Advertencia: {$resultado['advertencia']}\n";
}
