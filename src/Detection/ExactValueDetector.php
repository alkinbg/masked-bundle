<?php

declare(strict_types=1);

namespace Masked\Bundle\Detection;

/**
 * @internal
 */
final readonly class ExactValueDetector
{
    private const int MAX_SEARCH_OPERATIONS = 10000;

    private const int MAX_SENSITIVE_VALUE_COUNT = 1000;

    private const int MAX_TOTAL_SENSITIVE_VALUE_BYTES =
        1024 * 1024;

    private const int MAX_SEARCH_WINDOW_BYTES =
        64 * 1024 * 1024;

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
     * Explicit-value count, unique-value bytes, substring search count and
     * aggregate search-window bytes are bounded. Exhausting any safety limit
     * fails closed by marking the complete input as sensitive.
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
        $valueByteLength = strlen($value);

        if (
            count($sensitiveValues)
            > self::MAX_SENSITIVE_VALUE_COUNT
        ) {
            return $this->failClosedMatch(
                $valueByteLength,
            );
        }

        /*
         * Preserve the programmer-error contract before applying resource
         * limits: an empty explicit sensitive value is always invalid.
         */
        foreach ($sensitiveValues as $sensitiveValue) {
            if ('' === $sensitiveValue) {
                throw new \InvalidArgumentException('Sensitive values must not contain an empty string.');
            }
        }

        if ('' === $value) {
            return [];
        }

        $valueByteLength = strlen($value);

        if (
            count($sensitiveValues)
            > self::MAX_SENSITIVE_VALUE_COUNT
        ) {
            return $this->failClosedMatch(
                $valueByteLength,
            );
        }

        $uniqueSensitiveValues = [];
        $seenSensitiveValues = [];
        $totalSensitiveValueBytes = 0;

        foreach ($sensitiveValues as $sensitiveValue) {
            $sensitiveValueByteLength =
                strlen($sensitiveValue);

            if (
                $sensitiveValueByteLength
                > self::MAX_TOTAL_SENSITIVE_VALUE_BYTES
            ) {
                return $this->failClosedMatch(
                    $valueByteLength,
                );
            }

            /*
             * Prefix the key so PHP never interprets a numeric-looking
             * sensitive value as an integer array key.
             */
            $seenKey = "\0".$sensitiveValue;

            if (isset($seenSensitiveValues[$seenKey])) {
                continue;
            }

            if (
                $totalSensitiveValueBytes
                > self::MAX_TOTAL_SENSITIVE_VALUE_BYTES
                - $sensitiveValueByteLength
            ) {
                return $this->failClosedMatch(
                    $valueByteLength,
                );
            }

            $seenSensitiveValues[$seenKey] = true;
            $uniqueSensitiveValues[] = $sensitiveValue;
            $totalSensitiveValueBytes +=
                $sensitiveValueByteLength;
        }

        $matches = [];
        $searchOperations = 0;
        $remainingSearchWindowBytes =
            self::MAX_SEARCH_WINDOW_BYTES;

        foreach ($uniqueSensitiveValues as $sensitiveValue) {
            $sensitiveValueByteLength =
                strlen($sensitiveValue);

            if (
                $sensitiveValueByteLength
                > $valueByteLength
            ) {
                continue;
            }

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

                /*
                 * strpos() may inspect the complete remaining input window.
                 * Charge that worst-case window before performing the search
                 * so many non-matching explicit values cannot multiply work
                 * without bound.
                 */
                $searchWindowBytes =
                    $valueByteLength - $searchByteOffset;

                if (
                    $searchWindowBytes
                    > $remainingSearchWindowBytes
                ) {
                    return $this->failClosedMatch(
                        $valueByteLength,
                    );
                }

                $remainingSearchWindowBytes -=
                    $searchWindowBytes;

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
                    $matchByteOffset
                    + $sensitiveValueByteLength;

                if (null === $pendingMatchByteOffset) {
                    $pendingMatchByteOffset =
                        $matchByteOffset;

                    $pendingMatchEndByteOffsetExclusive =
                        $matchEndByteOffsetExclusive;
                } elseif (
                    $matchByteOffset
                    < $pendingMatchEndByteOffsetExclusive
                ) {
                    $pendingMatchEndByteOffsetExclusive =
                        max(
                            $pendingMatchEndByteOffsetExclusive,
                            $matchEndByteOffsetExclusive,
                        );
                } else {
                    $matches[] = new SensitiveDataMatch(
                        byteOffset: $pendingMatchByteOffset,
                        byteLength: $pendingMatchEndByteOffsetExclusive
                        - $pendingMatchByteOffset,
                    );

                    $pendingMatchByteOffset =
                        $matchByteOffset;

                    $pendingMatchEndByteOffsetExclusive =
                        $matchEndByteOffsetExclusive;
                }

                /*
                 * Advance by one byte so overlapping occurrences remain
                 * detectable.
                 */
                $searchByteOffset =
                    $matchByteOffset + 1;
            }

            if (null !== $pendingMatchByteOffset) {
                $matches[] = new SensitiveDataMatch(
                    byteOffset: $pendingMatchByteOffset,
                    byteLength: $pendingMatchEndByteOffsetExclusive
                    - $pendingMatchByteOffset,
                );
            }
        }

        return $this->matchNormalizer->normalize(
            $matches,
        );
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
