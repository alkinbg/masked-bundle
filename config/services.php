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

    $services->set(
        '.masked.redactor',
        Redactor::class,
    );

    $services->set(
        '.masked.sensitive_data_match_normalizer',
        SensitiveDataMatchNormalizer::class,
    );

    $services->set(
        '.masked.payment_card_detector',
        PaymentCardDetector::class,
    )
        ->args([
            service('.masked.sensitive_data_match_normalizer'),
        ]);

    $services->set(
        '.masked.exact_value_detector',
        ExactValueDetector::class,
    )
        ->args([
            service('.masked.sensitive_data_match_normalizer'),
        ]);

    $services->set(
        '.masked.range_redactor',
        RangeRedactor::class,
    )
        ->args([
            service('.masked.redactor'),
            service('.masked.sensitive_data_match_normalizer'),
        ]);

    $services->set(
        '.masked.sensitive_data_masker',
        SensitiveDataMasker::class,
    )
        ->args([
            service('.masked.payment_card_detector'),
            service('.masked.exact_value_detector'),
            service('.masked.range_redactor'),
        ]);

    $services->alias(
        SensitiveDataMasker::class,
        '.masked.sensitive_data_masker',
    );

    $services->set(
        '.masked.structured_data_masker',
        StructuredDataMasker::class,
    )
        ->args([
            service('.masked.sensitive_data_masker'),
        ]);

    $services->alias(
        StructuredDataMasker::class,
        '.masked.structured_data_masker',
    );

    $services->set(
        '.masked.sensitive_logger',
        SensitiveLogger::class,
    )
        ->args([
            service('.masked.sensitive_data_masker'),
            service('.masked.structured_data_masker'),
        ]);

    $services->alias(
        SensitiveLogger::class,
        '.masked.sensitive_logger',
    );

    if (
        !class_exists(\Monolog\Logger::class)
        || 3 !== \Monolog\Logger::API
    ) {
        return;
    }

    $services->set(
        '.masked.monolog.line_formatter',
        SensitiveLineFormatter::class,
    )
        ->args([
            service('.masked.sensitive_data_masker'),
        ]);

    $services->alias(
        SensitiveLineFormatter::class,
        '.masked.monolog.line_formatter',
    );

    $services->set(
        '.masked.monolog.json_formatter',
        SensitiveJsonFormatter::class,
    )
        ->args([
            service('.masked.structured_data_masker'),
        ]);

    $services->alias(
        SensitiveJsonFormatter::class,
        '.masked.monolog.json_formatter',
    );

    $services->set(
        '.masked.monolog.processor',
        SensitiveDataProcessor::class,
    )
        ->args([
            service('.masked.sensitive_data_masker'),
            service('.masked.structured_data_masker'),
        ])
        ->tag('monolog.processor');

    $services->alias(
        SensitiveDataProcessor::class,
        '.masked.monolog.processor',
    );
};
