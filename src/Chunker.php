<?php
declare(strict_types=1);
namespace App;

final class Chunker
{
    public function __construct(
        private readonly int $size    = 800,
        private readonly int $overlap = 100,
    ) {}

    /** @return string[] */
    public function chunk(string $text): array
    {
        $text = preg_replace('/\s+/', ' ', trim($text));

        if (mb_strlen($text) === 0) {
            return [];
        }

        $chunks = [];
        $len    = mb_strlen($text);
        $step   = $this->size - $this->overlap;
        $i      = 0;

        while ($i < $len) {
            $chunks[] = mb_substr($text, $i, $this->size);
            $i += $step;
        }

        return $chunks;
    }
}
