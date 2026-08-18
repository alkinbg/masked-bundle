<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests\Monolog;

use Alkin\MaskedBundle\Monolog\SensitiveLineFormatter;
use Alkin\MaskedBundle\RangeRedactor;
use Alkin\MaskedBundle\Redactor;
use Alkin\MaskedBundle\SensitiveDataMasker;
use Alkin\MaskedBundle\StructuredDataMasker;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveLineFormatter::class)]
final class SensitiveLineFormatterTest extends TestCase
{
    public function testMasksSensitiveDataInsideExceptionMessage(): void
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

        $output = $this->createFormatter()->format($record);

        self::assertStringNotContainsString(
            '4111111111111111',
            $output,
        );
        self::assertStringContainsString(
            str_repeat('█', 16),
            $output,
        );

        self::assertSame(
            'Payment failed for 4111111111111111',
            $exception->getMessage(),
        );
    }

    public function testMasksPreviousExceptionMessages(): void
    {
        $previous = new \RuntimeException(
            'Backup card 5555555555554444',
        );

        $exception = new \RuntimeException(
            'Primary card 4111111111111111',
            previous: $previous,
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

        $output = $this->createFormatter()->format($record);

        self::assertStringNotContainsString(
            '4111111111111111',
            $output,
        );
        self::assertStringNotContainsString(
            '5555555555554444',
            $output,
        );
        self::assertStringContainsString(
            'Primary card '.str_repeat('█', 16),
            $output,
        );
        self::assertStringContainsString(
            'Backup card '.str_repeat('█', 16),
            $output,
        );
    }

    public function testPreservesLineFormatterStacktraceBehavior(): void
    {
        $exception = $this->createExceptionWithTrace();

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

        $withoutStacktrace =
            $this->createFormatter()->format($record);

        $withStacktrace = $this
            ->createFormatter(includeStacktraces: true)
            ->format($record);

        self::assertStringNotContainsString(
            '[stacktrace]',
            $withoutStacktrace,
        );
        self::assertStringContainsString(
            '[stacktrace]',
            $withStacktrace,
        );

        self::assertStringNotContainsString(
            '4111111111111111',
            $withStacktrace,
        );
    }

    public function testUsesConfiguredMaskCharacter(): void
    {
        $exception = new \RuntimeException(
            'Card 4111111111111111',
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

        $output = $this
            ->createFormatter('*')
            ->format($record);

        self::assertStringContainsString(
            'Card '.str_repeat('*', 16),
            $output,
        );
    }

    private function createFormatter(
        string $maskCharacter = '█',
        bool $includeStacktraces = false,
    ): SensitiveLineFormatter {
        $sensitiveDataMasker = new SensitiveDataMasker(
            rangeRedactor: new RangeRedactor(
                redactor: new Redactor($maskCharacter),
            ),
        );

        return new SensitiveLineFormatter(
            structuredDataMasker: new StructuredDataMasker(
                $sensitiveDataMasker,
            ),
            includeStacktraces: $includeStacktraces,
        );
    }

    private function createExceptionWithTrace(): \RuntimeException
    {
        try {
            $this->throwSensitiveException();
        } catch (\RuntimeException $exception) {
            return $exception;
        }
    }

    private function throwSensitiveException(): never
    {
        throw new \RuntimeException('Payment failed for 4111111111111111');
    }
}
