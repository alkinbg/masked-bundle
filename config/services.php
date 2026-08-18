<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Masked\Bundle\Detection\ExactValueDetector;
use Masked\Bundle\Detection\PaymentCardDetector;
use Masked\Bundle\Detection\SensitiveDataMatchNormalizer;
use Masked\Bundle\Logging\SensitiveLogger;
use Masked\Bundle\Monolog\SensitiveDataProcessor;
use Masked\Bundle\Monolog\SensitiveJsonFormatter;
use Masked\Bundle\Monolog\SensitiveLineFormatter;
use Masked\Bundle\RangeRedactor;
use Masked\Bundle\Redactor;
use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(Redactor::class);

    $services->set(SensitiveDataMatchNormalizer::class);

    $services->set(PaymentCardDetector::class)
        ->args([
            service(SensitiveDataMatchNormalizer::class),
        ]);

    $services->set(ExactValueDetector::class)
        ->args([
            service(SensitiveDataMatchNormalizer::class),
        ]);

    $services->set(RangeRedactor::class)
        ->args([
            service(Redactor::class),
            service(SensitiveDataMatchNormalizer::class),
        ]);

    $services->set(SensitiveDataMasker::class)
        ->args([
            service(PaymentCardDetector::class),
            service(ExactValueDetector::class),
            service(RangeRedactor::class),
        ]);

    $services->set(StructuredDataMasker::class)
        ->args([
            service(SensitiveDataMasker::class),
        ]);

    $services->set(SensitiveLogger::class)
        ->args([
            service(SensitiveDataMasker::class),
            service(StructuredDataMasker::class),
        ]);

    if (!class_exists(\Monolog\LogRecord::class)) {
        return;
    }

    $services->set(SensitiveLineFormatter::class)
        ->args([
            service(SensitiveDataMasker::class),
        ]);

    $services->set(SensitiveJsonFormatter::class)
        ->args([
            service(StructuredDataMasker::class),
        ]);

    $services->set(SensitiveDataProcessor::class)
        ->args([
            service(SensitiveDataMasker::class),
            service(StructuredDataMasker::class),
        ])
        ->tag('monolog.processor');
};
