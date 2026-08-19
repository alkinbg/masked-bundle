<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests;

use Masked\Bundle\StructuredDataMasker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StructuredDataMasker::class)]
final class StructuredExactValueBudgetTest extends TestCase
{
    public function testExactValueSearchBudgetIsSharedAcrossStructuredValues(): void
    {
        $sensitiveValues = self::createNonMatchingSensitiveValues(
            64,
        );

        /*
         * The first value consumes exactly 64 MiB of worst-case substring
         * search windows: 64 non-matching patterns × 1 MiB.
         *
         * Integer list keys are shorter than every sensitive pattern, so they
         * consume no exact-value search work while the context is active.
         *
         * The second value therefore encounters the already exhausted shared
         * search-window budget and must fail closed. If each scalar received a
         * fresh context, this regression would leave the second value intact.
         */
        $value = [
            str_repeat(
                'x',
                1024 * 1024,
            ),
            '0123456789',
        ];

        $masked = new StructuredDataMasker()->mask(
            $value,
            $sensitiveValues,
        );

        self::assertIsArray($masked);

        self::assertSame(
            $value[0],
            $masked[0],
        );

        self::assertSame(
            str_repeat(
                '█',
                strlen($value[1]),
            ),
            $masked[1],
        );
    }

    public function testFailClosedStateProtectsLaterStructuredKeysAndValues(): void
    {
        $sensitiveValues = self::createNonMatchingSensitiveValues(
            64,
        );

        $value = [
            str_repeat(
                'x',
                1024 * 1024,
            ),
            '0123456789',
            'abcdefghij',
        ];

        $masked = new StructuredDataMasker()->mask(
            $value,
            $sensitiveValues,
        );

        self::assertIsArray($masked);
        self::assertCount(3, $masked);

        /*
         * The first value consumes exactly the complete search-window budget
         * without exceeding it.
         */
        self::assertSame(
            $value[0],
            $masked[0],
        );

        /*
         * The second value is the first input that attempts to search after
         * the budget has reached zero, so it enters fail-closed state.
         */
        self::assertSame(
            str_repeat('█', 10),
            $masked[1],
        );

        /*
         * Fail-closed state is permanent for the operation. The next integer
         * key is therefore treated as sensitive too, so the original key 2 is
         * no longer exposed.
         */
        self::assertArrayNotHasKey(
            2,
            $masked,
        );

        self::assertArrayHasKey(
            '█',
            $masked,
        );

        /*
         * The value behind that later key is protected by the same permanent
         * fail-closed state.
         */
        self::assertSame(
            str_repeat('█', 10),
            $masked['█'],
        );

        self::assertNotContains(
            'abcdefghij',
            array_values($masked)
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
