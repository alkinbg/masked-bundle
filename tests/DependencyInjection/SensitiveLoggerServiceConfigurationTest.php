<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\DependencyInjection;

use Masked\Bundle\Logging\SensitiveLogger;
use Masked\Bundle\MaskedBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversNothing]
final class SensitiveLoggerServiceConfigurationTest extends TestCase
{
    public function testRegistersSensitiveLogger(): void
    {
        $container = new ContainerBuilder();

        $extension = new MaskedBundle()->getContainerExtension();

        self::assertNotNull($extension);

        $extension->load([], $container);

        self::assertTrue(
            $container->hasDefinition('.masked.sensitive_logger'),
        );

        self::assertEquals(
            [
                new Reference('.masked.sensitive_data_masker'),
                new Reference('.masked.structured_data_masker'),
            ],
            $container
                ->getDefinition('.masked.sensitive_logger')
                ->getArguments(),
        );

        self::assertTrue(
            $container->hasAlias(SensitiveLogger::class),
        );

        self::assertSame(
            '.masked.sensitive_logger',
            (string) $container->getAlias(SensitiveLogger::class),
        );
    }
}
