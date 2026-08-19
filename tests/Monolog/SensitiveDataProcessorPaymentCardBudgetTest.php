<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Monolog;

use Masked\Bundle\Monolog\SensitiveDataProcessor;
use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveDataProcessor::class)]
final class SensitiveDataProcessorPaymentCardBudgetTest extends TestCase
{
    public function testSharesCandidateCheckBudgetAcrossCompleteRecord(): void
    {
        /*
         * 1,443 one-digit groups consume 9,996 candidate validations.
         *
         * The context value then attempts additional candidate checks and
         * exhausts the shared 10,000-check budget. The processor must remain
         * fail-closed when it continues with extra.
         *
         * If message, context and extra receive separate detector contexts,
         * the context value and extra remain unchanged and this regression
         * fails.
         */
        $message = self::createCandidateHeavyValue(1443);

        $contextValue =
            self::createCandidateHeavyValue(20);

        $extraValue = 'later-extra';

        $processor = $this->createProcessor();

        $processed = $processor(
            new LogRecord(
                datetime: new \DateTimeImmutable(
                    '2026-08-19T00:00:00+00:00',
                ),
                channel: 'app',
                level: Level::Warning,
                message: $message,
                context: [
                    'value' => $contextValue,
                ],
                extra: [
                    'value' => $extraValue,
                ],
            ),
        );

        /*
         * The message stays unchanged because it stops just below the shared
         * candidate-validation limit.
         */
        self::assertSame(
            $message,
            $processed->message,
        );

        /*
         * Context exhausts the remaining detector budget and therefore the
         * complete current value is redacted.
         */
        self::assertSame(
            str_repeat(
                '█',
                strlen($contextValue),
            ),
            $processed->context['value'],
        );

        /*
         * Fail-closed state must continue into extra. The array key itself is
         * processed after the budget has been exhausted, so both key and value
         * are redacted.
         */
        self::assertArrayNotHasKey(
            'value',
            $processed->extra,
        );

        $maskedExtraKey = str_repeat(
            '█',
            strlen('value'),
        );

        self::assertArrayHasKey(
            $maskedExtraKey,
            $processed->extra,
        );

        self::assertSame(
            str_repeat(
                '█',
                strlen($extraValue),
            ),
            $processed->extra[$maskedExtraKey],
        );
    }

    public function testStartsFreshCandidateCheckBudgetForEachRecord(): void
    {
        $processor = $this->createProcessor();

        $processor(
            new LogRecord(
                datetime: new \DateTimeImmutable(
                    '2026-08-19T00:00:00+00:00',
                ),
                channel: 'app',
                level: Level::Warning,
                message: self::createCandidateHeavyValue(
                    1443,
                ),
                context: [
                    'value' => self::createCandidateHeavyValue(
                        20,
                    ),
                ],
            ),
        );

        $freshValue =
            self::createCandidateHeavyValue(20);

        $processed = $processor(
            new LogRecord(
                datetime: new \DateTimeImmutable(
                    '2026-08-19T00:01:00+00:00',
                ),
                channel: 'app',
                level: Level::Warning,
                message: 'Fresh record',
                context: [
                    'value' => $freshValue,
                ],
            ),
        );

        self::assertSame(
            $freshValue,
            $processed->context['value'],
        );
    }

    private function createProcessor(): SensitiveDataProcessor
    {
        $sensitiveDataMasker =
            new SensitiveDataMasker();

        return new SensitiveDataProcessor(
            sensitiveDataMasker: $sensitiveDataMasker,
            structuredDataMasker: new StructuredDataMasker(
                $sensitiveDataMasker,
            ),
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
