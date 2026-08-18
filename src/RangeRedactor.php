<?php

declare(strict_types=1);

namespace Masked\Bundle;

use Masked\Bundle\Detection\SensitiveDataMatch;
use Masked\Bundle\Detection\SensitiveDataMatchNormalizer;

/**
 * @internal
 */
final readonly class RangeRedactor
{
    /**
     * Bounds the number of match ranges passed to normalization.
     *
     * If more valid ranges are supplied, the complete input is redacted
     * instead of sorting an unbounded match list.
     */
    private const int MAX_NORMALIZABLE_MATCH_COUNT = 10000;

    public function __construct(
        private Redactor $redactor = new Redactor(),
        private SensitiveDataMatchNormalizer $matchNormalizer =
        new SensitiveDataMatchNormalizer(),
    ) {
    }

    /**
     * Redacts the supplied byte ranges while preserving all other content.
     *
     * All ranges are validated against the original input before redaction
     * takes place. Overlapping ranges are normalized, then the result is built
     * from left to right so each preserved or sensitive input chunk is copied
     * at most once.
     *
     * If the match-count safety limit is exceeded, the complete input is
     * redacted rather than attempting to normalize an unbounded match list.
     *
     * @param list<SensitiveDataMatch> $matches
     *
     * @throws \InvalidArgumentException when a match exceeds the input bounds
     */
    public function redact(
        #[\SensitiveParameter]
        string $value,
        array $matches,
    ): string {
        if ([] === $matches) {
            return $value;
        }

        $valueByteLength = strlen($value);

        $this->validateMatches(
            valueByteLength: $valueByteLength,
            matches: $matches,
        );

        if (
            count($matches)
            > self::MAX_NORMALIZABLE_MATCH_COUNT
        ) {
            return $this->redactor->redact($value);
        }

        $matches = $this->matchNormalizer->normalize(
            $matches,
        );

        $chunks = [];
        $cursorByteOffset = 0;

        foreach ($matches as $match) {
            if ($match->byteOffset > $cursorByteOffset) {
                $chunks[] = substr(
                    $value,
                    $cursorByteOffset,
                    $match->byteOffset - $cursorByteOffset,
                );
            }

            $sensitiveValue = substr(
                $value,
                $match->byteOffset,
                $match->byteLength,
            );

            $chunks[] = $this->redactor->redact(
                $sensitiveValue,
            );

            $cursorByteOffset =
                $match->endByteOffsetExclusive();
        }

        if ($cursorByteOffset < $valueByteLength) {
            $chunks[] = substr(
                $value,
                $cursorByteOffset,
            );
        }

        return implode('', $chunks);
    }

    /**
     * @param list<SensitiveDataMatch> $matches
     */
    private function validateMatches(
        int $valueByteLength,
        array $matches,
    ): void {
        foreach ($matches as $match) {
            if (
                $match->endByteOffsetExclusive()
                > $valueByteLength
            ) {
                throw new \InvalidArgumentException('Sensitive data match exceeds the bounds of the input value.');
            }
        }
    }
}
