<?php

declare(strict_types=1);

namespace Masked\Bundle\Monolog;

use Masked\Bundle\SensitiveDataMasker;
use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

final class SensitiveLineFormatter extends LineFormatter
{
    public function __construct(
        private readonly SensitiveDataMasker $sensitiveDataMasker,
        ?string $format = null,
        ?string $dateFormat = null,
        bool $allowInlineLineBreaks = false,
        bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = false,
    ) {
        parent::__construct(
            $format,
            $dateFormat,
            $allowInlineLineBreaks,
            $ignoreEmptyContextAndExtra,
            $includeStacktraces,
        );
    }

    public function format(
        #[\SensitiveParameter]
        LogRecord $record,
    ): string {
        return $this->sensitiveDataMasker->mask(
            parent::format($record),
        );
    }
}
