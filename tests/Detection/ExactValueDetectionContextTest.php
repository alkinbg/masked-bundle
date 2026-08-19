<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Detection;

use Masked\Bundle\Detection\ExactValueDetectionContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExactValueDetectionContext::class)]
final class ExactValueDetectionContextTest extends TestCase
{
    private const int MIB = 1024 * 1024;

    private const int GIB = 1024 * 1024 * 1024;

    public function testCreatesEmptyContext(): void
    {
        $context = ExactValueDetectionContext::create([]);

        self::assertFalse($context->isFailClosed());
        self::assertSame([], $context->sensitiveValues());
    }

    public function testAcceptsSensitiveValueCountBelowLimit(): void
    {
        $context = ExactValueDetectionContext::create(
            array_fill(
                0,
                999,
                'x',
            ),
        );

        self::assertFalse($context->isFailClosed());
    }

    public function testAcceptsMaximumSensitiveValueCount(): void
    {
        $context = ExactValueDetectionContext::create(
            array_fill(
                0,
                1000,
                'x',
            ),
        );

        self::assertFalse($context->isFailClosed());

        self::assertSame(
            ['x'],
            $context->sensitiveValues(),
        );
    }

    public function testFailsClosedAboveMaximumSensitiveValueCount(): void
    {
        $context = ExactValueDetectionContext::create(
            array_fill(
                0,
                1001,
                'x',
            ),
        );

        self::assertTrue($context->isFailClosed());
        self::assertSame([], $context->sensitiveValues());
    }

    public function testCountLimitTakesPriorityOverEmptyValueValidation(): void
    {
        $sensitiveValues = array_fill(
            0,
            1000,
            'secret',
        );

        $sensitiveValues[] = '';

        $context = ExactValueDetectionContext::create(
            $sensitiveValues,
        );

        self::assertTrue($context->isFailClosed());
        self::assertSame([], $context->sensitiveValues());
    }

    public function testRejectsEmptyValueAnywhereWithinCountLimit(): void
    {
        $this->expectException(
            \InvalidArgumentException::class,
        );

        $this->expectExceptionMessage(
            'Sensitive values must not contain an empty string.',
        );

        ExactValueDetectionContext::create([
            str_repeat(
                'x',
                self::MIB + 1,
            ),
            '',
        ]);
    }

    public function testAcceptsTotalSensitiveValueBytesBelowLimit(): void
    {
        $context = ExactValueDetectionContext::create([
            str_repeat(
                'x',
                self::MIB - 1,
            ),
        ]);

        self::assertFalse($context->isFailClosed());
    }

    public function testAcceptsMaximumTotalSensitiveValueBytes(): void
    {
        $sensitiveValue = str_repeat(
            'x',
            self::MIB,
        );

        $context = ExactValueDetectionContext::create([
            $sensitiveValue,
        ]);

        self::assertFalse($context->isFailClosed());

        self::assertSame(
            [$sensitiveValue],
            $context->sensitiveValues(),
        );
    }

    public function testFailsClosedAboveMaximumTotalSensitiveValueBytes(): void
    {
        $context = ExactValueDetectionContext::create([
            str_repeat(
                'x',
                self::MIB,
            ),
            'y',
        ]);

        self::assertTrue($context->isFailClosed());
        self::assertSame([], $context->sensitiveValues());
    }

    public function testDuplicateValuesConsumeTotalSuppliedByteBudget(): void
    {
        $context = ExactValueDetectionContext::create(
            array_fill(
                0,
                1000,
                str_repeat('x', 1049),
            ),
        );

        self::assertTrue($context->isFailClosed());
        self::assertSame([], $context->sensitiveValues());
    }

    public function testDuplicateValuesAreDeduplicatedAfterBudgetValidation(): void
    {
        $sensitiveValue = str_repeat('x', 1024);

        $context = ExactValueDetectionContext::create(
            array_fill(
                0,
                1000,
                $sensitiveValue,
            ),
        );

        self::assertFalse($context->isFailClosed());

        self::assertSame(
            [$sensitiveValue],
            $context->sensitiveValues(),
        );
    }

    public function testPreparedValuesAreSortedByByteLength(): void
    {
        $context = ExactValueDetectionContext::create([
            '12345678',
            'a',
            '1234',
            'ab',
        ]);

        $lengths = array_map(
            strlen(...),
            $context->sensitiveValues(),
        );

        self::assertSame(
            [1, 2, 4, 8],
            $lengths,
        );
    }

    public function testAcceptsSearchWindowBelowLimit(): void
    {
        $context = self::createSearchContext();

        self::assertTrue(
            $context->consumeSearch(
                64 * self::MIB - 1,
                1,
            ),
        );

        self::assertFalse($context->isFailClosed());
    }

