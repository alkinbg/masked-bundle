<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests;

use Masked\Bundle\Detection\SensitiveDataMatch;
use Masked\Bundle\RangeRedactor;
use Masked\Bundle\Redactor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RangeRedactor::class)]
final class RangeRedactorTest extends TestCase
{
    public function testReturnsOriginalValueWhenThereAreNoMatches(): void
    {
        $value = 'Nothing sensitive here.';

        self::assertSame(
            $value,
            new RangeRedactor()->redact($value, []),
        );
    }

    public function testRedactsSingleRangeAndPreservesSurroundingText(): void
    {
        $value = 'token=secret&status=ok';

        $matches = [
            new SensitiveDataMatch(
                byteOffset: 6,
                byteLength: 6,
            ),
        ];

        self::assertSame(
            'token='.str_repeat('█', 6).'&status=ok',
            new RangeRedactor()->redact($value, $matches),
        );
    }

    public function testRedactsFormattingCharactersInsideRange(): void
    {
        $value = '4111 1111 1111 1111';

        $matches = [
            new SensitiveDataMatch(
                byteOffset: 0,
                byteLength: strlen($value),
            ),
        ];

        self::assertSame(
            str_repeat('█', 19),
            new RangeRedactor()->redact($value, $matches),
        );
    }

    public function testUsesByteOffsetsWithMultibyteSurroundingText(): void
    {
        $prefix = 'Плащане: ';
        $sensitiveValue = '4111111111111111';
        $suffix = ' е отказано.';
        $value = $prefix.$sensitiveValue.$suffix;

        $matches = [
            new SensitiveDataMatch(
                byteOffset: strlen($prefix),
                byteLength: strlen($sensitiveValue),
            ),
        ];

        self::assertSame(
            $prefix.str_repeat('█', 16).$suffix,
            new RangeRedactor()->redact($value, $matches),
        );
    }

    public function testRedactsMultipleRangesWithoutInvalidatingByteOffsets(): void
    {
        $prefix = 'Primary: ';
        $separator = '; backup: ';
        $first = '4111111111111111';
        $second = '5555555555554444';
        $suffix = '.';

        $value = $prefix.$first.$separator.$second.$suffix;

        $matches = [
            new SensitiveDataMatch(
                byteOffset: strlen($prefix),
                byteLength: strlen($first),
            ),
            new SensitiveDataMatch(
                byteOffset: strlen($prefix.$first.$separator),
                byteLength: strlen($second),
            ),
        ];

        self::assertSame(
            $prefix
            .str_repeat('█', 16)
            .$separator
            .str_repeat('█', 16)
            .$suffix,
            new RangeRedactor()->redact($value, $matches),
        );
    }

    public function testMatchInputOrderDoesNotMatter(): void
    {
        $separator = ' / ';
        $first = '4111111111111111';
        $second = '5555555555554444';

        $value = $first.$separator.$second;

        $matches = [
            new SensitiveDataMatch(
                byteOffset: strlen($first.$separator),
                byteLength: strlen($second),
            ),
            new SensitiveDataMatch(
                byteOffset: 0,
                byteLength: strlen($first),
            ),
        ];

        self::assertSame(
            str_repeat('█', 16)
            .$separator
            .str_repeat('█', 16),
            new RangeRedactor()->redact($value, $matches),
        );
    }

    public function testUsesConfiguredRedactor(): void
    {
        $value = 'token=secret';

        $matches = [
            new SensitiveDataMatch(
                byteOffset: 6,
                byteLength: 6,
            ),
        ];

        $rangeRedactor = new RangeRedactor(
            new Redactor('*'),
        );

        self::assertSame(
            'token=******',
            $rangeRedactor->redact($value, $matches),
        );
    }

    public function testRejectsRangeOutsideInputBounds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Sensitive data match exceeds the bounds of the input value.',
        );

        new RangeRedactor()->redact(
            'short',
            [
                new SensitiveDataMatch(
                    byteOffset: 4,
                    byteLength: 2,
                ),
            ],
        );
    }

    public function testMergesOverlappingSensitiveRanges(): void
    {
        $value = 'abcdefghij';

        $matches = [
            new SensitiveDataMatch(
                byteOffset: 2,
                byteLength: 4,
            ),
            new SensitiveDataMatch(
                byteOffset: 4,
                byteLength: 4,
            ),
        ];

        self::assertSame(
            'ab'.str_repeat('█', 6).'ij',
            new RangeRedactor()->redact($value, $matches),
        );
    }
}
