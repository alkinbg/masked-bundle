<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Architecture;

use Masked\Bundle\Detection\ExactValueDetector;
use Masked\Bundle\Detection\PaymentCardDetector;
use Masked\Bundle\Detection\SensitiveDataMatch;
use Masked\Bundle\Detection\SensitiveDataMatchNormalizer;
use Masked\Bundle\Logging\SensitiveLogger;
use Masked\Bundle\MaskedBundle;
use Masked\Bundle\Monolog\SensitiveDataProcessor;
use Masked\Bundle\Monolog\SensitiveJsonFormatter;
use Masked\Bundle\Monolog\SensitiveLineFormatter;
use Masked\Bundle\RangeRedactor;
use Masked\Bundle\Redactor;
use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicApiTest extends TestCase
{
    /**
     * @param class-string $class
     */
    #[DataProvider('internalClassProvider')]
    public function testImplementationClassIsMarkedInternal(
        string $class,
    ): void {
        $reflection = new \ReflectionClass($class);

        self::assertStringContainsString(
            '@internal',
            $reflection->getDocComment() ?: '',
            sprintf(
                '%s must be marked @internal.',
                $class,
            ),
        );
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('publicApiClassProvider')]
    public function testPublicApiClassIsNotMarkedInternal(
        string $class,
    ): void {
        $reflection = new \ReflectionClass($class);

        self::assertStringNotContainsString(
            '@internal',
            $reflection->getDocComment() ?: '',
            sprintf(
                '%s is part of the supported public API.',
                $class,
            ),
        );
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function internalClassProvider(): iterable
    {
        yield 'redactor' => [
            Redactor::class,
        ];

        yield 'range redactor' => [
            RangeRedactor::class,
        ];

        yield 'exact-value detector' => [
            ExactValueDetector::class,
        ];

        yield 'payment-card detector' => [
            PaymentCardDetector::class,
        ];

        yield 'sensitive-data match' => [
            SensitiveDataMatch::class,
        ];

        yield 'match normalizer' => [
            SensitiveDataMatchNormalizer::class,
        ];
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function publicApiClassProvider(): iterable
    {
        yield 'bundle' => [
            MaskedBundle::class,
        ];

        yield 'string masker' => [
            SensitiveDataMasker::class,
        ];

        yield 'structured masker' => [
            StructuredDataMasker::class,
        ];

        yield 'sensitive logger' => [
            SensitiveLogger::class,
        ];

        yield 'Monolog processor' => [
            SensitiveDataProcessor::class,
        ];

        yield 'line formatter' => [
            SensitiveLineFormatter::class,
        ];

        yield 'JSON formatter' => [
            SensitiveJsonFormatter::class,
        ];
    }
}
