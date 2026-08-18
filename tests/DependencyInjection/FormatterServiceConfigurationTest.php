<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\DependencyInjection;

use Masked\Bundle\MaskedBundle;
use Masked\Bundle\Monolog\SensitiveJsonFormatter;
use Masked\Bundle\Monolog\SensitiveLineFormatter;
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
            $container->hasDefinition('.masked.monolog.line_formatter'),
        );

        self::assertEquals(
            [
                new Reference('.masked.sensitive_data_masker'),
            ],
            $container
                ->getDefinition('.masked.monolog.line_formatter')
                ->getArguments(),
        );

        self::assertTrue(
            $container->hasAlias(SensitiveLineFormatter::class),
        );

        self::assertSame(
            '.masked.monolog.line_formatter',
            (string) $container->getAlias(SensitiveLineFormatter::class),
        );

        self::assertTrue(
            $container->hasDefinition('.masked.monolog.json_formatter'),
        );

        self::assertEquals(
            [
                new Reference('.masked.structured_data_masker'),
            ],
            $container
                ->getDefinition('.masked.monolog.json_formatter')
                ->getArguments(),
        );

        self::assertTrue(
            $container->hasAlias(SensitiveJsonFormatter::class),
        );

        self::assertSame(
            '.masked.monolog.json_formatter',
            (string) $container->getAlias(SensitiveJsonFormatter::class),
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
            ->getAlias(SensitiveLineFormatter::class)
            ->setPublic(true);

        $container
            ->getAlias(SensitiveJsonFormatter::class)
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
