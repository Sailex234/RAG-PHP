<?php
declare(strict_types=1);
namespace App;

final class RagPipeline
{
    public function __construct(
        private readonly OllamaClient    $ollama,
        private readonly EmbeddingClient $embed,
        private readonly VectorStore     $store,
    ) {}

    public function ask(string $pregunta): string
    {
        $queryVec = $this->embed->embed([$pregunta])[0];

        $hits = $this->store->search($queryVec, 5);

        if (empty($hits)) {
            return 'No encuentro esa información en los documentos disponibles.';
        }

        $contexto = '';
        foreach ($hits as $h) {
            $contexto .= "[FRAGMENTO {$h['id']}] {$h['source']}\n{$h['content']}\n\n";
        }

        $sys = 'Respondé SOLO con el CONTEXTO proporcionado. Reglas: '
             . '1) Citá cada afirmación con [FRAGMENTO #ID]. '
             . '2) Si la información no está en el contexto, respondé exactamente: "No encuentro esa información." '
             . '3) NO inventes. NO uses conocimiento previo al contexto.';

        $messages = [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user',   'content' => "CONTEXTO:\n{$contexto}\nPREGUNTA: {$pregunta}"],
        ];

        return $this->ollama->chat($messages);
    }
}
