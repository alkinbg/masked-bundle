<?php

declare(strict_types=1);

namespace Masked\Bundle;

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
            ->validate()
            ->ifTrue(
                static fn (string $value): bool => !mb_check_encoding($value, 'UTF-8')
                    || 1 !== mb_strlen($value, 'UTF-8'),
            )
            ->thenInvalid(
                'The "mask_character" option must contain exactly one valid UTF-8 character.',
            )
            ->end()
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
            __DIR__.'/../config/services.php',
        );

        $configurator->services()
            ->get('.masked.redactor')
            ->arg('$maskCharacter', $config['mask_character']);
    }
}
