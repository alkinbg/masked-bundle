<?php

declare(strict_types=1);

namespace Masked\Bundle\Logging;

use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;
use Psr\Log\LoggerInterface;

final readonly class SensitiveLogger
{
    public function __construct(
        private SensitiveDataMasker $sensitiveDataMasker,
        private StructuredDataMasker $structuredDataMasker,
    ) {
    }

    /**
     * Masks a log message and structured context before delegating them
     * to the supplied PSR-3 logger.
     *
     * Arbitrary objects inside the context are intentionally preserved by
     * StructuredDataMasker and remain the responsibility of downstream
     * processors and formatters.
     *
     * @param array<string, mixed> $context
     * @param list<string>         $sensitiveValues
     */
    public function log(
        LoggerInterface $logger,
        mixed $level,
        string|\Stringable $message,
        array $context = [],
        array $sensitiveValues = [],
    ): void {
        $maskedMessage = $this->sensitiveDataMasker->mask(
            (string) $message,
            $sensitiveValues,
        );

        $maskedContext = $this->structuredDataMasker->mask(
            $context,
            $sensitiveValues,
        );

        if (!is_array($maskedContext)) {
            throw new \LogicException('Structured data masking must preserve an array root.');
        }

        $logger->log(
            $level,
            $maskedMessage,
            $maskedContext,
        );
    }
}
