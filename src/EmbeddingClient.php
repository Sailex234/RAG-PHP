<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;

final class EmbeddingClient
{
    public function __construct(
        private readonly Client $http,
        private readonly string $host = 'http://localhost:11434',
        private readonly string $model = 'nomic-embed-text' // 768 dims
    ) {}

    /**
     * Genera embeddings en batch. Devuelve float[][]
     * Endpoint: POST /api/embed (NO /api/embeddings — ese es legacy)
     */
    public function embed(array $texts): array
    {
        $resp = $this->http->post($this->host . '/api/embed', [
            'json' => [
                'model' => $this->model,
                'input' => $texts, // string o array de strings (batch)
            ],
        ]);

        $data = json_decode((string) $resp->getBody(), true, flags: JSON_THROW_ON_ERROR);

        // Ollama /api/embed devuelve {"embeddings": [[...]]} (plural)
        return $data['embeddings']; // float[][], ya L2-normalizados
    }
}
