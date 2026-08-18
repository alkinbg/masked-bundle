<?php

declare(strict_types=1);

namespace Masked\Bundle\Detection;

final class SensitiveDataMatchNormalizer
{
    /**
     * Sorts matches by byte offset and merges overlapping sensitive ranges.
     *
     * Adjacent ranges remain separate because they do not share any bytes.
     *
     * @param list<SensitiveDataMatch> $matches
     *
     * @return list<SensitiveDataMatch>
     */
    public function normalize(array $matches): array
    {
        if (count($matches) < 2) {
            return $matches;
        }

        usort(
            $matches,
            static function (
                SensitiveDataMatch $left,
                SensitiveDataMatch $right,
            ): int {
                $offsetComparison =
                    $left->byteOffset <=> $right->byteOffset;

                if (0 !== $offsetComparison) {
                    return $offsetComparison;
                }

                /*
                 * For ranges starting at the same byte, process the longest
                 * one first. This makes containment explicit before merging.
                 */
                return $right->byteLength <=> $left->byteLength;
            },
        );

        $normalized = [];

        foreach ($matches as $match) {
            $lastIndex = count($normalized) - 1;

            if ($lastIndex < 0) {
                $normalized[] = $match;

                continue;
            }

            $previous = $normalized[$lastIndex];

            if (
                $match->byteOffset
                >= $previous->endByteOffsetExclusive()
            ) {
                $normalized[] = $match;

                continue;
            }

            $endByteOffsetExclusive = max(
                $previous->endByteOffsetExclusive(),
                $match->endByteOffsetExclusive(),
            );

            $normalized[$lastIndex] = new SensitiveDataMatch(
                byteOffset: $previous->byteOffset,
                byteLength: $endByteOffsetExclusive - $previous->byteOffset,
            );
        }

        return array_values($normalized);
    }
}
