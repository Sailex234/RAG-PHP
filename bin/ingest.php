<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use App\{Chunker, EmbeddingClient, VectorStore};
use Dotenv\Dotenv;
use GuzzleHttp\Client as HttpClient;

Dotenv::createImmutable(__DIR__ . '/../')->load();

$chunker  = new Chunker(800, 100);
$embedder = new EmbeddingClient(new HttpClient());
$pdo      = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_NAME']),
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$store = new VectorStore($pdo);

$txtDir = __DIR__ . '/../samples/txt/';
$files  = glob($txtDir . '*.txt') ?: [];

if (empty($files)) {
    echo "No se encontraron archivos .txt en {$txtDir}\n";
    exit(1);
}

foreach ($files as $file) {
    $source = basename($file, '.txt');
    $text   = file_get_contents($file);

    if ($text === false || trim($text) === '') {
        echo "Saltando {$source}: archivo vacío o ilegible\n";
        continue;
    }

    $chunks = $chunker->chunk($text);
    $total  = count($chunks);
    echo "Procesando {$source}: {$total} chunks...\n";

    foreach (array_chunk($chunks, 20, preserve_keys: true) as $batch) {
        $vectors = $embedder->embed(array_values($batch));
        foreach (array_values($batch) as $localIdx => $chunk) {
            $globalIdx = array_key_first($batch) + $localIdx;
            $store->insert($source, $globalIdx, $chunk, $vectors[$localIdx]);
        }
    }

    echo "✓ {$source} indexado ({$total} chunks)\n";
}

echo "\nIngesta completada.\n";
