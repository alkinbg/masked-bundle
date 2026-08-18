<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Integration;

use Masked\Bundle\MaskedBundle;
use Masked\Bundle\Monolog\SensitiveJsonFormatter;
use Masked\Bundle\Monolog\SensitiveLineFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversNothing]
final class MonologBundleIntegrationTest extends TestCase
{
    public function testAutomaticallyRegistersProcessorWithMonolog(): void
    {
        $container = $this->createContainer();

        $logger = $container->get('monolog.logger');

        self::assertInstanceOf(
            Logger::class,
            $logger,
        );

        $logger->info(
            'Processing {card}',
            [
                'card' => '4111111111111111',
            ],
        );

        $handler = $this->getTestHandler($container);

        $records = $handler->getRecords();

        self::assertCount(1, $records);

        $record = $records[0];

        self::assertSame(
            'Processing '.str_repeat('█', 16),
            $record->message,
        );

        self::assertSame(
            str_repeat('█', 16),
            $record->context['card'],
        );
    }

    public function testUsesSensitiveLineFormatterThroughMonologBundle(): void
    {
        $container = $this->createContainer(
            SensitiveLineFormatter::class,
        );

        $handler = $this->getTestHandler($container);

        self::assertInstanceOf(
            SensitiveLineFormatter::class,
            $handler->getFormatter(),
        );

        $logger = $container->get('monolog.logger');

        self::assertInstanceOf(
            Logger::class,
            $logger,
        );

        $exception = new \RuntimeException(
            'Payment failed for 4111111111111111',
        );

        $logger->error(
            'Payment failure',
            [
                'exception' => $exception,
            ],
        );

        $records = $handler->getRecords();

        self::assertCount(1, $records);

        $formatted = $records[0]->formatted;

        self::assertIsString($formatted);

        self::assertStringNotContainsString(
            '4111111111111111',
            $formatted,
        );

        self::assertStringContainsString(
            str_repeat('█', 16),
            $formatted,
        );

        self::assertSame(
            'Payment failed for 4111111111111111',
            $exception->getMessage(),
        );
    }

    public function testUsesSensitiveJsonFormatterThroughMonologBundle(): void
    {
        $container = $this->createContainer(
            SensitiveJsonFormatter::class,
        );

        $handler = $this->getTestHandler($container);

        self::assertInstanceOf(
            SensitiveJsonFormatter::class,
            $handler->getFormatter(),
        );

        $logger = $container->get('monolog.logger');

        self::assertInstanceOf(
            Logger::class,
            $logger,
        );

        $exception = new \RuntimeException(
            'Payment failed for 4111111111111111',
        );

        $logger->error(
            'Payment failure',
            [
                'exception' => $exception,
            ],
        );

        $records = $handler->getRecords();

        self::assertCount(1, $records);

        $formatted = $records[0]->formatted;

        self::assertIsString($formatted);

        self::assertStringNotContainsString(
            '4111111111111111',
            $formatted,
        );

        self::assertStringContainsString(
            str_repeat('█', 16),
            $formatted,
        );

        $decoded = json_decode(
            $formatted,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);

        self::assertSame(
            'Payment failed for 4111111111111111',
            $exception->getMessage(),
        );
    }

    private function createContainer(
        ?string $formatter = null,
    ): ContainerBuilder {
        $container = new ContainerBuilder();

        new MonologBundle()->build($container);

        /**
         * @var array<string, mixed> $handlerConfig
         */
        $handlerConfig = [
            'type' => 'test',
        ];

        if (null !== $formatter) {
            $handlerConfig['formatter'] = $formatter;
        }

        new MonologExtension()->load(
            [
                [
                    'handlers' => [
                        'main' => $handlerConfig,
                    ],
                ],
            ],
            $container,
        );

        $extension = new MaskedBundle()->getContainerExtension();

        self::assertNotNull($extension);

        $extension->load([], $container);

        $container
            ->getDefinition('monolog.logger')
            ->setPublic(true);

        $container
            ->getDefinition('monolog.handler.main')
            ->setPublic(true);

        $container->compile();

        return $container;
    }

    private function getTestHandler(
        ContainerBuilder $container,
    ): TestHandler {
        $handler = $container->get(
            'monolog.handler.main',
        );

        self::assertInstanceOf(
            TestHandler::class,
            $handler,
        );

        return $handler;
    }
}
