<?php

declare(strict_types=1);

namespace Masked;

use Masked\Detection\SensitiveDataMatch;
use Masked\Detection\SensitiveDataMatchNormalizer;

final readonly class RangeRedactor
{
    public function __construct(
        private Redactor $redactor = new Redactor(),
        private SensitiveDataMatchNormalizer $matchNormalizer =
        new SensitiveDataMatchNormalizer(),
    ) {
    }

    /**
     * Redacts the supplied byte ranges while preserving all other content.
     *
     * All ranges are validated against the original input before any
     * replacement takes place. Overlapping ranges are normalized and the
     * resulting ranges are applied from right to left so changes in byte
     * length cannot invalidate offsets that still need to be processed.
     *
     * @param list<SensitiveDataMatch> $matches
     *
     * @throws \InvalidArgumentException when a match exceeds the input bounds
     */
    public function redact(string $value, array $matches): string
    {
        if ([] === $matches) {
            return $value;
        }

        $this->validateMatches(
            valueByteLength: strlen($value),
            matches: $matches,
        );

        $matches = $this->matchNormalizer->normalize($matches);

        for ($index = count($matches) - 1; $index >= 0; --$index) {
            $match = $matches[$index];

            $sensitiveValue = substr(
                $value,
                $match->byteOffset,
                $match->byteLength,
            );

            $value = substr_replace(
                $value,
                $this->redactor->redact($sensitiveValue),
                $match->byteOffset,
                $match->byteLength,
            );
        }

        return $value;
    }

    /**
     * @param list<SensitiveDataMatch> $matches
     */
    private function validateMatches(
        int $valueByteLength,
        array $matches,
    ): void {
        foreach ($matches as $match) {
            if ($match->endByteOffsetExclusive() > $valueByteLength) {
                throw new \InvalidArgumentException('Sensitive data match exceeds the bounds of the input value.');
            }
        }
    }
}
