<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\DependencyInjection;

use Masked\Bundle\Detection\ExactValueDetector;
use Masked\Bundle\Detection\PaymentCardDetector;
use Masked\Bundle\Detection\SensitiveDataMatchNormalizer;
use Masked\Bundle\MaskedBundle;
use Masked\Bundle\Monolog\SensitiveDataProcessor;
use Masked\Bundle\RangeRedactor;
use Masked\Bundle\Redactor;
use Masked\Bundle\SensitiveDataMasker;
use Masked\Bundle\StructuredDataMasker;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversNothing]
final class ServiceConfigurationTest extends TestCase
{
    public function testRegistersCompleteMaskingServiceGraph(): void
    {
        $container = new ContainerBuilder();

        $extension = new MaskedBundle()->getContainerExtension();

        self::assertNotNull($extension);

        $extension->load([], $container);

        self::assertSame(
            Redactor::class,
            $container
                ->getDefinition('.masked.redactor')
                ->getClass(),
        );

        self::assertSame(
            [
                '$maskCharacter' => '█',
            ],
            $container
                ->getDefinition('.masked.redactor')
                ->getArguments(),
        );

        self::assertSame(
            SensitiveDataMatchNormalizer::class,
            $container
                ->getDefinition('.masked.sensitive_data_match_normalizer')
                ->getClass(),
        );

        self::assertSame(
            [],
            $container
                ->getDefinition('.masked.sensitive_data_match_normalizer')
                ->getArguments(),
        );

        self::assertSame(
            PaymentCardDetector::class,
            $container
                ->getDefinition('.masked.payment_card_detector')
                ->getClass(),
        );

        self::assertEquals(
            [
                new Reference(
                    '.masked.sensitive_data_match_normalizer',
                ),
            ],
            $container
                ->getDefinition('.masked.payment_card_detector')
                ->getArguments(),
        );

        self::assertSame(
            ExactValueDetector::class,
            $container
                ->getDefinition('.masked.exact_value_detector')
                ->getClass(),
        );

        self::assertEquals(
            [
                new Reference(
                    '.masked.sensitive_data_match_normalizer',
                ),
            ],
            $container
                ->getDefinition('.masked.exact_value_detector')
                ->getArguments(),
        );

        self::assertSame(
            RangeRedactor::class,
            $container
                ->getDefinition('.masked.range_redactor')
                ->getClass(),
        );

        self::assertEquals(
            [
                new Reference('.masked.redactor'),
                new Reference(
                    '.masked.sensitive_data_match_normalizer',
                ),
            ],
            $container
                ->getDefinition('.masked.range_redactor')
                ->getArguments(),
        );

        self::assertSame(
            SensitiveDataMasker::class,
            $container
                ->getDefinition('.masked.sensitive_data_masker')
                ->getClass(),
        );

        self::assertEquals(
            [
                new Reference('.masked.payment_card_detector'),
                new Reference('.masked.exact_value_detector'),
                new Reference('.masked.range_redactor'),
            ],
            $container
                ->getDefinition('.masked.sensitive_data_masker')
                ->getArguments(),
        );

        self::assertTrue(
            $container->hasAlias(SensitiveDataMasker::class),
        );

        self::assertSame(
            '.masked.sensitive_data_masker',
            (string) $container->getAlias(
                SensitiveDataMasker::class,
            ),
        );

        self::assertSame(
            StructuredDataMasker::class,
            $container
                ->getDefinition('.masked.structured_data_masker')
                ->getClass(),
        );

        self::assertEquals(
            [
                new Reference('.masked.sensitive_data_masker'),
            ],
            $container
                ->getDefinition('.masked.structured_data_masker')
                ->getArguments(),
        );

        self::assertTrue(
            $container->hasAlias(StructuredDataMasker::class),
        );

        self::assertSame(
            '.masked.structured_data_masker',
            (string) $container->getAlias(
                StructuredDataMasker::class,
            ),
        );

        self::assertTrue(
            $container->hasDefinition(
                '.masked.monolog.processor',
            ),
        );

        self::assertSame(
            SensitiveDataProcessor::class,
            $container
                ->getDefinition('.masked.monolog.processor')
                ->getClass(),
        );

        self::assertEquals(
            [
                new Reference('.masked.sensitive_data_masker'),
                new Reference('.masked.structured_data_masker'),
            ],
            $container
                ->getDefinition('.masked.monolog.processor')
                ->getArguments(),
        );

        self::assertSame(
            [
                [],
            ],
            $container
                ->getDefinition('.masked.monolog.processor')
                ->getTag('monolog.processor'),
        );

        self::assertTrue(
            $container->hasAlias(SensitiveDataProcessor::class),
        );

        self::assertSame(
            '.masked.monolog.processor',
            (string) $container->getAlias(
                SensitiveDataProcessor::class,
            ),
        );

        $container
            ->getAlias(SensitiveDataMasker::class)
            ->setPublic(true);

        $container
            ->getAlias(SensitiveDataProcessor::class)
            ->setPublic(true);

        $container->compile();

        $masker = $container->get(
            SensitiveDataMasker::class,
        );

        self::assertInstanceOf(
            SensitiveDataMasker::class,
            $masker,
        );

        self::assertSame(
            'Card: '.str_repeat('█', 16),
            $masker->mask(
                'Card: 4111111111111111',
            ),
        );

        $processor = $container->get(
            SensitiveDataProcessor::class,
        );

        self::assertInstanceOf(
            SensitiveDataProcessor::class,
            $processor,
        );

        $record = $processor(
            new LogRecord(
                datetime: new \DateTimeImmutable(
                    '2026-08-18T00:00:00+00:00',
                ),
                channel: 'app',
                level: Level::Info,
                message: 'Card 4111111111111111',
                context: [
                    'card' => '5555555555554444',
                ],
            ),
        );

        self::assertSame(
            'Card '.str_repeat('█', 16),
            $record->message,
        );

        self::assertSame(
            str_repeat('█', 16),
            $record->context['card'],
        );
    }

    public function testConfiguresCustomMaskCharacter(): void
    {
        $container = new ContainerBuilder();

        $extension = new MaskedBundle()->getContainerExtension();

        self::assertNotNull($extension);

        $extension->load(
            [
                [
                    'mask_character' => '*',
                ],
            ],
            $container,
        );

        self::assertSame(
            [
                '$maskCharacter' => '*',
            ],
            $container
                ->getDefinition('.masked.redactor')
                ->getArguments(),
        );

        $container
            ->getAlias(SensitiveDataMasker::class)
            ->setPublic(true);

        $container
            ->getAlias(SensitiveDataProcessor::class)
            ->setPublic(true);

        $container->compile();

        $masker = $container->get(
            SensitiveDataMasker::class,
        );

        self::assertInstanceOf(
            SensitiveDataMasker::class,
            $masker,
        );

        self::assertSame(
            'Card: '.str_repeat('*', 16),
            $masker->mask(
                'Card: 4111111111111111',
            ),
        );

        $processor = $container->get(
            SensitiveDataProcessor::class,
        );

        self::assertInstanceOf(
            SensitiveDataProcessor::class,
            $processor,
        );

        $record = $processor(
            new LogRecord(
                datetime: new \DateTimeImmutable(
                    '2026-08-18T00:00:00+00:00',
                ),
                channel: 'app',
                level: Level::Info,
                message: 'Card 4111111111111111',
                context: [
                    'card' => '5555555555554444',
                ],
            ),
        );

        self::assertSame(
            'Card '.str_repeat('*', 16),
            $record->message,
        );

        self::assertSame(
            str_repeat('*', 16),
            $record->context['card'],
        );
    }
}
