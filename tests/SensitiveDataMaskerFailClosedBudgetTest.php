<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests;

use Masked\Bundle\Detection\SensitiveDataDetectionContext;
use Masked\Bundle\SensitiveDataMasker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveDataMasker::class)]
final class SensitiveDataMaskerFailClosedBudgetTest extends TestCase
{
    public function testSkipsExactValueWorkWhenPaymentCardContextAlreadyFailedClosed(): void
    {
        $detectionContext =
            SensitiveDataDetectionContext::create([
                'x',
            ]);

        $paymentCardDetectionContext =
            $detectionContext
                ->paymentCardDetectionContext();

        $successfulCandidateChecks = 0;

        for ($index = 0; $index < 10000; ++$index) {
            if (
                $paymentCardDetectionContext
                    ->consumeCandidateCheck()
            ) {
                ++$successfulCandidateChecks;
            }
        }

        self::assertSame(
            10000,
            $successfulCandidateChecks,
        );

        self::assertFalse(
            $paymentCardDetectionContext
                ->consumeCandidateCheck(),
        );

        self::assertTrue(
            $paymentCardDetectionContext
                ->isFailClosed(),
        );

        $masked = new SensitiveDataMasker()
            ->maskWithinContext(
                'a',
                $detectionContext,
            );

        self::assertSame(
            '█',
            $masked,
        );

        /*
         * If maskWithinContext() performed exact-value detection before
         * noticing the already fail-closed payment-card context, one exact
         * search operation would already have been consumed here.
         */
        $exactValueDetectionContext =
            $detectionContext
                ->exactValueDetectionContext();

        $successfulSearches = 0;

        for ($index = 0; $index < 10000; ++$index) {
            if (
                $exactValueDetectionContext->consumeSearch(
                    1,
                    1,
                )
            ) {
                ++$successfulSearches;
            }
        }

        self::assertSame(
            10000,
            $successfulSearches,
        );

        self::assertFalse(
            $exactValueDetectionContext->consumeSearch(
                1,
                1,
            ),
        );

        self::assertTrue(
            $exactValueDetectionContext
                ->isFailClosed(),
        );
    }
}
