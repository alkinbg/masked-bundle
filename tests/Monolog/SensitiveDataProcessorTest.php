<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Monolog;

use Masked\Bundle\Monolog\SensitiveDataProcessor;
use Masked\Bundle\RangeRedactor;
use Masked\Bundle\Redactor;
use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveDataProcessor::class)]
final class SensitiveDataProcessorTest extends TestCase
{
    public function testMasksMessageContextAndExtra(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(
                '2026-08-18T00:00:00+00:00',
            ),
            channel: 'app',
            level: Level::Info,
            message: 'Payment failed for 4111111111111111',
            context: [
                'card' => '5555555555554444',
                'nested' => [
                    'card' => '4111111111111111',
                ],
            ],
            extra: [
                'gateway_response' => 'Declined 5555555555554444',
            ],
        );

        $processed = $this->createProcessor()($record);

        self::assertNotSame($record, $processed);

        self::assertSame(
            'Payment failed for '.str_repeat('█', 16),
            $processed->message,
        );
        self::assertSame(
            [
                'card' => str_repeat('█', 16),
                'nested' => [
                    'card' => str_repeat('█', 16),
                ],
            ],
            $processed->context,
        );
        self::assertSame(
            [
                'gateway_response' => 'Declined '.str_repeat('█', 16),
            ],
            $processed->extra,
        );

        self::assertSame(
            'Payment failed for 4111111111111111',
            $record->message,
        );
    }

    public function testPreservesObjectsForDownstreamProcessing(): void
    {
        $exception = new \RuntimeException(
            'Payment failed for 4111111111111111',
        );

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(
                '2026-08-18T00:00:00+00:00',
            ),
            channel: 'app',
            level: Level::Error,
            message: 'Payment failed',
            context: [
                'exception' => $exception,
            ],
        );

        $processed = $this->createProcessor()($record);

        self::assertSame(
            $exception,
            $processed->context['exception'],
        );
    }

    public function testUsesConfiguredMaskCharacterAcrossCompleteRecord(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(
                '2026-08-18T00:00:00+00:00',
            ),
            channel: 'security',
            level: Level::Warning,
            message: 'Card 4111111111111111',
            context: [
                'card' => '4111111111111111',
            ],
            extra: [
                'card' => '4111111111111111',
            ],
        );

        $processed = $this->createProcessor('*')($record);

        self::assertSame(
            'Card '.str_repeat('*', 16),
            $processed->message,
        );
        self::assertSame(
            [
                'card' => str_repeat('*', 16),
            ],
            $processed->context,
        );
        self::assertSame(
            [
                'card' => str_repeat('*', 16),
            ],
            $processed->extra,
        );
    }

    private function createProcessor(
        string $maskCharacter = '█',
    ): SensitiveDataProcessor {
        $sensitiveDataMasker = new SensitiveDataMasker(
            rangeRedactor: new RangeRedactor(
                redactor: new Redactor($maskCharacter),
            ),
        );

        return new SensitiveDataProcessor(
            sensitiveDataMasker: $sensitiveDataMasker,
            structuredDataMasker: new StructuredDataMasker(
                $sensitiveDataMasker,
            ),
        );
    }
}
