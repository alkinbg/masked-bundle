<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Alkin\MaskedBundle\Detection\ExactValueDetector;
use Alkin\MaskedBundle\Detection\PaymentCardDetector;
use Alkin\MaskedBundle\Detection\SensitiveDataMatchNormalizer;
use Alkin\MaskedBundle\Logging\SensitiveLogger;
use Alkin\MaskedBundle\Monolog\SensitiveDataProcessor;
use Alkin\MaskedBundle\Monolog\SensitiveJsonFormatter;
use Alkin\MaskedBundle\Monolog\SensitiveLineFormatter;
use Alkin\MaskedBundle\RangeRedactor;
use Alkin\MaskedBundle\Redactor;
use Alkin\MaskedBundle\SensitiveDataMasker;
use Alkin\MaskedBundle\StructuredDataMasker;

return static function (ContainerConfigurator $container): void
{
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

	if (!class_exists(\Monolog\LogRecord::class))
	{
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
