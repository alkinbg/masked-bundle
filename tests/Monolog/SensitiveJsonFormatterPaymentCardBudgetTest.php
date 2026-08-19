<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Monolog;

use Masked\Bundle\Monolog\SensitiveJsonFormatter;
use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveJsonFormatter::class)]
final class SensitiveJsonFormatterPaymentCardBudgetTest extends TestCase
{
    public function testSharesCandidateCheckBudgetAcrossNormalizedTree(): void
    {
        $candidateHeavyValue = self::createCandidateHeavyValue(200);

        /*
         * Each value consumes 1,295 candidate validations. The eighth value
         * therefore exceeds the shared 10,000-check operation budget.
         *
         * If the formatter starts a fresh detector context for every scalar,
         * all eight values remain unchanged and this regression fails.
         */
        $context = array_fill(
            0,
            8,
            $candidateHeavyValue,
        );

        $context['later-key'] = 'later-value';

        $output = $this->createFormatter()->format(
            $this->createRecord($context),
        );

        $decodedContext = $this->decodeContext($output);

        for ($index = 0; $index < 7; ++$index) {
            self::assertSame(
                $candidateHeavyValue,
                $decodedContext[$index],
            );
        }

        self::assertSame(
            str_repeat(
                '█',
                strlen($candidateHeavyValue),
            ),
            $decodedContext[7],
        );

        self::assertArrayNotHasKey(
            'later-key',
            $decodedContext,
        );

        $maskedLaterKey = str_repeat(
            '█',
            strlen('later-key'),
        );

        self::assertArrayHasKey(
            $maskedLaterKey,
            $decodedContext,
        );

        self::assertSame(
            str_repeat(
                '█',
                strlen('later-value'),
            ),
            $decodedContext[$maskedLaterKey],
        );
    }

    public function testSharesCandidateCheckBudgetAcrossBatch(): void
    {
        $candidateHeavyValue = self::createCandidateHeavyValue(200);
        $records = [];

        for ($index = 0; $index < 8; ++$index) {
            $records[] = $this->createRecord([
                'value' => $candidateHeavyValue,
            ]);
        }

        $output = $this->createFormatter()->formatBatch($records);

        $decoded = json_decode(
            $output,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);
        self::assertCount(8, $decoded);

        for ($index = 0; $index < 7; ++$index) {
            $record = $decoded[$index] ?? null;

            self::assertIsArray($record);

            $context = $record['context'] ?? null;

            self::assertIsArray($context);

            self::assertSame(
                $candidateHeavyValue,
                $context['value'] ?? null,
            );
        }

        $record = $decoded[7] ?? null;

        self::assertIsArray($record);

        $context = $record['context'] ?? null;

        self::assertIsArray($context);

        self::assertSame(
            str_repeat(
                '█',
                strlen($candidateHeavyValue),
            ),
            $context['value'] ?? null,
        );
    }

    public function testStartsFreshCandidateCheckBudgetForSeparateFormatCalls(): void
    {
        $formatter = $this->createFormatter();
        $candidateHeavyValue = self::createCandidateHeavyValue(200);

        $formatter->format(
            $this->createRecord(
                array_fill(
                    0,
                    8,
                    $candidateHeavyValue,
                ),
            ),
        );

        $freshValue = self::createCandidateHeavyValue(20);

        $output = $formatter->format(
            $this->createRecord([
                'value' => $freshValue,
            ]),
        );

        $decodedContext = $this->decodeContext($output);

        self::assertSame(
            $freshValue,
            $decodedContext['value'],
        );
    }

    private function createFormatter(): SensitiveJsonFormatter
    {
        return new SensitiveJsonFormatter(
            new StructuredDataMasker(
                new SensitiveDataMasker(),
            ),
        );
    }

    /**
     * @param array<int|string, mixed> $context
     */
    private function createRecord(array $context): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(
                '2026-08-19T00:00:00+00:00',
            ),
            channel: 'app',
            level: Level::Info,
            message: 'Payment-card budget test',
            context: $context,
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeContext(string $output): array
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

        $context = $decoded['context'] ?? null;

        if (!is_array($context)) {
            self::fail(
                'Expected the decoded record to contain a context array.',
            );
        }

        return $context;
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
