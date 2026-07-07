<?php
declare(strict_types=1);
namespace App;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

final class RagResponseValidator
{
    private readonly Validator $validator;
    private readonly object $schema;

    public function __construct(string $schemaPath)
    {
        $this->validator = new Validator();
        $this->schema = json_decode(
            file_get_contents($schemaPath),
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Valida un array asociativo (ya decodificado de JSON) contra el schema.
     *
     * @return string[] lista de errores; vacía si es válido
     */
    public function validate(array $data): array
    {
        $asObject = json_decode(json_encode($data, flags: JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

        $error = $this->validator->validate($asObject, $this->schema)->error();

        if ($error === null) {
            return [];
        }

        return array_values((new ErrorFormatter())->format($error, false));
    }
}
