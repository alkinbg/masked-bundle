<?php

declare(strict_types=1);

namespace Masked\Bundle\Logging;

use Masked\Bundle\Detection\SensitiveDataDetectionContext;
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
     * The message and context share one sensitive-data detection context so
     * detector work cannot be multiplied by restarting resource budgets for
     * each field.
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
        #[\SensitiveParameter]
        string|\Stringable $message,
        #[\SensitiveParameter]
        array $context = [],
        #[\SensitiveParameter]
        array $sensitiveValues = [],
    ): void {
        $detectionContext =
            SensitiveDataDetectionContext::create(
                $sensitiveValues,
            );

        $maskedMessage =
            $this->sensitiveDataMasker
                ->maskWithinContext(
                    (string) $message,
                    $detectionContext,
                );

        $maskedContext =
            $this->structuredDataMasker
                ->maskWithinContext(
                    $context,
                    $detectionContext,
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
