<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Detection;

use Masked\Bundle\Detection\PaymentCardDetectionContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentCardDetectionContext::class)]
final class PaymentCardDetectionContextTest extends TestCase
{
    public function testFailsClosedWhenCandidateCheckBudgetIsExceeded(): void
    {
        $context = PaymentCardDetectionContext::create();

        for ($index = 0; $index < 10000; ++$index) {
            if (!$context->consumeCandidateCheck()) {
                self::fail(
                    'Candidate-check budget was exhausted too early.',
                );
            }
        }

        self::assertFalse(
            $context->isFailClosed(),
        );

        self::assertFalse(
            $context->consumeCandidateCheck(),
        );

        self::assertTrue(
            $context->isFailClosed(),
        );

        self::assertFalse(
            $context->consumeCandidateCheck(),
        );
    }
}
