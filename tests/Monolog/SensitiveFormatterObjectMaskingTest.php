<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Monolog;

use Masked\Bundle\Monolog\SensitiveJsonFormatter;
use Masked\Bundle\Monolog\SensitiveLineFormatter;
use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;
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

    public function testJsonFormatterPreservesEmptyObjectRepresentation(): void
    {
        $output = $this->createJsonFormatter()->format(
            $this->createRecord(new \stdClass()),
        );

        $decoded = json_decode(
            $output,
            false,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertInstanceOf(\stdClass::class, $decoded);
        self::assertInstanceOf(\stdClass::class, $decoded->context);
        self::assertInstanceOf(\stdClass::class, $decoded->context->payload);
    }

    public function testJsonFormatterPreservesNestedObjectRepresentation(): void
    {
        $nested = new \stdClass();
        $nested->card = '4111111111111111';

        $payload = new \stdClass();
        $payload->nested = $nested;

        $output = $this->createJsonFormatter()->format(
            $this->createRecord($payload),
        );

        $decoded = json_decode(
            $output,
            false,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertInstanceOf(\stdClass::class, $decoded);
        self::assertInstanceOf(\stdClass::class, $decoded->context);
        self::assertInstanceOf(\stdClass::class, $decoded->context->payload);
        self::assertInstanceOf(
            \stdClass::class,
            $decoded->context->payload->nested,
        );

        self::assertSame(
            str_repeat('█', 16),
            $decoded->context->payload->nested->card,
        );
    }

    public function testJsonFormatterMasksJsonSerializableReturningObject(): void
    {
        $payload = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return (object) [
                    'card' => '4111111111111111',
                ];
            }
        };

        $output = $this->createJsonFormatter()->format(
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

    public function testJsonFormatterMasksJsonSerializableReturningNestedObject(): void
    {
        $nested = new \stdClass();
        $nested->card = '4111111111111111';

        $payload = new class($nested) implements \JsonSerializable {
            public function __construct(
                private readonly object $nested,
            ) {
            }

            public function jsonSerialize(): mixed
            {
                return [
                    'level_one' => [
                        'level_two' => $this->nested,
                    ],
                ];
            }
        };

        $output = $this->createJsonFormatter()->format(
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

    public function testJsonFormatterMasksJsonSerializableObjectInBatch(): void
    {
        $payload = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return (object) [
                    'card' => '4111111111111111',
                ];
            }
        };

        $output = $this->createJsonFormatter()->formatBatch([
            $this->createRecord($payload),
        ]);

        self::assertStringNotContainsString(
            '4111111111111111',
            $output,
        );

        self::assertStringContainsString(
            str_repeat('█', 16),
            $output,
        );

        $decoded = json_decode(
            $output,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);
        self::assertCount(1, $decoded);

        $record = $decoded[0] ?? null;

        self::assertIsArray($record);

        $context = $record['context'] ?? null;

        self::assertIsArray($context);

        $normalizedPayload = $context['payload'] ?? null;

        self::assertIsArray($normalizedPayload);

        self::assertSame(
            str_repeat('█', 16),
            $normalizedPayload['card'],
        );
    }

    private function createLineFormatter(): SensitiveLineFormatter
    {
        return new SensitiveLineFormatter(
            new SensitiveDataMasker(),
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
