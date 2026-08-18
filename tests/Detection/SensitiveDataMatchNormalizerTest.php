<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests\Detection;

use Alkin\MaskedBundle\Detection\SensitiveDataMatch;
use Alkin\MaskedBundle\Detection\SensitiveDataMatchNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveDataMatchNormalizer::class)]
final class SensitiveDataMatchNormalizerTest extends TestCase
{
    public function testReturnsEmptyListUnchanged(): void
    {
        self::assertSame(
            [],
            new SensitiveDataMatchNormalizer()->normalize([]),
        );
    }

    public function testReturnsSingleMatchUnchanged(): void
    {
        $match = new SensitiveDataMatch(
            byteOffset: 4,
            byteLength: 6,
        );

        self::assertSame(
            [$match],
            new SensitiveDataMatchNormalizer()->normalize([$match]),
        );
    }

    public function testSortsMatchesByByteOffset(): void
    {
        $first = new SensitiveDataMatch(
            byteOffset: 2,
            byteLength: 4,
        );

        $second = new SensitiveDataMatch(
            byteOffset: 10,
            byteLength: 4,
        );

        self::assertSame(
            [$first, $second],
            new SensitiveDataMatchNormalizer()->normalize([
                $second,
                $first,
            ]),
        );
    }

    public function testMergesOverlappingMatches(): void
    {
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

        $normalized = new SensitiveDataMatchNormalizer()->normalize($matches);

        self::assertCount(1, $normalized);
        self::assertSame(2, $normalized[0]->byteOffset);
        self::assertSame(6, $normalized[0]->byteLength);
    }

    public function testMergesContainedMatch(): void
    {
        $matches = [
            new SensitiveDataMatch(
                byteOffset: 2,
                byteLength: 8,
            ),
            new SensitiveDataMatch(
                byteOffset: 4,
                byteLength: 2,
            ),
        ];

        $normalized = new SensitiveDataMatchNormalizer()->normalize($matches);

        self::assertCount(1, $normalized);
        self::assertSame(2, $normalized[0]->byteOffset);
        self::assertSame(8, $normalized[0]->byteLength);
    }

    public function testKeepsAdjacentMatchesSeparate(): void
    {
        $first = new SensitiveDataMatch(
            byteOffset: 2,
            byteLength: 4,
        );

        $second = new SensitiveDataMatch(
            byteOffset: 6,
            byteLength: 4,
        );

        self::assertSame(
            [$first, $second],
            new SensitiveDataMatchNormalizer()->normalize([
                $second,
                $first,
            ]),
        );
    }
}
