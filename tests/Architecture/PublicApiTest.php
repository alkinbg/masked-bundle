<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Architecture;

use Masked\Bundle\Detection\ExactValueDetectionContext;
use Masked\Bundle\Detection\ExactValueDetector;
use Masked\Bundle\Detection\PaymentCardDetectionContext;
use Masked\Bundle\Detection\PaymentCardDetector;
use Masked\Bundle\Detection\SensitiveDataDetectionContext;
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
     *
     * @throws \ReflectionException
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
     *
     * @throws \ReflectionException
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
     * @param class-string $class
     *
     * @throws \ReflectionException
     */
    #[DataProvider('internalMethodProvider')]
    public function testInternalOperationMethodIsMarkedInternal(
        string $class,
        string $method,
    ): void {
        $reflection = new \ReflectionMethod(
            $class,
            $method,
        );

        self::assertStringContainsString(
            '@internal',
            $reflection->getDocComment() ?: '',
            sprintf(
                '%s::%s() must remain an internal implementation hook.',
                $class,
                $method,
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

        yield 'exact-value context' => [
            ExactValueDetectionContext::class,
        ];

        yield 'payment-card context' => [
            PaymentCardDetectionContext::class,
        ];

        yield 'sensitive-data detection context' => [
            SensitiveDataDetectionContext::class,
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

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function internalMethodProvider(): iterable
    {
        yield 'exact detector shared context' => [
            ExactValueDetector::class,
            'detectWithinContext',
        ];

        yield 'string masker shared context' => [
            SensitiveDataMasker::class,
            'maskWithinContext',
        ];

        yield 'payment-card detector shared context' => [
            PaymentCardDetector::class,
            'detectWithinContext',
        ];

        yield 'structured masker shared context' => [
            StructuredDataMasker::class,
            'maskWithinContext',
        ];
    }
}
