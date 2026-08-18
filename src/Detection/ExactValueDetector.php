<?php

declare(strict_types=1);

namespace Masked\Bundle\Detection;

/**
 * @internal
 */
final readonly class ExactValueDetector
{
    /**
     * Bounds the number of substring searches performed by one detection
     * operation.
     *
     * Reaching the limit fails closed by marking the complete input as
     * sensitive rather than returning partially scanned data.
     */
    private const int MAX_SEARCH_OPERATIONS = 10000;

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
     * Overlapping occurrences of the same sensitive value are merged while
     * scanning so pathological inputs cannot create one match object per
     * overlapping occurrence.
     *
     * @param list<string> $sensitiveValues
     *
     * @return list<SensitiveDataMatch>
     */
    public function detect(
        #[\SensitiveParameter]
        string $value,
        #[\SensitiveParameter]
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

        /*
         * Duplicate explicit values have identical matching semantics and
         * should not cause the input to be scanned repeatedly.
         *
         * SORT_STRING keeps comparison byte-oriented.
         *
         * @var list<string> $uniqueSensitiveValues
         */
        $uniqueSensitiveValues = array_values(
            array_unique(
                $sensitiveValues,
                SORT_STRING,
            ),
        );

        $matches = [];
        $searchOperations = 0;
        $valueByteLength = strlen($value);

        foreach ($uniqueSensitiveValues as $sensitiveValue) {
            $sensitiveValueByteLength = strlen($sensitiveValue);
            $searchByteOffset = 0;

            $pendingMatchByteOffset = null;
            $pendingMatchEndByteOffsetExclusive = null;

            while (true) {
                if (
                    $searchOperations
                    >= self::MAX_SEARCH_OPERATIONS
                ) {
                    return $this->failClosedMatch(
                        $valueByteLength,
                    );
                }

                ++$searchOperations;

                $matchByteOffset = strpos(
                    $value,
                    $sensitiveValue,
                    $searchByteOffset,
                );

                if (false === $matchByteOffset) {
                    break;
                }

                $matchEndByteOffsetExclusive =
                    $matchByteOffset + $sensitiveValueByteLength;

                if (null === $pendingMatchByteOffset) {
                    $pendingMatchByteOffset = $matchByteOffset;
                    $pendingMatchEndByteOffsetExclusive =
                        $matchEndByteOffsetExclusive;
                } elseif (
                    $matchByteOffset
                    < $pendingMatchEndByteOffsetExclusive
                ) {
                    /*
                     * Merge overlapping occurrences immediately.
                     *
                     * Touching occurrences remain separate, matching the
                     * contract of SensitiveDataMatchNormalizer.
                     */
                    $pendingMatchEndByteOffsetExclusive = max(
                        $pendingMatchEndByteOffsetExclusive,
                        $matchEndByteOffsetExclusive,
                    );
                } else {
                    $matches[] = new SensitiveDataMatch(
                        byteOffset: $pendingMatchByteOffset,
                        byteLength: $pendingMatchEndByteOffsetExclusive
                            - $pendingMatchByteOffset,
                    );

                    $pendingMatchByteOffset = $matchByteOffset;
                    $pendingMatchEndByteOffsetExclusive =
                        $matchEndByteOffsetExclusive;
                }

                /*
                 * Advance by one byte so overlapping occurrences are still
                 * detected.
                 */
                $searchByteOffset = $matchByteOffset + 1;
            }

            if (null !== $pendingMatchByteOffset) {
                $matches[] = new SensitiveDataMatch(
                    byteOffset: $pendingMatchByteOffset,
                    byteLength: $pendingMatchEndByteOffsetExclusive
                        - $pendingMatchByteOffset,
                );
            }
        }

        return $this->matchNormalizer->normalize($matches);
    }

    /**
     * @return list<SensitiveDataMatch>
     */
    private function failClosedMatch(
        int $valueByteLength,
    ): array {
        if (0 === $valueByteLength) {
            return [];
        }

        return [
            new SensitiveDataMatch(
                byteOffset: 0,
                byteLength: $valueByteLength,
            ),
        ];
    }
}
