<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests\Monolog;

use Alkin\MaskedBundle\Monolog\SensitiveJsonFormatter;
use Alkin\MaskedBundle\Monolog\SensitiveLineFormatter;
use Alkin\MaskedBundle\SensitiveDataMasker;
use Alkin\MaskedBundle\StructuredDataMasker;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveLineFormatter::class)]
#[CoversClass(SensitiveJsonFormatter::class)]
final class SensitiveFormatterObjectMaskingTest extends TestCase
{
    public function testLineFormatterMasksPublicObjectProperties(): void
    {
        $payload = new class {
            public string $card = '4111111111111111';
        };

        $output = $this->createLineFormatter()->format(
            $this->createRecord($payload),
        );

        self::assertStringNotContainsString(
            '4111111111111111',
            $output,
        );

        self::assertStringContainsString(
            str_repeat('█', 16),
            $output,
        );
    }

    /**
     * @throws \JsonException
     */
    public function testJsonFormatterMasksPublicObjectProperties(): void
    {
        $payload = new class {
            public string $card = '4111111111111111';
        };

        $decoded = $this->decode(
            $this->createJsonFormatter()->format(
                $this->createRecord($payload),
            ),
        );

        $context = $decoded['context'] ?? null;

        self::assertIsArray($context);

        $normalizedPayload = $context['payload'] ?? null;

        self::assertIsArray($normalizedPayload);

        self::assertSame(
            str_repeat('█', 16),
            $normalizedPayload['card'],
        );
    }

    public function testLineFormatterMasksNestedJsonSerializableData(): void
    {
        $output = $this->createLineFormatter()->format(
            $this->createRecord(
                $this->createJsonSerializablePayload(),
            ),
        );

        self::assertStringNotContainsString(
            '4111111111111111',
            $output,
        );

        self::assertStringContainsString(
            str_repeat('█', 16),
            $output,
        );
    }

    public function testJsonFormatterMasksNestedJsonSerializableData(): void
    {
        $output = $this->createJsonFormatter()->format(
            $this->createRecord(
                $this->createJsonSerializablePayload(),
            ),
        );

        self::assertStringNotContainsString(
            '4111111111111111',
            $output,
        );

        self::assertStringContainsString(
            str_repeat('█', 16),
            $output,
        );
    }

    private function createLineFormatter(): SensitiveLineFormatter
    {
        return new SensitiveLineFormatter(
            new StructuredDataMasker(
                new SensitiveDataMasker(),
            ),
        );
    }

    private function createJsonFormatter(): SensitiveJsonFormatter
    {
        return new SensitiveJsonFormatter(
            new StructuredDataMasker(
                new SensitiveDataMasker(),
            ),
        );
    }

    private function createRecord(object $payload): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(
                '2026-08-18T00:00:00+00:00',
            ),
            channel: 'app',
            level: Level::Info,
            message: 'Payload received',
            context: [
                'payload' => $payload,
            ],
        );
    }

    private function createJsonSerializablePayload(): \JsonSerializable
    {
        $nested = new class {
            public string $card = '4111111111111111';
        };

        return new class($nested) implements \JsonSerializable {
            public function __construct(
                private readonly object $nested,
            ) {
            }

            /**
             * @return array<string, object>
             */
            public function jsonSerialize(): array
            {
                return [
                    'nested' => $this->nested,
                ];
            }
        };
    }

    /**
     * @return array<mixed, mixed>
     *
     * @throws \JsonException
     */
    private function decode(string $output): array
    {
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

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
