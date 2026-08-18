<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests\DependencyInjection;

use Alkin\MaskedBundle\MaskedBundle;
use Alkin\MaskedBundle\Monolog\SensitiveJsonFormatter;
use Alkin\MaskedBundle\Monolog\SensitiveLineFormatter;
use Alkin\MaskedBundle\StructuredDataMasker;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversNothing]
final class FormatterServiceConfigurationTest extends TestCase
{
    public function testRegistersFormatterServices(): void
    {
        $container = $this->createContainer();

        self::assertTrue(
            $container->hasDefinition(SensitiveLineFormatter::class),
        );
        self::assertEquals(
            [
                new Reference(StructuredDataMasker::class),
            ],
            $container
                ->getDefinition(SensitiveLineFormatter::class)
                ->getArguments(),
        );

        self::assertTrue(
            $container->hasDefinition(SensitiveJsonFormatter::class),
        );
        self::assertEquals(
            [
                new Reference(StructuredDataMasker::class),
            ],
            $container
                ->getDefinition(SensitiveJsonFormatter::class)
                ->getArguments(),
        );

        $this->makeFormattersPublic($container);

        $container->compile();

        $record = $this->createRecord(
            'Payment failed for 4111111111111111',
        );

        $lineFormatter = $container->get(
            SensitiveLineFormatter::class,
        );

        self::assertInstanceOf(
            SensitiveLineFormatter::class,
            $lineFormatter,
        );

        $lineOutput = $lineFormatter->format($record);

        self::assertStringNotContainsString(
            '4111111111111111',
            $lineOutput,
        );
        self::assertStringContainsString(
            str_repeat('█', 16),
            $lineOutput,
        );

        $jsonFormatter = $container->get(
            SensitiveJsonFormatter::class,
        );

        self::assertInstanceOf(
            SensitiveJsonFormatter::class,
            $jsonFormatter,
        );

        $jsonOutput = $jsonFormatter->format($record);

        self::assertStringNotContainsString(
            '4111111111111111',
            $jsonOutput,
        );
        self::assertStringContainsString(
            str_repeat('█', 16),
            $jsonOutput,
        );

        json_decode(
            $jsonOutput,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function testFormattersUseConfiguredMaskCharacter(): void
    {
        $container = $this->createContainer(
            [
                [
                    'mask_character' => '*',
                ],
            ],
        );

        $this->makeFormattersPublic($container);

        $container->compile();

        $record = $this->createRecord(
            'Payment failed for 4111111111111111',
        );

        $lineFormatter = $container->get(
            SensitiveLineFormatter::class,
        );

        self::assertInstanceOf(
            SensitiveLineFormatter::class,
            $lineFormatter,
        );

        $lineOutput = $lineFormatter->format($record);

        self::assertStringNotContainsString(
            '4111111111111111',
            $lineOutput,
        );
        self::assertStringContainsString(
            str_repeat('*', 16),
            $lineOutput,
        );

        $jsonFormatter = $container->get(
            SensitiveJsonFormatter::class,
        );

        self::assertInstanceOf(
            SensitiveJsonFormatter::class,
            $jsonFormatter,
        );

        $jsonOutput = $jsonFormatter->format($record);

        self::assertStringNotContainsString(
            '4111111111111111',
            $jsonOutput,
        );
        self::assertStringContainsString(
            str_repeat('*', 16),
            $jsonOutput,
        );
    }

    /**
     * @param array<array<mixed>> $config
     */
    private function createContainer(
        array $config = [],
    ): ContainerBuilder {
        $container = new ContainerBuilder();

        $extension = new MaskedBundle()->getContainerExtension();

        self::assertNotNull($extension);

        $extension->load(
            $config,
            $container,
        );

        return $container;
    }

    private function makeFormattersPublic(
        ContainerBuilder $container,
    ): void {
        $container
            ->getDefinition(SensitiveLineFormatter::class)
            ->setPublic(true);

        $container
            ->getDefinition(SensitiveJsonFormatter::class)
            ->setPublic(true);
    }

    private function createRecord(
        string $exceptionMessage,
    ): LogRecord {
        return new LogRecord(
            datetime: new \DateTimeImmutable(
                '2026-08-18T00:00:00+00:00',
            ),
            channel: 'app',
            level: Level::Error,
            message: 'Payment failed',
            context: [
                'exception' => new \RuntimeException(
                    $exceptionMessage,
                ),
            ],
        );
    }
}
