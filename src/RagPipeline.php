<?php
declare(strict_types=1);
namespace App;

final class RagPipeline
{
    public function __construct(
        private readonly OllamaClient          $ollama,
        private readonly EmbeddingClient       $embed,
        private readonly VectorStore           $store,
        private readonly RagResponseValidator  $validator,
        private readonly string                $schemaPath = __DIR__ . '/../schemas/respuesta_rag.json',
    ) {}

    /**
     * @return array{respuesta: string, citas: int[], confianza_alta: bool, advertencia: ?string}
     */
    public function ask(string $pregunta): array
    {
        $queryVec = $this->embed->embed([$pregunta])[0];

        $hits = $this->store->search($queryVec, 5);

        if (empty($hits)) {
            return [
                'respuesta'      => 'No encuentro esa información en los documentos disponibles.',
                'citas'          => [],
                'confianza_alta' => false,
                'advertencia'    => null,
            ];
        }

        $contexto = '';
        foreach ($hits as $h) {
            $contexto .= "[FRAGMENTO {$h['id']}] {$h['source']}\n{$h['content']}\n\n";
        }

        $sys = 'Respondé SOLO con el CONTEXTO proporcionado. Reglas: '
             . '1) El campo "respuesta" debe citar cada afirmación con [FRAGMENTO #ID]. '
             . '2) El campo "citas" debe listar los IDs de FRAGMENTO realmente usados en la respuesta. '
             . '3) "confianza_alta" es true solo si el contexto respalda la respuesta con claridad, false si es parcial o dudosa. '
             . '4) Si la información no está en el contexto, poné en "respuesta" exactamente "No encuentro esa información.", "citas" vacío y "confianza_alta" false. '
             . '5) "advertencia" es un texto breve si hay dudas o información incompleta, o null si no aplica. '
             . '6) NO inventes. NO uses conocimiento previo al contexto.';

        $messages = [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user',   'content' => "CONTEXTO:\n{$contexto}\nPREGUNTA: {$pregunta}"],
        ];

        $schema = json_decode(file_get_contents($this->schemaPath), true, flags: JSON_THROW_ON_ERROR);

        $raw  = $this->ollama->chat($messages, format: $schema, temperature: 0.0);
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        $errores = $this->validator->validate($data);
        if ($errores !== []) {
            throw new \RuntimeException(
                "La respuesta del LLM no cumple el schema: " . implode('; ', $errores)
            );
        }

        return $data;
    }
}
