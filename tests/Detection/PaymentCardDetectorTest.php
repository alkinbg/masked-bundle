<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Detection;

use Masked\Bundle\Detection\PaymentCardDetectionContext;
use Masked\Bundle\Detection\PaymentCardDetector;
use Masked\Bundle\Detection\SensitiveDataMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentCardDetector::class)]
final class PaymentCardDetectorTest extends TestCase
{
    #[DataProvider('validPanProvider')]
    public function testDetectsValidPan(string $value): void
    {
        $matches = new PaymentCardDetector()->detect($value);

        self::assertSame(
            [$value],
            self::extractMatchedValues($value, $matches),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validPanProvider(): iterable
    {
        yield '13 digits' => [
            '4222222222222',
        ];

        yield 'American Express 15 digits' => [
            '378282246310005',
        ];

        yield 'Visa 16 digits' => [
            '4111111111111111',
        ];

        yield 'Mastercard 16 digits' => [
            '5555555555554444',
        ];

        yield '19 digits' => [
            '4000000000000000006',
        ];

        yield 'spaces' => [
            '4111 1111 1111 1111',
        ];

        yield 'hyphens' => [
            '4111-1111-1111-1111',
        ];

        yield 'multiple spaces' => [
            '4111  1111  1111  1111',
        ];

        yield 'horizontal tabs' => [
            "4111\t1111\t1111\t1111",
        ];

        yield 'non-breaking spaces' => [
            "4111\u{00A0}1111\u{00A0}1111\u{00A0}1111",
        ];
    }

    #[DataProvider('invalidPanProvider')]
    public function testDoesNotDetectInvalidPan(string $value): void
    {
        self::assertSame(
            [],
            new PaymentCardDetector()->detect($value),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPanProvider(): iterable
    {
        yield 'empty value' => [
            '',
        ];

        yield 'less than 13 digits' => [
            '411111111111',
        ];

        yield 'invalid Luhn checksum' => [
            '4111111111111112',
        ];

        yield 'arbitrary numeric reference' => [
            '1234567890123456',
        ];

        yield 'repeated zeros' => [
            '0000000000000000',
        ];

        yield 'repeated ones' => [
            '1111111111111111',
        ];

        yield 'contiguous value longer than 19 digits' => [
            '41111111111111111234',
        ];

        yield 'unsupported slash separator' => [
            '4111/1111/1111/1111',
        ];
    }

    public function testDetectsPanInsideText(): void
    {
        $value = 'Payment failed for card 4111 1111 1111 1111.';

        $matches = new PaymentCardDetector()->detect($value);

        self::assertSame(
            ['4111 1111 1111 1111'],
            self::extractMatchedValues($value, $matches),
        );
    }

    public function testUsesByteOffsetsWithMultibyteText(): void
    {
        $prefix = 'Плащане с карта: ';
        $pan = '4111 1111 1111 1111';
        $value = $prefix.$pan;

        $matches = new PaymentCardDetector()->detect($value);

        self::assertCount(1, $matches);
        self::assertSame(strlen($prefix), $matches[0]->byteOffset);
        self::assertSame(strlen($pan), $matches[0]->byteLength);
        self::assertSame(
            $pan,
            substr(
                $value,
                $matches[0]->byteOffset,
                $matches[0]->byteLength,
            ),
        );
    }

    public function testDetectsMultipleCardsInInputOrder(): void
    {
        $value = 'Primary: 4111111111111111, backup: 5555555555554444';

        $matches = new PaymentCardDetector()->detect($value);

        self::assertSame(
            [
                '4111111111111111',
                '5555555555554444',
            ],
            self::extractMatchedValues($value, $matches),
        );
    }

    public function testDetectsAdjacentCardsSeparately(): void
    {
        $value = '4111111111111111 5555555555554444';

        $matches = new PaymentCardDetector()->detect($value);

        self::assertSame(
            [
                '4111111111111111',
                '5555555555554444',
            ],
            self::extractMatchedValues($value, $matches),
        );
    }

    public function testIgnoresNumericContextBeforePan(): void
    {
        $value = 'Order 123 4111 1111 1111 1111';

        $matches = new PaymentCardDetector()->detect($value);

        self::assertSame(
            ['4111 1111 1111 1111'],
            self::extractMatchedValues($value, $matches),
        );
    }

    public function testDoesNotIncludeExpiryDateAfterPan(): void
    {
        $value = 'Card: 4111111111111111 12/30';

        $matches = new PaymentCardDetector()->detect($value);

        self::assertSame(
            ['4111111111111111'],
            self::extractMatchedValues($value, $matches),
        );
    }

    public function testDoesNotIncludeTrailingDigitsWhenCombinedValueFailsLuhn(): void
    {
        $value = 'Card: 4111 1111 1111 1111 123';

        $matches = new PaymentCardDetector()->detect($value);

        self::assertSame(
            ['4111 1111 1111 1111'],
            self::extractMatchedValues($value, $matches),
        );
    }

    public function testUsesMaximalOverlappingRangeWhenBothCandidatesAreValid(): void
    {
        /*
         * Both 4111111111111111 and 4111111111111111003 pass Luhn.
         *
         * From free text alone we cannot know whether this represents
         * a 16-digit PAN followed by another numeric value or one
         * 19-digit PAN. Masking the maximal overlapping range is the
         * safer behaviour.
         */
        $value = 'Card: 4111 1111 1111 1111 003';

        $matches = new PaymentCardDetector()->detect($value);

        self::assertSame(
            ['4111 1111 1111 1111 003'],
            self::extractMatchedValues($value, $matches),
        );
    }

    public function testDoesNotDetectPanPrefixInsideContiguousLongNumber(): void
    {
        $value = 'Reference: 41111111111111111234';

        self::assertSame(
            [],
            new PaymentCardDetector()->detect($value),
        );
    }

    public function testDetectsPanWhenInputContainsInvalidUtf8Bytes(): void
    {
        $pan = '4111111111111111';
        $value = "\xFF invalid bytes before card: ".$pan;

        $matches = new PaymentCardDetector()->detect($value);

        self::assertSame(
            [$pan],
            self::extractMatchedValues($value, $matches),
        );
    }

    public function testDoesNotDetectPanPrefixInsideVeryLongContiguousNumber(): void
    {
        $value = str_repeat(
            '1',
            100000,
        );

        self::assertSame(
            [],
            new PaymentCardDetector()->detect($value),
        );
    }

    public function testDetectsPanAcrossVeryLargeSupportedSeparatorRun(): void
    {
        $value =
            '4111'
            .str_repeat(' ', 100000)
            .'1111 1111 1111';

        $matches = new PaymentCardDetector()->detect($value);

        self::assertCount(
            1,
            $matches,
        );

        self::assertSame(
            0,
            $matches[0]->byteOffset,
        );

        self::assertSame(
            strlen($value),
            $matches[0]->byteLength,
        );
    }

    public function testFailsClosedWhenCandidateCheckBudgetIsExceeded(): void
    {
        /*
         * A long sequence of one-digit groups produces many possible 13–19
         * digit suffix candidates without requiring a large retained window.
         *
         * Once the validation budget is exhausted the whole input must be
         * treated as sensitive.
         */
        $value = implode(
            ' ',
            array_fill(
                0,
                2000,
                '1',
            ),
        );

        $matches = new PaymentCardDetector()->detect($value);

        self::assertCount(
            1,
            $matches,
        );

        self::assertSame(
            0,
            $matches[0]->byteOffset,
        );

        self::assertSame(
            strlen($value),
            $matches[0]->byteLength,
        );
    }

    public function testSharesCandidateCheckBudgetAcrossInputs(): void
    {
        $detector = new PaymentCardDetector();
        $context = PaymentCardDetectionContext::create();

        /*
         * For 200 one-digit groups, the scanner performs 1,295 candidate
         * validations while every repeated-digit candidate remains invalid.
         *
         * Seven inputs consume 9,065 validations. The eighth input therefore
         * exceeds the shared 10,000-check budget while each individual input
         * remains safely below the standalone limit.
         */
        $value = self::createCandidateHeavyValue(
            200,
        );

        for ($index = 0; $index < 7; ++$index) {
            self::assertSame(
                [],
                $detector->detectWithinContext(
                    $value,
                    $context,
                ),
            );
        }

        $matches = $detector->detectWithinContext(
            $value,
            $context,
        );

        self::assertCount(
            1,
            $matches,
        );

        self::assertSame(
            0,
            $matches[0]->byteOffset,
        );

        self::assertSame(
            strlen($value),
            $matches[0]->byteLength,
        );

        self::assertTrue(
            $context->isFailClosed(),
        );

        $laterValue = 'later safe value';

        $laterMatches = $detector->detectWithinContext(
            $laterValue,
            $context,
        );

        self::assertCount(
            1,
            $laterMatches,
        );

        self::assertSame(
            0,
            $laterMatches[0]->byteOffset,
        );

        self::assertSame(
            strlen($laterValue),
            $laterMatches[0]->byteLength,
        );
    }

    /**
     * @param list<SensitiveDataMatch> $matches
     *
     * @return list<string>
     */
    private static function extractMatchedValues(
        string $value,
        array $matches,
    ): array {
        $result = [];

        foreach ($matches as $match) {
            $result[] = substr(
                $value,
                $match->byteOffset,
                $match->byteLength,
            );
        }

        return $result;
    }

    private static function createCandidateHeavyValue(
        int $digitGroupCount,
    ): string {
        return implode(
            ' ',
            array_fill(
                0,
                $digitGroupCount,
                '1',
            ),
        );
    }
}
