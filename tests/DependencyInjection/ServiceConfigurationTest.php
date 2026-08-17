<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests\DependencyInjection;

use Alkin\MaskedBundle\Detection\PaymentCardDetector;
use Alkin\MaskedBundle\Detection\SensitiveDataMatchNormalizer;
use Alkin\MaskedBundle\MaskedBundle;
use Alkin\MaskedBundle\RangeRedactor;
use Alkin\MaskedBundle\Redactor;
use Alkin\MaskedBundle\SensitiveDataMasker;
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
				new Reference(RangeRedactor::class),
			],
			$container
				->getDefinition(SensitiveDataMasker::class)
				->getArguments(),
		);

		$container
			->getDefinition(SensitiveDataMasker::class)
			->setPublic(true);

		$container->compile();

		/** @var SensitiveDataMasker $masker */
		$masker = $container->get(SensitiveDataMasker::class);

		self::assertSame(
			'Card: ' . str_repeat('█', 16),
			$masker->mask('Card: 4111111111111111'),
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

		$container->compile();

		/** @var SensitiveDataMasker $masker */
		$masker = $container->get(SensitiveDataMasker::class);

		self::assertSame(
			'Card: ' . str_repeat('*', 16),
			$masker->mask('Card: 4111111111111111'),
		);
	}
}
