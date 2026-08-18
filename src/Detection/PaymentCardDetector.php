<?php

declare(strict_types=1);

namespace Masked\Bundle\Detection;

final readonly class PaymentCardDetector
{
    /*
     * Conservative bounds used for automatic free-text detection.
     *
     * These values define what this detector scans automatically; they are
     * intentionally not a complete definition of every PAN length permitted
     * by payment-card standards.
     */
    private const int MIN_DETECTABLE_PAN_LENGTH = 13;

    private const int MAX_DETECTABLE_PAN_LENGTH = 19;

    /**
     * Bounds candidate validation work for one input.
     *
     * When the limit is exhausted the detector fails closed by marking the
     * complete input as sensitive.
     */
    private const int MAX_CANDIDATE_CHECKS = 10000;

    public function __construct(
        private SensitiveDataMatchNormalizer $matchNormalizer =
        new SensitiveDataMatchNormalizer(),
    ) {
    }

    /**
     * Scans payment-card candidates incrementally.
     *
     * The scanner never materializes every numeric sequence or every digit
     * group in the input. At most the digit groups required to represent a
     * 19-digit candidate are retained at any one time.
     *
     * @return list<SensitiveDataMatch>
     */
    public function detect(
        #[\SensitiveParameter]
        string $value,
    ): array {
        if ('' === $value) {
            return [];
        }

        $valueByteLength = strlen($value);
        $byteOffset = 0;
        $candidateChecks = 0;
        $matches = [];

        while ($byteOffset < $valueByteLength) {
            if (!$this->isAsciiDigit($value[$byteOffset])) {
                ++$byteOffset;

                continue;
            }

            $sequenceResult = $this->detectInSequence(
                $value,
                $byteOffset,
                $candidateChecks,
            );

            if (null === $sequenceResult) {
                return $this->failClosedMatch(
                    $valueByteLength,
                );
            }

            foreach ($sequenceResult['matches'] as $match) {
                $matches[] = $match;
            }

            $byteOffset = $sequenceResult['nextByteOffset'];
        }

        return $this->matchNormalizer->normalize($matches);
    }

    /**
     * Scans one maximal sequence of digit groups separated only by supported
     * formatting characters.
     *
     * @return array{
     *     matches: list<SensitiveDataMatch>,
     *     nextByteOffset: int
     * }|null
     */
    private function detectInSequence(
        #[\SensitiveParameter]
        string $value,
        int $sequenceByteOffset,
        int &$candidateChecks,
    ): ?array {
        $valueByteLength = strlen($value);
        $byteOffset = $sequenceByteOffset;
        $matches = [];

        /**
         * Only groups which may participate in a candidate of at most
         * MAX_DETECTABLE_PAN_LENGTH digits are retained.
         *
         * @var list<array{
         *     byteOffset: int,
         *     digitCount: int,
         *     digits: string
         * }> $windowGroups
         */
        $windowGroups = [];

        $windowDigitCount = 0;

        while (true) {
            $groupByteOffset = $byteOffset;

            while (
                $byteOffset < $valueByteLength
                && $this->isAsciiDigit($value[$byteOffset])
            ) {
                ++$byteOffset;
            }

            $groupDigitCount =
                $byteOffset - $groupByteOffset;

            /*
             * A contiguous digit group longer than the maximum PAN length
             * cannot contain a candidate starting inside that group.
             *
             * Clearing the window also ensures that a long numeric reference
             * cannot donate trailing digits to a following candidate.
             */
            if (
                $groupDigitCount
                > self::MAX_DETECTABLE_PAN_LENGTH
            ) {
                $windowGroups = [];
                $windowDigitCount = 0;
            } else {
                $groupDigits = substr(
                    $value,
                    $groupByteOffset,
                    $groupDigitCount,
                );

                $windowGroups[] = [
                    'byteOffset' => $groupByteOffset,
                    'digitCount' => $groupDigitCount,
                    'digits' => $groupDigits,
                ];

                $windowDigitCount += $groupDigitCount;

                /*
                 * Candidate starts are only valid at digit-group boundaries.
                 * Remove complete leading groups until the retained suffix can
                 * contain at most a 19-digit PAN.
                 */
                while (
                    $windowDigitCount
                    > self::MAX_DETECTABLE_PAN_LENGTH
                ) {
                    $firstGroup = $windowGroups[0] ?? null;

                    if (null === $firstGroup) {
                        throw new \LogicException('Payment-card digit window must not be empty.');
                    }

                    $windowDigitCount -=
                        $firstGroup['digitCount'];

                    array_shift($windowGroups);
                }

                /*
                 * Examine suffixes ending at the current group.
                 *
                 * The window contains at most 19 digits, therefore this loop
                 * has a small fixed upper bound independent of input size.
                 */
                $pan = '';

                for (
                    $groupIndex = count($windowGroups) - 1;
                    $groupIndex >= 0;
                    --$groupIndex
                ) {
                    $group = $windowGroups[$groupIndex];

                    $pan = $group['digits'].$pan;
                    $panDigitCount = strlen($pan);

                    if (
                        $panDigitCount
                        < self::MIN_DETECTABLE_PAN_LENGTH
                    ) {
                        continue;
                    }

                    if (
                        $candidateChecks
                        >= self::MAX_CANDIDATE_CHECKS
                    ) {
                        return null;
                    }

                    ++$candidateChecks;

                    if (!$this->isValidPan($pan)) {
                        continue;
                    }

                    $matches[] = new SensitiveDataMatch(
                        byteOffset: $group['byteOffset'],
                        byteLength: $byteOffset
                            - $group['byteOffset'],
                    );
                }
            }

            /*
             * Consume a run of supported separators. If it is followed by a
             * digit, the next digit group belongs to the same sequence.
             */
            while ($byteOffset < $valueByteLength) {
                $separatorByteLength =
                    $this->supportedSeparatorByteLengthAt(
                        $value,
                        $byteOffset,
                    );

                if (0 === $separatorByteLength) {
                    break;
                }

                $byteOffset += $separatorByteLength;
            }

            if (
                $byteOffset >= $valueByteLength
                || !$this->isAsciiDigit(
                    $value[$byteOffset],
                )
            ) {
                return [
                    'matches' => $matches,
                    'nextByteOffset' => $byteOffset,
                ];
            }
        }
    }

    private function supportedSeparatorByteLengthAt(
        #[\SensitiveParameter]
        string $value,
        int $byteOffset,
    ): int {
        $byte = $value[$byteOffset];

        if (
            ' ' === $byte
            || "\t" === $byte
            || '-' === $byte
        ) {
            return 1;
        }

        if (
            "\xC2" === $byte
            && isset($value[$byteOffset + 1])
            && "\xA0" === $value[$byteOffset + 1]
        ) {
            return 2;
        }

        return 0;
    }

    private function isAsciiDigit(string $byte): bool
    {
        $ordinal = ord($byte);

        return $ordinal >= 48 && $ordinal <= 57;
    }

    private function isValidPan(
        #[\SensitiveParameter]
        string $pan,
    ): bool {
        $length = strlen($pan);

        if (
            $length < self::MIN_DETECTABLE_PAN_LENGTH
            || $length > self::MAX_DETECTABLE_PAN_LENGTH
        ) {
            return false;
        }

        if (strspn($pan, '0123456789') !== $length) {
            return false;
        }

        if ($this->consistsOfSingleRepeatedDigit($pan)) {
            return false;
        }

        return $this->passesLuhn($pan);
    }

    private function consistsOfSingleRepeatedDigit(
        #[\SensitiveParameter]
        string $pan,
    ): bool {
        $firstDigit = $pan[0];
        $length = strlen($pan);

        for ($index = 1; $index < $length; ++$index) {
            if ($pan[$index] !== $firstDigit) {
                return false;
            }
        }

        return true;
    }

    private function passesLuhn(
        #[\SensitiveParameter]
        string $pan,
    ): bool {
        $sum = 0;
        $length = strlen($pan);
        $parity = $length % 2;

        for ($index = 0; $index < $length; ++$index) {
            $digit = (int) $pan[$index];

            if ($index % 2 === $parity) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return 0 === $sum % 10;
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
