<?php

declare(strict_types=1);

namespace Masked\Bundle\Monolog;

use Masked\Bundle\Detection\SensitiveDataDetectionContext;
use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;
use Monolog\LogRecord;

final readonly class SensitiveDataProcessor
{
    public function __construct(
        private SensitiveDataMasker $sensitiveDataMasker,
        private StructuredDataMasker $structuredDataMasker,
    ) {
    }

    public function __invoke(
        #[\SensitiveParameter]
        LogRecord $record,
    ): LogRecord {
        $detectionContext =
            SensitiveDataDetectionContext::create([]);

        return $record->with(
            message: $this->sensitiveDataMasker->maskWithinContext(
                $record->message,
                $detectionContext,
            ),
            context: $this->structuredDataMasker->maskWithinContext(
                $record->context,
                $detectionContext,
            ),
            extra: $this->structuredDataMasker->maskWithinContext(
                $record->extra,
                $detectionContext,
            ),
        );
    }
}
