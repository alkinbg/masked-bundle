<?php

declare(strict_types=1);

namespace Masked\Bundle\Detection;

/**
 * Holds detector contexts shared by one sensitive-data masking operation.
 *
 * @internal
 */
final readonly class SensitiveDataDetectionContext
{
    private function __construct(
        private ExactValueDetectionContext $exactValueDetectionContext,
        private PaymentCardDetectionContext $paymentCardDetectionContext,
    ) {
    }

    /**
     * @param list<string> $sensitiveValues
     */
    public static function create(
        #[\SensitiveParameter]
        array $sensitiveValues,
    ): self {
        return new self(
            ExactValueDetectionContext::create(
                $sensitiveValues,
            ),
            PaymentCardDetectionContext::create(),
        );
    }

    public function exactValueDetectionContext(): ExactValueDetectionContext
    {
        return $this->exactValueDetectionContext;
    }

    public function paymentCardDetectionContext(): PaymentCardDetectionContext
    {
        return $this->paymentCardDetectionContext;
    }
}
