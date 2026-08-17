<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests;

use Alkin\MaskedBundle\StructuredDataMasker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StructuredDataMasker::class)]
final class StructuredDataMaskerTest extends TestCase
{
	public function testMasksStringValue(): void
	{
		self::assertSame(
			str_repeat('█', 16),
			new StructuredDataMasker()->mask(
				'4111111111111111',
			),
		);
	}

	public function testMasksSensitiveIntegerValue(): void
	{
		if (PHP_INT_SIZE < 8)
		{
			self::markTestSkipped(
				'This test requires 64-bit PHP integers.',
			);
		}

		self::assertSame(
			str_repeat('█', 16),
			new StructuredDataMasker()->mask(
				4111111111111111,
			),
		);
	}

	public function testPreservesNonStringScalarValues(): void
	{
		$masker = new StructuredDataMasker();

		self::assertSame(42, $masker->mask(42));
		self::assertSame(12.5, $masker->mask(12.5));
		self::assertSame(true, $masker->mask(true));
		self::assertSame(false, $masker->mask(false));
		self::assertNull($masker->mask(null));
	}

	public function testMasksStringsRecursivelyInsideArrays(): void
	{
		$value = [
			'primary' => '4111111111111111',
			'nested' => [
				'backup' => '5555555555554444',
				'count' => 2,
			],
		];

		self::assertSame(
			[
				'primary' => str_repeat('█', 16),
				'nested' => [
					'backup' => str_repeat('█', 16),
					'count' => 2,
				],
			],
			new StructuredDataMasker()->mask($value),
		);
	}

	public function testMasksSensitiveDataInsideStringKeys(): void
	{
		$value = [
			'card-4111111111111111' => 'failed',
		];

		self::assertSame(
			[
				'card-' . str_repeat('█', 16) => 'failed',
			],
			new StructuredDataMasker()->mask($value),
		);
	}

	public function testPreservesEntriesWhenMaskedKeysCollide(): void
	{
		$value = [
			'card-4111111111111111' => 'primary',
			'card-5555555555554444' => 'backup',
		];

		self::assertSame(
			[
				'card-' . str_repeat('█', 16) => 'primary',
				'card-' . str_repeat('█', 16) . '#2' => 'backup',
			],
			new StructuredDataMasker()->mask($value),
		);
	}

	public function testPreservesIntegerArrayKeys(): void
	{
		$value = [
			10 => '4111111111111111',
		];

		self::assertSame(
			[
				10 => str_repeat('█', 16),
			],
			new StructuredDataMasker()->mask($value),
		);
	}

	public function testReturnsEmptyArrayUnchanged(): void
	{
		self::assertSame(
			[],
			new StructuredDataMasker()->mask([]),
		);
	}

	public function testPreservesObjectsWithoutConvertingThem(): void
	{
		$object = new \stdClass();

		self::assertSame(
			$object,
			new StructuredDataMasker()->mask($object),
		);
	}
}
