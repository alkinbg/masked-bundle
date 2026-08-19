<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Logging;

use Masked\Bundle\Logging\SensitiveLogger;
use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveLogger::class)]
final class SensitiveLoggerExactValueBudgetTest extends TestCase
{
    public function testMessageAndContextShareExactValueSearchBudget(): void
    {
        $sensitiveValues = [];

        for ($index = 0; $index < 64; ++$index) {
            $sensitiveValues[] = sprintf(
                'secret-%02d',
                $index,
            );
        }

        $handler = new TestHandler();

        $logger = new Logger(
            'test',
            [$handler],
        );

        $sensitiveDataMasker =
            new SensitiveDataMasker();

        $sensitiveLogger = new SensitiveLogger(
            sensitiveDataMasker: $sensitiveDataMasker,
            structuredDataMasker: new StructuredDataMasker(
                $sensitiveDataMasker,
            ),
        );

        $message = str_repeat(
            'x',
            1024 * 1024,
        );

        $sensitiveLogger->log(
            logger: $logger,
            level: Level::Warning,
            message: $message,
            context: [
                'value' => '0123456789',
            ],
            sensitiveValues: $sensitiveValues,
        );

        $records = $handler->getRecords();

        self::assertCount(1, $records);

        /*
         * The message consumed exactly the 64 MiB search-window budget without
         * exceeding it, so it remains unchanged.
         */
        self::assertSame(
            $message,
            $records[0]->message,
        );

        /*
         * The context value uses the same context. Its first attempted search
         * exceeds the remaining zero-byte budget, therefore the value fails
         * closed instead of receiving a fresh 64 MiB allowance.
         */
        self::assertSame(
            str_repeat('█', 10),
            $records[0]->context['value'],
        );
    }
}
