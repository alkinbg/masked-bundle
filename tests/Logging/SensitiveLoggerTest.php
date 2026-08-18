<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Logging;

use Masked\Bundle\Logging\SensitiveLogger;
use Masked\Bundle\RangeRedactor;
use Masked\Bundle\Redactor;
use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveLogger::class)]
final class SensitiveLoggerTest extends TestCase
{
    public function testMasksExplicitSensitiveValuesBeforeLoggerProcessing(): void
    {
        $secret = 'super-secret';

        $handler = new TestHandler();

        $logger = new Logger(
            'test',
            [$handler],
        );

        $processorMessage = null;
        $processorContext = null;

        $logger->pushProcessor(
            static function (LogRecord $record) use (
                &$processorMessage,
                &$processorContext,
            ): LogRecord {
                $processorMessage = $record->message;
                $processorContext = $record->context;

                return $record;
            },
        );

        $this->createSensitiveLogger()->log(
            logger: $logger,
            level: Level::Warning,
            message: 'Authentication failed for '.$secret,
            context: [
                'token' => $secret,
                'nested' => [
                    'token' => $secret,
                ],
            ],
            sensitiveValues: [
                $secret,
            ],
        );

        self::assertSame(
            'Authentication failed for '.str_repeat('█', 12),
            $processorMessage,
        );

        self::assertSame(
            [
                'token' => str_repeat('█', 12),
                'nested' => [
                    'token' => str_repeat('█', 12),
                ],
            ],
            $processorContext,
        );

        $records = $handler->getRecords();

        self::assertCount(1, $records);

        self::assertSame(
            'Authentication failed for '.str_repeat('█', 12),
            $records[0]->message,
        );
    }

    public function testAlsoAppliesAutomaticDetection(): void
    {
        $handler = new TestHandler();

        $logger = new Logger(
            'test',
            [$handler],
        );

        $this->createSensitiveLogger()->log(
            logger: $logger,
            level: Level::Info,
            message: 'Card 4111111111111111',
            context: [
                'card' => '5555555555554444',
            ],
        );

        $records = $handler->getRecords();

        self::assertCount(1, $records);

        self::assertSame(
            'Card '.str_repeat('█', 16),
            $records[0]->message,
        );

        self::assertSame(
            [
                'card' => str_repeat('█', 16),
            ],
            $records[0]->context,
        );
    }

    public function testPreservesObjectsInsideContext(): void
    {
        $handler = new TestHandler();

        $logger = new Logger(
            'test',
            [$handler],
        );

        $exception = new \RuntimeException('Authentication failed.');

        $this->createSensitiveLogger()->log(
            logger: $logger,
            level: Level::Error,
            message: 'Authentication failed.',
            context: [
                'exception' => $exception,
            ],
            sensitiveValues: [
                'super-secret',
            ],
        );

        $records = $handler->getRecords();

        self::assertCount(1, $records);

        self::assertSame(
            $exception,
            $records[0]->context['exception'],
        );
    }

    public function testUsesConfiguredMaskCharacter(): void
    {
        $handler = new TestHandler();

        $logger = new Logger(
            'test',
            [$handler],
        );

        $this->createSensitiveLogger('*')->log(
            logger: $logger,
            level: Level::Notice,
            message: 'Token super-secret',
            context: [
                'token' => 'super-secret',
            ],
            sensitiveValues: [
                'super-secret',
            ],
        );

        $records = $handler->getRecords();

        self::assertCount(1, $records);

        self::assertSame(
            'Token '.str_repeat('*', 12),
            $records[0]->message,
        );

        self::assertSame(
            [
                'token' => str_repeat('*', 12),
            ],
            $records[0]->context,
        );
    }

    private function createSensitiveLogger(
        string $maskCharacter = '█',
    ): SensitiveLogger {
        $sensitiveDataMasker = new SensitiveDataMasker(
            rangeRedactor: new RangeRedactor(
                redactor: new Redactor($maskCharacter),
            ),
        );

        return new SensitiveLogger(
            sensitiveDataMasker: $sensitiveDataMasker,
            structuredDataMasker: new StructuredDataMasker(
                $sensitiveDataMasker,
            ),
        );
    }
}
