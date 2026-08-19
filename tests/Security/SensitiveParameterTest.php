<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Security;

use Masked\Bundle\Detection\ExactValueDetectionContext;
use Masked\Bundle\Detection\ExactValueDetector;
use Masked\Bundle\Detection\PaymentCardDetector;
use Masked\Bundle\Logging\SensitiveLogger;
use Masked\Bundle\Monolog\SensitiveDataProcessor;
use Masked\Bundle\Monolog\SensitiveJsonFormatter;
use Masked\Bundle\Monolog\SensitiveLineFormatter;
use Masked\Bundle\RangeRedactor;
use Masked\Bundle\Redactor;
use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SensitiveParameterTest extends TestCase
{
    #[DataProvider('sensitiveParameterProvider')]
    public function testSensitiveInputParameterIsMarked(
        string $class,
        string $method,
        string $parameterName,
    ): void {
        $parameter = new \ReflectionParameter(
            [$class, $method],
            $parameterName,
        );

        self::assertCount(
            1,
            $parameter->getAttributes(
                \SensitiveParameter::class,
            ),
            sprintf(
                '%s::%s($%s) must be marked with #[\\SensitiveParameter].',
                $class,
                $method,
                $parameterName,
            ),
        );
    }

    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function sensitiveParameterProvider(): iterable
    {
        yield 'exact context explicit values' => [
            ExactValueDetectionContext::class,
            'create',
            'sensitiveValues',
        ];

        yield 'exact detector value' => [
            ExactValueDetector::class,
            'detect',
            'value',
        ];

        yield 'exact detector explicit values' => [
            ExactValueDetector::class,
            'detect',
            'sensitiveValues',
        ];

        yield 'exact detector shared-context value' => [
            ExactValueDetector::class,
            'detectWithinContext',
            'value',
        ];

        yield 'exact detector context' => [
            ExactValueDetector::class,
            'detectWithinContext',
            'context',
        ];

        yield 'payment card detector input' => [
            PaymentCardDetector::class,
            'detect',
            'value',
        ];

        yield 'payment card sequence input' => [
            PaymentCardDetector::class,
            'detectInSequence',
            'value',
        ];

        yield 'payment card separator input' => [
            PaymentCardDetector::class,
            'supportedSeparatorByteLengthAt',
            'value',
        ];

        yield 'payment card candidate' => [
            PaymentCardDetector::class,
            'isValidPan',
            'pan',
        ];

        yield 'payment card repeated digit input' => [
            PaymentCardDetector::class,
            'consistsOfSingleRepeatedDigit',
            'pan',
        ];

        yield 'payment card Luhn input' => [
            PaymentCardDetector::class,
            'passesLuhn',
            'pan',
        ];

        yield 'range redactor value' => [
            RangeRedactor::class,
            'redact',
            'value',
        ];

        yield 'redactor value' => [
            Redactor::class,
            'redact',
            'value',
        ];

        yield 'masker value' => [
            SensitiveDataMasker::class,
            'mask',
            'value',
        ];

        yield 'masker explicit values' => [
            SensitiveDataMasker::class,
            'mask',
            'sensitiveValues',
        ];

        yield 'masker shared-context value' => [
            SensitiveDataMasker::class,
            'maskWithinContext',
            'value',
        ];

        yield 'masker exact context' => [
            SensitiveDataMasker::class,
            'maskWithinContext',
            'exactValueDetectionContext',
        ];

        yield 'structured masker root value' => [
            StructuredDataMasker::class,
            'mask',
            'value',
        ];

        yield 'structured masker explicit values' => [
            StructuredDataMasker::class,
            'mask',
            'sensitiveValues',
        ];

        yield 'structured shared-context root value' => [
            StructuredDataMasker::class,
            'maskWithinContext',
            'value',
        ];

        yield 'structured shared exact context' => [
            StructuredDataMasker::class,
            'maskWithinContext',
            'exactValueDetectionContext',
        ];

        yield 'structured masker recursive value' => [
            StructuredDataMasker::class,
            'maskValue',
            'value',
        ];

        yield 'structured recursive exact context' => [
            StructuredDataMasker::class,
            'maskValue',
            'exactValueDetectionContext',
        ];

        yield 'structured array value' => [
            StructuredDataMasker::class,
            'maskArray',
            'value',
        ];

        yield 'structured array exact context' => [
            StructuredDataMasker::class,
            'maskArray',
            'exactValueDetectionContext',
        ];

        yield 'structured array key' => [
            StructuredDataMasker::class,
            'maskArrayKey',
            'key',
        ];

        yield 'structured array-key exact context' => [
            StructuredDataMasker::class,
            'maskArrayKey',
            'exactValueDetectionContext',
        ];

        yield 'sensitive logger message' => [
            SensitiveLogger::class,
            'log',
            'message',
        ];

        yield 'sensitive logger context' => [
            SensitiveLogger::class,
            'log',
            'context',
        ];

        yield 'sensitive logger explicit values' => [
            SensitiveLogger::class,
            'log',
            'sensitiveValues',
        ];

        yield 'Monolog processor record' => [
            SensitiveDataProcessor::class,
            '__invoke',
            'record',
        ];

        yield 'line formatter record' => [
            SensitiveLineFormatter::class,
            'format',
            'record',
        ];

        yield 'JSON formatter record' => [
            SensitiveJsonFormatter::class,
            'format',
            'record',
        ];

        yield 'JSON formatter batch records' => [
            SensitiveJsonFormatter::class,
            'formatBatch',
            'records',
        ];

        yield 'JSON normalization data' => [
            SensitiveJsonFormatter::class,
            'normalize',
            'data',
        ];

        yield 'normalized JSON tree data' => [
            SensitiveJsonFormatter::class,
            'maskNormalizedTree',
            'data',
        ];

        yield 'JSON representation data' => [
            SensitiveJsonFormatter::class,
            'maskJsonRepresentation',
            'data',
        ];

        yield 'JSON array data' => [
            SensitiveJsonFormatter::class,
            'maskJsonArray',
            'data',
        ];

        yield 'JSON object data' => [
            SensitiveJsonFormatter::class,
            'maskJsonObject',
            'data',
        ];

        yield 'JSON key' => [
            SensitiveJsonFormatter::class,
            'maskJsonKey',
            'key',
        ];

        yield 'JSON scalar data' => [
            SensitiveJsonFormatter::class,
            'maskScalar',
            'data',
        ];
    }
}
