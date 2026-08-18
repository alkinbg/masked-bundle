<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Monolog;

use Alkin\MaskedBundle\SensitiveDataMasker;
use Alkin\MaskedBundle\StructuredDataMasker;
use Monolog\LogRecord;

final readonly class SensitiveDataProcessor
{
    public function __construct(
        private SensitiveDataMasker $sensitiveDataMasker,
        private StructuredDataMasker $structuredDataMasker,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->sensitiveDataMasker->mask(
                $record->message,
            ),
            context: $this->structuredDataMasker->mask(
                $record->context,
            ),
            extra: $this->structuredDataMasker->mask(
                $record->extra,
            ),
        );
    }
}
