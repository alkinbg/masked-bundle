<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class MaskedBundle extends AbstractBundle
{
	/**
	 * @param array<string, mixed> $config
	 */
	public function loadExtension(
		array $config,
		ContainerConfigurator $configurator,
		ContainerBuilder $container,
	): void {
		$configurator->import('../config/services.php');
	}
}
