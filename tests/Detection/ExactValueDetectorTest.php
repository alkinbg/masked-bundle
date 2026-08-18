<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Detection;

use Masked\Bundle\Detection\ExactValueDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExactValueDetector::class)]
final class ExactValueDetectorTest extends TestCase
{
    public function testReturnsNoMatchesForEmptyInput(): void
    {
        self::assertSame(
            [],
            new ExactValueDetector()->detect(
                '',
                ['secret'],
            ),
        );
    }

    public function testReturnsNoMatchesWithoutSensitiveValues(): void
    {
        self::assertSame(
            [],
            new ExactValueDetector()->detect(
                'Nothing sensitive here.',
                [],
            ),
        );
    }

    public function testDetectsExactSensitiveValue(): void
    {
        $matches = new ExactValueDetector()->detect(
            'Token: super-secret',
            ['super-secret'],
        );

        self::assertCount(1, $matches);
        self::assertSame(7, $matches[0]->byteOffset);
        self::assertSame(12, $matches[0]->byteLength);
    }

    public function testDetectsMultipleOccurrences(): void
    {
        $matches = new ExactValueDetector()->detect(
            'secret and secret',
            ['secret'],
        );

        self::assertCount(2, $matches);

        self::assertSame(0, $matches[0]->byteOffset);
        self::assertSame(6, $matches[0]->byteLength);

        self::assertSame(11, $matches[1]->byteOffset);
        self::assertSame(6, $matches[1]->byteLength);
    }

    public function testDetectsMultipleSensitiveValues(): void
    {
        $matches = new ExactValueDetector()->detect(
            'username=alkin&password=super-secret',
            [
                'alkin',
                'super-secret',
            ],
        );

        self::assertCount(2, $matches);

        self::assertSame(9, $matches[0]->byteOffset);
        self::assertSame(5, $matches[0]->byteLength);

        self::assertSame(24, $matches[1]->byteOffset);
        self::assertSame(12, $matches[1]->byteLength);
    }

    public function testMergesOverlappingOccurrences(): void
    {
        $matches = new ExactValueDetector()->detect(
            'aaaa',
            ['aaa'],
        );

        self::assertCount(1, $matches);
        self::assertSame(0, $matches[0]->byteOffset);
        self::assertSame(4, $matches[0]->byteLength);
    }

    public function testMatchingIsCaseSensitive(): void
    {
        self::assertSame(
            [],
            new ExactValueDetector()->detect(
                'Secret',
                ['secret'],
            ),
        );
    }

    public function testSupportsArbitraryByteSequencesAroundSensitiveValue(): void
    {
        $matches = new ExactValueDetector()->detect(
            "\xFFsecret\xFE",
            ['secret'],
        );

        self::assertCount(1, $matches);
        self::assertSame(1, $matches[0]->byteOffset);
        self::assertSame(6, $matches[0]->byteLength);
    }

    public function testRejectsEmptySensitiveValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Sensitive values must not contain an empty string.',
        );

        new ExactValueDetector()->detect(
            'Something',
            [''],
        );
    }

    public function testRejectsEmptySensitiveValueWhenInputIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Sensitive values must not contain an empty string.',
        );

        new ExactValueDetector()->detect(
            '',
            [''],
        );
    }

    public function testMergesManyOverlappingOccurrencesWhileScanning(): void
    {
        $value = str_repeat('a', 5000);
        $sensitiveValue = str_repeat('a', 32);

        $matches = new ExactValueDetector()->detect(
            $value,
            [$sensitiveValue],
        );

        self::assertCount(1, $matches);

        self::assertSame(
            0,
            $matches[0]->byteOffset,
        );

        self::assertSame(
            strlen($value),
            $matches[0]->byteLength,
        );
    }

    public function testDuplicateSensitiveValuesDoNotChangeDetectedRanges(): void
    {
        $detector = new ExactValueDetector();

        $expected = $detector->detect(
            'Token: super-secret',
            ['super-secret'],
        );

        $actual = $detector->detect(
            'Token: super-secret',
            [
                'super-secret',
                'super-secret',
                'super-secret',
            ],
        );

        self::assertEquals(
            $expected,
            $actual,
        );
    }

    public function testFailsClosedWhenSearchBudgetIsExceeded(): void
    {
        /*
         * One-character matches are adjacent rather than overlapping, so this
         * deliberately creates more search operations than the detector budget.
         *
         * Once the budget is exhausted the complete input must be protected.
         */
        $value = str_repeat('x', 10001);

        $matches = new ExactValueDetector()->detect(
            $value,
            ['x'],
        );

        self::assertCount(1, $matches);

        self::assertSame(
            0,
            $matches[0]->byteOffset,
        );

        self::assertSame(
            strlen($value),
            $matches[0]->byteLength,
        );
    }

    public function testFailsClosedWhenTooManySensitiveValuesAreSupplied(): void
    {
        $sensitiveValues = [];

        for ($index = 0; $index < 1001; ++$index) {
            $sensitiveValues[] = 'secret-'.$index;
        }

        $value = 'Nothing sensitive here.';

        $matches = new ExactValueDetector()->detect(
            $value,
            $sensitiveValues,
        );

        self::assertCount(1, $matches);
        self::assertSame(0, $matches[0]->byteOffset);
        self::assertSame(
            strlen($value),
            $matches[0]->byteLength,
        );
    }

    public function testFailsClosedWhenUniqueSensitiveValueBytesExceedBudget(): void
    {
        $value = 'Nothing sensitive here.';

        $matches = new ExactValueDetector()->detect(
            $value,
            [
                str_repeat('a', 600 * 1024),
                str_repeat('b', 600 * 1024),
            ],
        );

        self::assertCount(1, $matches);
        self::assertSame(0, $matches[0]->byteOffset);
        self::assertSame(
            strlen($value),
            $matches[0]->byteLength,
        );
    }

    public function testDuplicateSensitiveValuesDoNotConsumeUniqueByteBudget(): void
    {
        $sensitiveValue = str_repeat('s', 2048);

        $matches = new ExactValueDetector()->detect(
            'prefix-'.$sensitiveValue,
            array_fill(
                0,
                1000,
                $sensitiveValue,
            ),
        );

        self::assertCount(1, $matches);
        self::assertSame(7, $matches[0]->byteOffset);
        self::assertSame(
            strlen($sensitiveValue),
            $matches[0]->byteLength,
        );
    }

    public function testFailsClosedWhenAggregateSearchWindowBudgetIsExceeded(): void
    {
        $value = str_repeat('x', 1024 * 1024);

        $sensitiveValues = [];

        for ($index = 0; $index < 65; ++$index) {
            $sensitiveValues[] =
                'non-matching-secret-'.$index;
        }

        $matches = new ExactValueDetector()->detect(
            $value,
            $sensitiveValues,
        );

        self::assertCount(1, $matches);
        self::assertSame(0, $matches[0]->byteOffset);
        self::assertSame(
            strlen($value),
            $matches[0]->byteLength,
        );
    }
}
