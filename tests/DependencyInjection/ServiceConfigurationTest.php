<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests\DependencyInjection;

use Alkin\MaskedBundle\Detection\ExactValueDetector;
use Alkin\MaskedBundle\Detection\PaymentCardDetector;
use Alkin\MaskedBundle\Detection\SensitiveDataMatchNormalizer;
use Alkin\MaskedBundle\MaskedBundle;
use Alkin\MaskedBundle\Monolog\SensitiveDataProcessor;
use Alkin\MaskedBundle\RangeRedactor;
use Alkin\MaskedBundle\Redactor;
use Alkin\MaskedBundle\SensitiveDataMasker;
use Alkin\MaskedBundle\StructuredDataMasker;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversNothing]
final class ServiceConfigurationTest extends TestCase
{
	public function testRegistersCompleteMaskingServiceGraph(): void
	{
		$container = new ContainerBuilder();

		$extension = new MaskedBundle()->getContainerExtension();

		self::assertNotNull($extension);

		$extension->load([], $container);

		self::assertSame(
			[
				'$maskCharacter' => '█',
			],
			$container->getDefinition(Redactor::class)->getArguments(),
		);
		self::assertSame(
			[],
			$container
				->getDefinition(SensitiveDataMatchNormalizer::class)
				->getArguments(),
		);
		self::assertEquals(
			[
				new Reference(SensitiveDataMatchNormalizer::class),
			],
			$container
				->getDefinition(PaymentCardDetector::class)
				->getArguments(),
		);
		self::assertEquals(
			[
				new Reference(Redactor::class),
				new Reference(SensitiveDataMatchNormalizer::class),
			],
			$container
				->getDefinition(RangeRedactor::class)
				->getArguments(),
		);
		self::assertEquals(
			[
				new Reference(PaymentCardDetector::class),
				new Reference(ExactValueDetector::class),
				new Reference(RangeRedactor::class),
			],
			$container
				->getDefinition(SensitiveDataMasker::class)
				->getArguments(),
		);
		self::assertEquals(
			[
				new Reference(SensitiveDataMatchNormalizer::class),
			],
			$container
				->getDefinition(ExactValueDetector::class)
				->getArguments(),
		);
		self::assertEquals(
			[
				new Reference(SensitiveDataMasker::class),
			],
			$container
				->getDefinition(StructuredDataMasker::class)
				->getArguments(),
		);

		self::assertTrue(
			$container->hasDefinition(SensitiveDataProcessor::class),
		);
		self::assertEquals(
			[
				new Reference(SensitiveDataMasker::class),
				new Reference(StructuredDataMasker::class),
			],
			$container
				->getDefinition(SensitiveDataProcessor::class)
				->getArguments(),
		);
		self::assertSame(
			[
				[

				],
			],
			$container
				->getDefinition(SensitiveDataProcessor::class)
				->getTag('monolog.processor'),
		);

		$container
			->getDefinition(SensitiveDataMasker::class)
			->setPublic(true);
		$container
			->getDefinition(SensitiveDataProcessor::class)
			->setPublic(true);

		$container->compile();

		/** @var SensitiveDataMasker $masker */
		$masker = $container->get(SensitiveDataMasker::class);

		self::assertSame(
			'Card: ' . str_repeat('█', 16),
			$masker->mask('Card: 4111111111111111'),
		);

		/** @var SensitiveDataProcessor $processor */
		$processor = $container->get(SensitiveDataProcessor::class);

		$record = $processor(
			new LogRecord(
				datetime: new DateTimeImmutable(
					'2026-08-18T00:00:00+00:00',
				),
				channel: 'app',
				level: Level::Info,
				message: 'Card 4111111111111111',
				context: [
					'card' => '5555555555554444',
				],
			),
		);

		self::assertSame(
			'Card ' . str_repeat('█', 16),
			$record->message,
		);
		self::assertSame(
			str_repeat('█', 16),
			$record->context['card'],
		);
	}

	public function testConfiguresCustomMaskCharacter(): void
	{
		$container = new ContainerBuilder();

		$extension = new MaskedBundle()->getContainerExtension();

		self::assertNotNull($extension);

		$extension->load(
			[
				[
					'mask_character' => '*',
				],
			],
			$container,
		);

		self::assertSame(
			[
				'$maskCharacter' => '*',
			],
			$container->getDefinition(Redactor::class)->getArguments(),
		);

		$container
			->getDefinition(SensitiveDataMasker::class)
			->setPublic(true);
		$container
			->getDefinition(SensitiveDataProcessor::class)
			->setPublic(true);

		$container->compile();

		/** @var SensitiveDataMasker $masker */
		$masker = $container->get(SensitiveDataMasker::class);

		self::assertSame(
			'Card: ' . str_repeat('*', 16),
			$masker->mask('Card: 4111111111111111'),
		);

		/** @var SensitiveDataProcessor $processor */
		$processor = $container->get(SensitiveDataProcessor::class);

		$record = $processor(
			new LogRecord(
				datetime: new DateTimeImmutable(
					'2026-08-18T00:00:00+00:00',
				),
				channel: 'app',
				level: Level::Info,
				message: 'Card 4111111111111111',
				context: [
					'card' => '5555555555554444',
				],
			),
		);

		self::assertSame(
			'Card ' . str_repeat('*', 16),
			$record->message,
		);
		self::assertSame(
			str_repeat('*', 16),
			$record->context['card'],
		);
	}
}
