<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\DependencyInjection;

use Masked\Bundle\Detection\ExactValueDetectionContext;
use Masked\Bundle\MaskedBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversNothing]
final class ExactValueDetectionContextServiceTest extends TestCase
{
    public function testExactValueDetectionContextIsNotRegisteredAsService(): void
    {
        $container = new ContainerBuilder();

        $extension = new MaskedBundle()->getContainerExtension();

        self::assertNotNull($extension);

        $extension->load([], $container);

        self::assertFalse(
            $container->hasDefinition(
                ExactValueDetectionContext::class,
            ),
        );

        self::assertFalse(
            $container->hasAlias(
                ExactValueDetectionContext::class,
            ),
        );

        self::assertFalse(
            $container->has(
                ExactValueDetectionContext::class,
            ),
        );
    }
}
