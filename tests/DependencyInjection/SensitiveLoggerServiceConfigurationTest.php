<?php

declare(strict_types=1);

namespace Masked\Tests\DependencyInjection;

use Masked\Logging\SensitiveLogger;
use Masked\MaskedBundle;
use Masked\SensitiveDataMasker;
use Masked\StructuredDataMasker;
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

        self::assertEquals(
            [
                new Reference(SensitiveDataMasker::class),
                new Reference(StructuredDataMasker::class),
            ],
            $container
                ->getDefinition(SensitiveLogger::class)
                ->getArguments(),
        );
    }
}
