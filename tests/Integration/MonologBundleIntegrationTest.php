<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests\Integration;

use Alkin\MaskedBundle\MaskedBundle;
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
		$container = new ContainerBuilder();

		new MonologBundle()->build($container);

		new MonologExtension()->load(
			[
				[
					'handlers' => [
						'main' => [
							'type' => 'test',
						],
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

		$handler = $container->get(
			'monolog.handler.main',
		);

		self::assertInstanceOf(
			TestHandler::class,
			$handler,
		);

		$records = $handler->getRecords();

		self::assertCount(1, $records);

		$record = $records[0];

		self::assertSame(
			'Processing ' . str_repeat('█', 16),
			$record->message,
		);

		self::assertSame(
			str_repeat('█', 16),
			$record->context['card'],
		);
	}
}
