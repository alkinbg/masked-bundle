<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Detection;

use Masked\Bundle\Detection\ExactValueDetectionContext;
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

    public function testKeepsTouchingOccurrencesSeparate(): void
    {
        $matches = new ExactValueDetector()->detect(
            'secretsecret',
            ['secret'],
        );

        self::assertCount(2, $matches);

        self::assertSame(0, $matches[0]->byteOffset);
        self::assertSame(6, $matches[0]->byteLength);

        self::assertSame(6, $matches[1]->byteOffset);
        self::assertSame(6, $matches[1]->byteLength);
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
        $this->expectException(
            \InvalidArgumentException::class,
        );
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
        $this->expectException(
            \InvalidArgumentException::class,
        );
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

    public function testFailsClosedWhenSearchOperationBudgetIsExceeded(): void
    {
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
        $value = 'Nothing sensitive here.';

        $matches = new ExactValueDetector()->detect(
            $value,
            array_fill(
                0,
                1001,
                'secret',
            ),
        );

        self::assertCount(1, $matches);
        self::assertSame(0, $matches[0]->byteOffset);

        self::assertSame(
            strlen($value),
            $matches[0]->byteLength,
        );
    }

    public function testFailsClosedWhenTotalSensitiveValueBytesExceedBudget(): void
    {
        $value = 'Nothing sensitive here.';

        $matches = new ExactValueDetector()->detect(
            $value,
            [
                str_repeat(
                    'a',
                    1024 * 1024,
                ),
                'b',
            ],
        );

        self::assertCount(1, $matches);
        self::assertSame(0, $matches[0]->byteOffset);

        self::assertSame(
            strlen($value),
            $matches[0]->byteLength,
        );
    }

    public function testDuplicateValuesCanReachCountLimitWithinByteBudget(): void
    {
        $sensitiveValue = str_repeat(
            's',
            1024,
        );

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
        $value = str_repeat(
            'x',
            1024 * 1024,
        );

        $sensitiveValues = self::createNonMatchingSensitiveValues(
            65,
        );

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

    public function testSearchWindowBudgetIsSharedAcrossDetectorCalls(): void
    {
        $detector = new ExactValueDetector();

        $context = ExactValueDetectionContext::create(
            self::createNonMatchingSensitiveValues(
                64,
            ),
        );

        $firstValue = str_repeat(
            'x',
            1024 * 1024,
        );

        self::assertSame(
            [],
            $detector->detectWithinContext(
                $firstValue,
                $context,
            ),
        );

        self::assertFalse(
            $context->isFailClosed(),
        );

        $secondValue = '0123456789';

        $matches = $detector->detectWithinContext(
            $secondValue,
            $context,
        );

        self::assertTrue(
            $context->isFailClosed(),
        );

        self::assertCount(1, $matches);
        self::assertSame(0, $matches[0]->byteOffset);

        self::assertSame(
            strlen($secondValue),
            $matches[0]->byteLength,
        );
    }

    /**
     * @return list<string>
     */
    private static function createNonMatchingSensitiveValues(
        int $count,
    ): array {
        $sensitiveValues = [];

        for ($index = 0; $index < $count; ++$index) {
            $sensitiveValues[] = sprintf(
                'secret-%02d',
                $index,
            );
        }

        return $sensitiveValues;
    }
}
