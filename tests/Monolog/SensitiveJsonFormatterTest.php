<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests\Monolog;

use Alkin\MaskedBundle\Monolog\SensitiveJsonFormatter;
use Alkin\MaskedBundle\RangeRedactor;
use Alkin\MaskedBundle\Redactor;
use Alkin\MaskedBundle\SensitiveDataMasker;
use Alkin\MaskedBundle\StructuredDataMasker;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveJsonFormatter::class)]
final class SensitiveJsonFormatterTest extends TestCase
{
    public function testMasksSensitiveDataInsideExceptionMessage(): void
    {
        $exception = new \RuntimeException(
            'Payment failed for 4111111111111111',
        );

        $output = $this->createFormatter()->format(
            $this->createRecord($exception),
        );

        $exceptionData = $this->decodeExceptionData($output);

        self::assertSame(
            'Payment failed for '.str_repeat('█', 16),
            $exceptionData['message'],
        );

        self::assertStringNotContainsString(
            '4111111111111111',
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

        $output = $this->createFormatter()->format(
            $this->createRecord($exception),
        );

        $exceptionData = $this->decodeExceptionData($output);

        self::assertSame(
            'Primary card '.str_repeat('█', 16),
            $exceptionData['message'],
        );

        $previousData = $exceptionData['previous'] ?? null;

        self::assertIsArray($previousData);

        self::assertSame(
            'Backup card '.str_repeat('█', 16),
            $previousData['message'],
        );

        self::assertStringNotContainsString(
            '4111111111111111',
            $output,
        );
        self::assertStringNotContainsString(
            '5555555555554444',
            $output,
        );
    }

    public function testPreservesJsonFormatterStacktraceBehavior(): void
    {
        $exception = $this->createExceptionWithTrace();

        $withoutStacktrace = $this->decodeExceptionData(
            $this->createFormatter()->format(
                $this->createRecord($exception),
            ),
        );

        $withStacktrace = $this->decodeExceptionData(
            $this
                ->createFormatter(includeStacktraces: true)
                ->format(
                    $this->createRecord($exception),
                ),
        );

        self::assertArrayNotHasKey(
            'trace',
            $withoutStacktrace,
        );
        self::assertArrayHasKey(
            'trace',
            $withStacktrace,
        );

        self::assertSame(
            'Payment failed for '.str_repeat('█', 16),
            $withStacktrace['message'],
        );
    }

    public function testUsesConfiguredMaskCharacter(): void
    {
        $exception = new \RuntimeException(
            'Card 4111111111111111',
        );

        $output = $this
            ->createFormatter('*')
            ->format(
                $this->createRecord($exception),
            );

        $exceptionData = $this->decodeExceptionData($output);

        self::assertSame(
            'Card '.str_repeat('*', 16),
            $exceptionData['message'],
        );
    }

    private function createFormatter(
        string $maskCharacter = '█',
        bool $includeStacktraces = false,
    ): SensitiveJsonFormatter {
        $sensitiveDataMasker = new SensitiveDataMasker(
            rangeRedactor: new RangeRedactor(
                redactor: new Redactor($maskCharacter),
            ),
        );

        return new SensitiveJsonFormatter(
            structuredDataMasker: new StructuredDataMasker(
                $sensitiveDataMasker,
            ),
            includeStacktraces: $includeStacktraces,
        );
    }

    private function createRecord(
        \RuntimeException $exception,
    ): LogRecord {
        return new LogRecord(
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
    }

    /**
     * @return array<mixed, mixed>
     */
    private function decodeExceptionData(
        string $output,
    ): array {
        $decoded = json_decode(
            $output,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (!is_array($decoded)) {
            self::fail(
                'Expected the formatter output to decode to an array.',
            );
        }

        $context = $decoded['context'] ?? null;

        if (!is_array($context)) {
            self::fail(
                'Expected the decoded record to contain a context array.',
            );
        }

        $exception = $context['exception'] ?? null;

        if (!is_array($exception)) {
            self::fail(
                'Expected the context to contain normalized exception data.',
            );
        }

        /* @var array<mixed, mixed> $exception */
        return $exception;
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
