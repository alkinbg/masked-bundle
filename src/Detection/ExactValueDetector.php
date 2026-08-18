<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Detection;

final readonly class ExactValueDetector
{
    public function __construct(
        private SensitiveDataMatchNormalizer $matchNormalizer =
        new SensitiveDataMatchNormalizer(),
    ) {
    }

    /**
     * Detects exact byte-for-byte occurrences of explicitly supplied
     * sensitive values.
     *
     * Matching is intentionally case-sensitive and byte-oriented. Sensitive
     * values are supplied explicitly, so no normalization or interpretation
     * should change their meaning.
     *
     * @param list<string> $sensitiveValues
     *
     * @return list<SensitiveDataMatch>
     */
    public function detect(
        string $value,
        array $sensitiveValues,
    ): array {
        if ([] === $sensitiveValues) {
            return [];
        }

        foreach ($sensitiveValues as $sensitiveValue) {
            if ('' === $sensitiveValue) {
                throw new \InvalidArgumentException('Sensitive values must not contain an empty string.');
            }
        }

        if ('' === $value) {
            return [];
        }

        $matches = [];

        foreach ($sensitiveValues as $sensitiveValue) {
            $sensitiveValueByteLength = strlen($sensitiveValue);
            $searchByteOffset = 0;

            while (true) {
                $matchByteOffset = strpos(
                    $value,
                    $sensitiveValue,
                    $searchByteOffset,
                );

                if (false === $matchByteOffset) {
                    break;
                }

                $matches[] = new SensitiveDataMatch(
                    byteOffset: $matchByteOffset,
                    byteLength: $sensitiveValueByteLength,
                );

                /*
                 * Advance by one byte rather than by the sensitive value
                 * length so overlapping occurrences are detected as well.
                 *
                 * Example:
                 * value  = "aaaa"
                 * secret = "aaa"
                 *
                 * Matches exist at byte offsets 0 and 1. The normalizer
                 * subsequently merges them into one protected range.
                 */
                $searchByteOffset = $matchByteOffset + 1;
            }
        }

        return $this->matchNormalizer->normalize($matches);
    }
}
