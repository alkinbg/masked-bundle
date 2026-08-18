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
final class SensitiveJsonFormatterWorkBudgetTest extends TestCase
{
    public function testBoundsAggregateMaskingWorkAcrossNormalizedTree(): void
    {
        $context = [];

        for ($group = 0; $group < 12; ++$group) {
            $context['group_'.$group] = array_fill(
                0,
                1000,
                null,
            );
        }

        $context['group_0'][0] = '4111111111111111';
        $context['late_card'] = '5555555555554444';

        $output = $this->createFormatter()->format(
            $this->createRecord($context),
        );

        self::assertStringContainsString(
            '[maximum JSON masking work budget exceeded]',
            $output,
        );
        self::assertStringNotContainsString(
            '4111111111111111',
            $output,
        );
        self::assertStringNotContainsString(
            '5555555555554444',
            $output,
        );
        self::assertStringContainsString(
            str_repeat('█', 16),
            $output,
        );

        $this->assertValidJson($output);
    }

    public function testSharesMaskingWorkBudgetAcrossBatch(): void
    {
        $records = [];

        for ($recordIndex = 0; $recordIndex < 12; ++$recordIndex) {
            $context = [
                'items' => array_fill(
                    0,
                    1000,
                    null,
                ),
            ];

            if (0 === $recordIndex) {
                $context['items'][0] = '4111111111111111';
            }

            if (11 === $recordIndex) {
                $context['late_card'] = '5555555555554444';
            }

            $records[] = $this->createRecord($context);
        }

        $output = $this->createFormatter()->formatBatch($records);

        self::assertStringContainsString(
            '[maximum JSON masking work budget exceeded]',
            $output,
        );
        self::assertStringNotContainsString(
            '4111111111111111',
            $output,
        );
        self::assertStringNotContainsString(
            '5555555555554444',
            $output,
        );
        self::assertStringContainsString(
            str_repeat('█', 16),
            $output,
        );

        $this->assertValidJson($output);
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
                '2026-08-18T00:00:00+00:00',
            ),
            channel: 'app',
            level: Level::Info,
            message: 'Budget test',
            context: $context,
        );
    }

    private function assertValidJson(string $output): void
    {
        json_decode(
            $output,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->addToAssertionCount(1);
    }
}
