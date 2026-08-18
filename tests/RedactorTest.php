<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests;

use Masked\Bundle\Redactor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Redactor::class)]
final class RedactorTest extends TestCase
{
    public function testRedactsEntireValueByDefault(): void
    {
        $redactor = new Redactor();

        self::assertSame(
            '████████',
            $redactor->redact('password'),
        );
    }

    public function testKeepsRequestedTrailingCharactersVisible(): void
    {
        $redactor = new Redactor();

        self::assertSame(
            '████████████1111',
            $redactor->redact('4111111111111111', 4),
        );
    }

    public function testMasksEntireValueWhenVisiblePartWouldExposeEverything(): void
    {
        $redactor = new Redactor();

        self::assertSame(
            '███',
            $redactor->redact('123', 4),
        );
    }

    public function testSupportsCustomMaskCharacter(): void
    {
        $redactor = new Redactor('*');

        self::assertSame(
            '************1111',
            $redactor->redact('4111111111111111', 4),
        );
    }

    public function testSupportsUnicodeSymbolMaskCharacter(): void
    {
        self::assertSame(
            '🔒🔒🔒🔒',
            new Redactor('🔒')->redact('test'),
        );
    }

    public function testHandlesMultibyteValues(): void
    {
        $redactor = new Redactor();

        self::assertSame(
            '████ла',
            $redactor->redact('парола', 2),
        );
    }

    public function testUsesUtf8IndependentlyOfInternalEncoding(): void
    {
        $originalEncoding = mb_internal_encoding();

        try {
            mb_internal_encoding('ISO-8859-1');

            $redactor = new Redactor();

            self::assertSame(
                '████ла',
                $redactor->redact('парола', 2),
            );
        } finally {
            mb_internal_encoding($originalEncoding);
        }
    }

    public function testFullyMasksInvalidUtf8Value(): void
    {
        $value = "\xFFsecret";

        self::assertSame(
            str_repeat('█', strlen($value)),
            new Redactor()->redact(
                $value,
                2,
            ),
        );
    }

    public function testEmptyValueRemainsEmpty(): void
    {
        $redactor = new Redactor();

        self::assertSame(
            '',
            $redactor->redact(''),
        );
    }

    public function testRejectsNegativeVisibleCharacters(): void
    {
        $redactor = new Redactor();

        $this->expectException(\InvalidArgumentException::class);

        $redactor->redact('secret', -1);
    }

    public function testRejectsEmptyMaskCharacter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Redactor('');
    }

    public function testRejectsMultipleMaskCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Redactor('**');
    }

    public function testRejectsInvalidUtf8MaskCharacter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'exactly one valid UTF-8 character',
        );

        new Redactor("\xFF");
    }

    #[DataProvider('unsafeMaskCharacterProvider')]
    public function testRejectsUnsafeMaskCharacter(
        string $maskCharacter,
    ): void {
        $this->expectException(
            \InvalidArgumentException::class,
        );

        $this->expectExceptionMessage(
            'letter, number, punctuation, or symbol categories',
        );

        new Redactor($maskCharacter);
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
