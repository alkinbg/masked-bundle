<?php

declare(strict_types=1);

namespace Masked\Bundle\Tests\Detection;

use Masked\Bundle\Detection\ExactValueDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExactValueDetector::class)]
final class ExactValueDetectorTest extends TestCase
{
    public function testReturnsNoMatchesForEmptyInput(): void
    {
        self::assertSame(
            [],
            new ExactValueDetector()->detect(
                '',
                ['secret'],
            ),
        );
    }

    public function testReturnsNoMatchesWithoutSensitiveValues(): void
    {
        self::assertSame(
            [],
            new ExactValueDetector()->detect(
                'Nothing sensitive here.',
                [],
            ),
        );
    }

    public function testDetectsExactSensitiveValue(): void
    {
        $matches = new ExactValueDetector()->detect(
            'Token: super-secret',
            ['super-secret'],
        );

        self::assertCount(1, $matches);
        self::assertSame(7, $matches[0]->byteOffset);
        self::assertSame(12, $matches[0]->byteLength);
    }

    public function testDetectsMultipleOccurrences(): void
    {
        $matches = new ExactValueDetector()->detect(
            'secret and secret',
            ['secret'],
        );

        self::assertCount(2, $matches);

        self::assertSame(0, $matches[0]->byteOffset);
        self::assertSame(6, $matches[0]->byteLength);

        self::assertSame(11, $matches[1]->byteOffset);
        self::assertSame(6, $matches[1]->byteLength);
    }

    public function testDetectsMultipleSensitiveValues(): void
    {
        $matches = new ExactValueDetector()->detect(
            'username=alkin&password=super-secret',
            [
                'alkin',
                'super-secret',
            ],
        );

        self::assertCount(2, $matches);

        self::assertSame(9, $matches[0]->byteOffset);
        self::assertSame(5, $matches[0]->byteLength);

        self::assertSame(24, $matches[1]->byteOffset);
        self::assertSame(12, $matches[1]->byteLength);
    }

    public function testMergesOverlappingOccurrences(): void
    {
        $matches = new ExactValueDetector()->detect(
            'aaaa',
            ['aaa'],
        );

        self::assertCount(1, $matches);
        self::assertSame(0, $matches[0]->byteOffset);
        self::assertSame(4, $matches[0]->byteLength);
    }

    public function testMatchingIsCaseSensitive(): void
    {
        self::assertSame(
            [],
            new ExactValueDetector()->detect(
                'Secret',
                ['secret'],
            ),
        );
    }

    public function testSupportsArbitraryByteSequencesAroundSensitiveValue(): void
    {
        $matches = new ExactValueDetector()->detect(
            "\xFFsecret\xFE",
            ['secret'],
        );

        self::assertCount(1, $matches);
        self::assertSame(1, $matches[0]->byteOffset);
        self::assertSame(6, $matches[0]->byteLength);
    }

    public function testRejectsEmptySensitiveValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Sensitive values must not contain an empty string.',
        );

        new ExactValueDetector()->detect(
            'Something',
            [''],
        );
    }

    public function testRejectsEmptySensitiveValueWhenInputIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Sensitive values must not contain an empty string.',
        );

        new ExactValueDetector()->detect(
            '',
            [''],
        );
    }
}
