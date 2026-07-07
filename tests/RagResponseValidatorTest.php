<?php
declare(strict_types=1);
namespace App\Tests;

use App\RagResponseValidator;
use PHPUnit\Framework\TestCase;

final class RagResponseValidatorTest extends TestCase
{
    private RagResponseValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RagResponseValidator(__DIR__ . '/../schemas/respuesta_rag.json');
    }

    public function testRespuestaValidaNoTieneErrores(): void
    {
        $data = [
            'respuesta'      => 'El período de inscripción se abre el 3 de marzo de 2026 [FRAGMENTO 5].',
            'citas'          => [5],
            'confianza_alta' => true,
            'advertencia'    => null,
        ];

        $this->assertSame([], $this->validator->validate($data));
    }

    public function testRespuestaInvalidaFaltaCamposRequeridos(): void
    {
        $data = [
            'respuesta' => 'Texto sin el resto de los campos.',
        ];

        $errores = $this->validator->validate($data);

        $this->assertNotSame([], $errores);
    }

    public function testRespuestaInvalidaTipoIncorrectoEnCitas(): void
    {
        $data = [
            'respuesta'      => 'Respuesta con citas mal tipadas.',
            'citas'          => ['cinco'],
            'confianza_alta' => true,
            'advertencia'    => null,
        ];

        $errores = $this->validator->validate($data);

        $this->assertNotSame([], $errores);
    }
}
