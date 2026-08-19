<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests;

use Masked\Bundle\StructuredDataMasker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StructuredDataMasker::class)]
final class StructuredPaymentCardBudgetTest extends TestCase
{
    public function testCandidateCheckBudgetIsSharedAcrossStructuredValues(): void
    {
        $candidateHeavyValue =
            self::createCandidateHeavyValue(
                200,
            );

        /*
         * Each value consumes 1,295 candidate validations. The eighth value
         * therefore exceeds the shared 10,000-check operation budget.
         *
         * With one fresh detector budget per scalar, every value would remain
         * unchanged and this regression would fail.
         */
        $value = array_fill(
            0,
            8,
            $candidateHeavyValue,
        );

        $value['later-key'] = 'later-value';

        $masked = new StructuredDataMasker()->mask(
            $value,
        );

        self::assertIsArray($masked);

        for ($index = 0; $index < 7; ++$index) {
            self::assertSame(
                $candidateHeavyValue,
                $masked[$index],
            );
        }

        self::assertSame(
            str_repeat(
                '█',
                strlen($candidateHeavyValue),
            ),
            $masked[7],
        );

        /*
         * Fail-closed state remains active for later keys and values in the
         * same traversal.
         */
        self::assertArrayNotHasKey(
            'later-key',
            $masked,
        );

        $maskedLaterKey = str_repeat(
            '█',
            strlen('later-key'),
        );

        self::assertArrayHasKey(
            $maskedLaterKey,
            $masked,
        );

        self::assertSame(
            str_repeat(
                '█',
                strlen('later-value'),
            ),
            $masked[$maskedLaterKey],
        );
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
