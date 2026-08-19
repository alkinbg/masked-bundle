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
final class SensitiveLoggerPaymentCardBudgetTest extends TestCase
{
    public function testMessageAndContextShareCandidateCheckBudget(): void
    {
        /*
         * For at least 19 one-digit groups, the scanner performs:
         *
         *     7 × (group count - 15)
         *
         * candidate validations.
         *
         * 1,443 groups therefore consume 9,996 checks. The context value
         * attempts another 35, causing the shared operation to fail closed.
         */
        $message = self::createCandidateHeavyValue(
            1443,
        );

        $contextValue =
            self::createCandidateHeavyValue(
                20,
            );

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

        $sensitiveLogger->log(
            logger: $logger,
            level: Level::Warning,
            message: $message,
            context: [
                'value' => $contextValue,
            ],
        );

        $records = $handler->getRecords();

        self::assertCount(
            1,
            $records,
        );

        self::assertSame(
            $message,
            $records[0]->message,
        );

        self::assertSame(
            str_repeat(
                '█',
                strlen($contextValue),
            ),
            $records[0]->context['value'],
        );
    }

    private static function createCandidateHeavyValue(
        int $digitGroupCount,
    ): string {
        return implode(
            ' ',
            array_fill(
                0,
                $digitGroupCount,
                '1',
            ),
        );
    }
}
