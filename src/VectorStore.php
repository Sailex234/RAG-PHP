<?php

declare(strict_types=1);

namespace App;

use PDO;

final class VectorStore
{
    public function __construct(private readonly PDO $pdo) {}

    public function insert(string $source, int $chunkIdx, string $content, array $embedding): int
    {
        // Convertir array PHP a formato vector de pgvector: [1.2, 3.4, ...]
        $vec = '[' . implode(',', $embedding) . ']';

        $stmt = $this->pdo->prepare(
            'INSERT INTO documents (source, chunk_idx, content, embedding)
             VALUES (:s, :i, :c, :e::vector) RETURNING id'
        );

        $stmt->execute([':s' => $source, ':i' => $chunkIdx, ':c' => $content, ':e' => $vec]);

        return (int) $stmt->fetchColumn();
    }

    public function search(array $queryEmbedding, int $topK = 5): array
    {
        $vec = '[' . implode(',', $queryEmbedding) . ']';

        // <=> es DISTANCIA coseno (0=idéntico, 2=opuesto)
        // 1 - (<=>) da la SIMILITUD (1=idéntico, 0=sin relación)
        $stmt = $this->pdo->prepare(
            'SELECT id, source, content, 1 - (embedding <=> :q::vector) AS similitud
             FROM documents
             ORDER BY embedding <=> :q::vector  -- ORDER BY usa el índice HNSW
             LIMIT :k'
        );

        $stmt->bindValue(':q', $vec);
        $stmt->bindValue(':k', $topK, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
