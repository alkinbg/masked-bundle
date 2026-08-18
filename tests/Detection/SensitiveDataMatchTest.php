<?php

declare(strict_types=1);

namespace Masked\Tests\Detection;

use Masked\Detection\SensitiveDataMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveDataMatch::class)]
final class SensitiveDataMatchTest extends TestCase
{
    public function testStoresByteRange(): void
    {
        $match = new SensitiveDataMatch(
            byteOffset: 12,
            byteLength: 19,
        );

        self::assertSame(12, $match->byteOffset);
        self::assertSame(19, $match->byteLength);
    }

    public function testCalculatesExclusiveEndByteOffset(): void
    {
        $match = new SensitiveDataMatch(
            byteOffset: 12,
            byteLength: 19,
        );

        self::assertSame(31, $match->endByteOffsetExclusive());
    }

    public function testAllowsMatchAtBeginningOfString(): void
    {
        $match = new SensitiveDataMatch(
            byteOffset: 0,
            byteLength: 16,
        );

        self::assertSame(0, $match->byteOffset);
    }

    public function testRejectsNegativeByteOffset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The byte offset cannot be negative.');

        new SensitiveDataMatch(
            byteOffset: -1,
            byteLength: 16,
        );
    }

    public function testRejectsZeroByteLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The byte length must be greater than zero.',
        );

        new SensitiveDataMatch(
            byteOffset: 0,
            byteLength: 0,
        );
    }

    public function testRejectsNegativeByteLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The byte length must be greater than zero.',
        );

        new SensitiveDataMatch(
            byteOffset: 0,
            byteLength: -1,
        );
    }

    public function testAllowsSingleByteMatch(): void
    {
        $match = new SensitiveDataMatch(
            byteOffset: 0,
            byteLength: 1,
        );

        self::assertSame(1, $match->byteLength);
        self::assertSame(1, $match->endByteOffsetExclusive());
    }
}
