<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests;

use Alkin\MaskedBundle\MaskedBundle;
use Alkin\MaskedBundle\Redactor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(MaskedBundle::class)]
final class MaskedBundleTest extends TestCase
{
	public function testAcceptsSingleUtf8MaskCharacter(): void
	{
		$container = $this->loadConfiguration(
			[
				'mask_character' => '🔒',
			],
		);

		self::assertSame(
			[
				'$maskCharacter' => '🔒',
			],
			$container
				->getDefinition(Redactor::class)
				->getArguments(),
		);
	}

	public function testRejectsEmptyMaskCharacter(): void
	{
		$this->expectException(
			InvalidConfigurationException::class,
		);

		$this->expectExceptionMessage(
			'exactly one valid UTF-8 character',
		);

		$this->loadConfiguration(
			[
				'mask_character' => '',
			],
		);
	}

	public function testRejectsMultipleMaskCharacters(): void
	{
		$this->expectException(
			InvalidConfigurationException::class,
		);

		$this->expectExceptionMessage(
			'exactly one valid UTF-8 character',
		);

		$this->loadConfiguration(
			[
				'mask_character' => '**',
			],
		);
	}

	public function testRejectsInvalidUtf8MaskCharacter(): void
	{
		$this->expectException(
			InvalidConfigurationException::class,
		);

		$this->expectExceptionMessage(
			'exactly one valid UTF-8 character',
		);

		$this->loadConfiguration(
			[
				'mask_character' => "\xFF",
			],
		);
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function loadConfiguration(
		array $config,
	): ContainerBuilder {
		$container = new ContainerBuilder();

		$extension = new MaskedBundle()
			->getContainerExtension();

		self::assertNotNull($extension);

		$extension->load(
			[$config],
			$container,
		);

		return $container;
	}
}
