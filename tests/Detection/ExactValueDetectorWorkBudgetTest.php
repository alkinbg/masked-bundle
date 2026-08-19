<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Detection;

use Masked\Bundle\Detection\ExactValueDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExactValueDetector::class)]
final class ExactValueDetectorWorkBudgetTest extends TestCase
{
    public function testFailsClosedBeforePathologicalLongNeedleSearch(): void
    {
        $value = str_repeat(
            'a',
            1024 * 1024,
        );

        /*
         * Almost the entire needle matches the repetitive haystack, but the
         * final byte does not. This shape can make naive substring-search work
         * depend heavily on both haystack and needle length.
         *
         * The conservative work budget must reject the search before strpos()
         * is attempted and protect the complete current value.
         */
        $sensitiveValue =
            str_repeat(
                'a',
                16 * 1024 - 1,
            )
            .'b';

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

    public function testStillSearchesReasonableLongerNeedleWithinWorkBudget(): void
    {
        $value =
            str_repeat(
                'a',
                1024 * 1024,
            )
            .'secret';

        $matches = new ExactValueDetector()->detect(
            $value,
            ['secret'],
        );

        self::assertCount(1, $matches);

        self::assertSame(
            1024 * 1024,
            $matches[0]->byteOffset,
        );

        self::assertSame(
            strlen('secret'),
            $matches[0]->byteLength,
        );
    }
}
