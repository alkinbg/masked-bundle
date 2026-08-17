<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class MaskedBundle extends AbstractBundle
{
	public function configure(DefinitionConfigurator $definition): void
	{
		$definition->rootNode()
			->children()
			->stringNode('mask_character')
			->defaultValue('█')
			->end()
			->end();
	}

	/**
	 * @param array{mask_character: string} $config
	 */
	public function loadExtension(
		array $config,
		ContainerConfigurator $configurator,
		ContainerBuilder $container,
	): void {
		$configurator->import(
			__DIR__ . '/../config/services.php',
		);

		$configurator->services()
			->get(Redactor::class)
			->arg('$maskCharacter', $config['mask_character']);
	}
}
