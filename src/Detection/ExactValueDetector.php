<?php

declare(strict_types=1);

namespace Masked\Bundle\Detection;

/**
 * @internal
 */
final readonly class ExactValueDetector
{
    public function __construct(
        private SensitiveDataMatchNormalizer $matchNormalizer =
        new SensitiveDataMatchNormalizer(),
    ) {
    }

    /**
     * Detects exact byte-for-byte occurrences of explicitly supplied
     * sensitive values using a fresh work budget.
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
        #[\SensitiveParameter]
        string $value,
        #[\SensitiveParameter]
        array $sensitiveValues,
    ): array {
        return $this->detectWithinContext(
            $value,
            ExactValueDetectionContext::create(
                $sensitiveValues,
            ),
        );
    }

    /**
     * Detects explicit values using the work budget shared by the surrounding
     * masking operation.
     *
     * Overlapping occurrences of the same sensitive value are merged while
     * scanning so pathological inputs cannot create one match object per
     * overlapping occurrence.
     *
     * Exhausting any shared substring-search budget fails closed by marking
     * the complete current input as sensitive. The context remains fail-closed
     * for every later value processed by the same operation.
     *
     * @return list<SensitiveDataMatch>
     *
     * @internal
     */
    public function detectWithinContext(
        #[\SensitiveParameter]
        string $value,
        #[\SensitiveParameter]
        ExactValueDetectionContext $context,
    ): array {
        if ('' === $value) {
            return [];
        }

        $valueByteLength = strlen($value);

        if ($context->isFailClosed()) {
            return $this->failClosedMatch(
                $valueByteLength,
            );
        }

        $matches = [];

        /*
         * ExactValueDetectionContext guarantees nondecreasing sensitive-value
         * byte lengths. Once one value is longer than the current input, every
         * remaining value is too, so no additional pattern iteration is
         * necessary.
         */
        foreach (
            $context->sensitiveValues() as $sensitiveValue
        ) {
            $sensitiveValueByteLength =
                strlen($sensitiveValue);

            if (
                $sensitiveValueByteLength
                > $valueByteLength
            ) {
                break;
            }

            $searchByteOffset = 0;

            /*
             * There cannot be a match starting beyond this byte offset.
             */
            $lastSearchByteOffset =
                $valueByteLength
                - $sensitiveValueByteLength;

            $pendingMatchByteOffset = null;
            $pendingMatchEndByteOffsetExclusive = null;

            while (
                $searchByteOffset
                <= $lastSearchByteOffset
            ) {
                /*
                 * Charge the complete possible search work before invoking
                 * strpos(). The context accounts for both remaining haystack
                 * size and needle length.
                 */
                $searchWindowBytes =
                    $valueByteLength
                    - $searchByteOffset;

                if (
                    !$context->consumeSearch(
                        $searchWindowBytes,
                        $sensitiveValueByteLength,
                    )
                ) {
                    return $this->failClosedMatch(
                        $valueByteLength,
                    );
                }

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