    public function testAcceptsMaximumSearchWindowBudget(): void
    {
        $context = self::createSearchContext();

        self::assertTrue(
            $context->consumeSearch(
                64 * self::MIB,
                1,
            ),
        );

        self::assertFalse($context->isFailClosed());
    }

    public function testFailsClosedAboveMaximumSearchWindowBudget(): void
    {
        $context = self::createSearchContext();

        self::assertFalse(
            $context->consumeSearch(
                64 * self::MIB + 1,
                1,
            ),
        );

        self::assertTrue($context->isFailClosed());
    }

    public function testAcceptsSearchOperationCountBelowLimit(): void
    {
        $context = self::createSearchContext();

        for ($index = 0; $index < 9999; ++$index) {
            self::assertTrue(
                $context->consumeSearch(
                    1,
                    1,
                ),
            );
        }

        self::assertFalse($context->isFailClosed());
    }

    public function testAcceptsMaximumSearchOperationCount(): void
    {
        $context = self::createSearchContext();

        for ($index = 0; $index < 10000; ++$index) {
            self::assertTrue(
                $context->consumeSearch(
                    1,
                    1,
                ),
            );
        }

        self::assertFalse($context->isFailClosed());
    }

    public function testFailsClosedAboveMaximumSearchOperationCount(): void
    {
        $context = self::createSearchContext();

        for ($index = 0; $index < 10000; ++$index) {
            self::assertTrue(
                $context->consumeSearch(
                    1,
                    1,
                ),
            );
        }

        self::assertFalse(
            $context->consumeSearch(
                1,
                1,
            ),
        );

        self::assertTrue($context->isFailClosed());
    }

    public function testAcceptsSearchWorkBelowLimit(): void
    {
        $context = self::createSearchContext();

        /*
         * For a 32-byte needle the exact 1 GiB work boundary occurs at:
         *
         * (searchWindow - 32 + 1) * 32 + 32
         */
        $exactWorkWindow =
            intdiv(
                self::GIB,
                32,
            )
            + 30;

        self::assertTrue(
            $context->consumeSearch(
                $exactWorkWindow - 1,
                32,
            ),
        );

        self::assertFalse($context->isFailClosed());
    }

    public function testAcceptsMaximumSearchWorkBudget(): void
    {
        $context = self::createSearchContext();

        $exactWorkWindow =
            intdiv(
                self::GIB,
                32,
            )
            + 30;

        self::assertTrue(
            $context->consumeSearch(
                $exactWorkWindow,
                32,
            ),
        );

        self::assertFalse($context->isFailClosed());
    }

    public function testFailsClosedAboveMaximumSearchWorkBudget(): void
    {
        $context = self::createSearchContext();

        $exactWorkWindow =
            intdiv(
                self::GIB,
                32,
            )
            + 30;

        self::assertFalse(
            $context->consumeSearch(
                $exactWorkWindow + 1,
                32,
            ),
        );

        self::assertTrue($context->isFailClosed());
    }

    public function testSearchWorkBudgetRemainsExhaustedAfterExactConsumption(): void
    {
        $context = self::createSearchContext();

        $exactWorkWindow =
            intdiv(
                self::GIB,
                32,
            )
            + 30;

        self::assertTrue(
            $context->consumeSearch(
                $exactWorkWindow,
                32,
            ),
        );

        self::assertFalse(
            $context->consumeSearch(
                1,
                1,
            ),
        );

        self::assertTrue($context->isFailClosed());
    }

    public function testFailClosedStateIsPermanent(): void
    {
        $context = self::createSearchContext();

        self::assertFalse(
            $context->consumeSearch(
                64 * self::MIB + 1,
                1,
            ),
        );

        self::assertFalse(
            $context->consumeSearch(
                1,
                1,
            ),
        );

        self::assertTrue($context->isFailClosed());
    }

    public function testRejectsEmptySearchWindow(): void
    {
        $context = self::createSearchContext();

        $this->expectException(\LogicException::class);

        $context->consumeSearch(
            0,
            1,
        );
    }

    public function testRejectsEmptySearchNeedle(): void
    {
        $context = self::createSearchContext();

        $this->expectException(\LogicException::class);

        $context->consumeSearch(
            1,
            0,
        );
    }

    public function testRejectsNeedleLongerThanSearchWindow(): void
    {
        $context = self::createSearchContext();

        $this->expectException(\LogicException::class);

        $context->consumeSearch(
            10,
            11,
        );
    }

    private static function createSearchContext(): ExactValueDetectionContext
    {
        return ExactValueDetectionContext::create([
            'secret',
        ]);
    }
}
