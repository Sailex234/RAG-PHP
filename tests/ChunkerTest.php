<?php
declare(strict_types=1);
namespace App\Tests;

use App\Chunker;
use PHPUnit\Framework\TestCase;

final class ChunkerTest extends TestCase
{
    private Chunker $chunker;

    protected function setUp(): void
    {
        $this->chunker = new Chunker(800, 100);
    }

    public function testTextoVacioRetornaArrayVacio(): void
    {
        $this->assertSame([], $this->chunker->chunk(''));
        $this->assertSame([], $this->chunker->chunk('   '));
    }

    public function testTextoMasCortoQueSize(): void
    {
        $texto  = str_repeat('a', 500);
        $chunks = $this->chunker->chunk($texto);

        $this->assertCount(1, $chunks);
        $this->assertSame($texto, $chunks[0]);
    }

    public function testTextoLargoGeneraMultiplesChunks(): void
    {
        $texto  = str_repeat('x', 2000);
        $chunks = $this->chunker->chunk($texto);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(800, mb_strlen($chunk));
        }
    }

    public function testOverlapExisteEntreChunksConsecutivos(): void
    {
        $texto  = str_repeat('a', 800) . str_repeat('b', 800);
        $chunks = $this->chunker->chunk($texto);

        $this->assertGreaterThanOrEqual(2, count($chunks));

        $finChunk0   = mb_substr($chunks[0], -100);
        $inicioChunk1 = mb_substr($chunks[1], 0, 100);

        $this->assertSame($finChunk0, $inicioChunk1);
    }

    public function testCaracteresUTF8NoSeCortan(): void
    {
        $texto  = str_repeat('áéíóú', 200);
        $chunks = $this->chunker->chunk($texto);

        foreach ($chunks as $chunk) {
            $this->assertTrue(mb_check_encoding($chunk, 'UTF-8'));
        }
    }

    public function testNormalizaEspacios(): void
    {
        $chunks = $this->chunker->chunk("hola   mundo\n\nesto  es   texto");

        $this->assertCount(1, $chunks);
        $this->assertSame('hola mundo esto es texto', $chunks[0]);
    }
}
