<?php

declare(strict_types=1);

namespace Masked\Bundle;

use Masked\Bundle\Detection\ExactValueDetectionContext;
use Masked\Bundle\Detection\ExactValueDetector;
use Masked\Bundle\Detection\PaymentCardDetector;

final readonly class SensitiveDataMasker
{
    public function __construct(
        private PaymentCardDetector $paymentCardDetector =
        new PaymentCardDetector(),
        private ExactValueDetector $exactValueDetector =
        new ExactValueDetector(),
        private RangeRedactor $rangeRedactor =
        new RangeRedactor(),
    ) {
    }

    /**
     * @param list<string> $sensitiveValues
     */
    public function mask(
        #[\SensitiveParameter]
        string $value,
        #[\SensitiveParameter]
        array $sensitiveValues = [],
    ): string {
        return $this->maskWithinContext(
            $value,
            ExactValueDetectionContext::create(
                $sensitiveValues,
            ),
        );
    }

    /**
     * Masks one string using an exact-value work budget shared by the
     * surrounding operation.
     *
     * Exact-value detection runs first. If its shared resource budget is
     * exhausted, the complete current input is redacted immediately and no
     * additional automatic detector work is performed.
     *
     * @internal
     */
    public function maskWithinContext(
        #[\SensitiveParameter]
        string $value,
        #[\SensitiveParameter]
        ExactValueDetectionContext $exactValueDetectionContext,
    ): string {
        $exactValueMatches =
            $this->exactValueDetector->detectWithinContext(
                $value,
                $exactValueDetectionContext,
            );

        if (
            $exactValueDetectionContext
                ->isFailClosed()
        ) {
            return $this->rangeRedactor->redact(
                $value,
                $exactValueMatches,
            );
        }

        $matches = [
            ...$exactValueMatches,
            ...$this->paymentCardDetector->detect(
                $value,
            ),
        ];

        return $this->rangeRedactor->redact(
            $value,
            $matches,
        );
    }
}
