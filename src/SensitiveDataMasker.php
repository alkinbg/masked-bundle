<?php

declare(strict_types=1);

namespace Masked\Bundle;

use Masked\Bundle\Detection\ExactValueDetector;
use Masked\Bundle\Detection\PaymentCardDetector;

final readonly class SensitiveDataMasker
{
    public function __construct(
        private PaymentCardDetector $paymentCardDetector =
        new PaymentCardDetector(),
        private ExactValueDetector $exactValueDetector =
        new ExactValueDetector(),
        private RangeRedactor $rangeRedactor = new RangeRedactor(),
    ) {
    }

    /**
     * @param list<string> $sensitiveValues
     */
    public function mask(
        string $value,
        array $sensitiveValues = [],
    ): string {
        $matches = [
            ...$this->paymentCardDetector->detect($value),
            ...$this->exactValueDetector->detect(
                $value,
                $sensitiveValues,
            ),
        ];

        return $this->rangeRedactor->redact(
            $value,
            $matches,
        );
    }
}
