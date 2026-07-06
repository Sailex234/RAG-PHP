<?php
declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;

final class OllamaClient
{
    public function __construct(
        private readonly Client $http,
        private readonly string $host  = 'http://localhost:11434',
        private readonly string $model = 'llama3.1:8b',
        // sin API key
    ) {}

    /** Chat simple: devuelve el texto de la respuesta */
    public function chat(array $messages, array|string|null $format = null): string
    {
        $payload = [
            'model'    => $this->model,
            'messages' => $messages,
            'stream'   => false,
            'options'  => ['temperature' => 0.1],
        ];

        if ($format !== null) {
            $payload['format'] = $format;
        }

        $resp = $this->http->post($this->host . '/api/chat', ['json' => $payload]);
        $body = json_decode((string) $resp->getBody(), true);

        return $body['message']['content'];
    }
}
