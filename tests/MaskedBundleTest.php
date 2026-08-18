<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests;

use Masked\Bundle\MaskedBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(MaskedBundle::class)]
final class MaskedBundleTest extends TestCase
{
    public function testAcceptsSingleUtf8MaskCharacter(): void
    {
        $container = $this->loadConfiguration(
            [
                'mask_character' => '🔒',
            ],
        );

        self::assertSame(
            [
                '$maskCharacter' => '🔒',
            ],
            $container
                ->getDefinition('.masked.redactor')
                ->getArguments(),
        );
    }

    public function testRejectsEmptyMaskCharacter(): void
    {
        $this->expectException(
            InvalidConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'exactly one valid UTF-8 character',
        );

        $this->loadConfiguration(
            [
                'mask_character' => '',
            ],
        );
    }

    public function testRejectsMultipleMaskCharacters(): void
    {
        $this->expectException(
            InvalidConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'exactly one valid UTF-8 character',
        );

        $this->loadConfiguration(
            [
                'mask_character' => '**',
            ],
        );
    }

    public function testRejectsInvalidUtf8MaskCharacter(): void
    {
        $this->expectException(
            InvalidConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'exactly one valid UTF-8 character',
        );

        $this->loadConfiguration(
            [
                'mask_character' => "\xFF",
            ],
        );
    }

    #[DataProvider('unsafeMaskCharacterProvider')]
    public function testRejectsUnsafeMaskCharacter(
        string $maskCharacter,
    ): void {
        $this->expectException(
            InvalidConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'letter, number, punctuation, or symbol categories',
        );

        $this->loadConfiguration(
            [
                'mask_character' => $maskCharacter,
            ],
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loadConfiguration(
        array $config,
    ): ContainerBuilder {
        $container = new ContainerBuilder();

        $extension = new MaskedBundle()
            ->getContainerExtension();

        self::assertNotNull($extension);

        $extension->load(
            [$config],
            $container,
        );

        return $container;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeMaskCharacterProvider(): iterable
    {
        yield 'line feed' => [
            "\n",
        ];

        yield 'carriage return' => [
            "\r",
        ];

        yield 'horizontal tab' => [
            "\t",
        ];

        yield 'null byte' => [
            "\0",
        ];

        yield 'escape' => [
            "\x1B",
        ];

        yield 'space' => [
            ' ',
        ];

        yield 'non-breaking space' => [
            "\u{00A0}",
        ];

        yield 'zero-width space' => [
            "\u{200B}",
        ];

        yield 'line separator' => [
            "\u{2028}",
        ];

        yield 'paragraph separator' => [
            "\u{2029}",
        ];

        yield 'combining acute accent' => [
            "\u{0301}",
        ];
    }
}
