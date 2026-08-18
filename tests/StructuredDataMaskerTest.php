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

	public function testHandlesSelfReferentialArray(): void
	{
		$value = [
			'card' => '4111111111111111',
		];

		$value['self'] =& $value;

		$masked = new StructuredDataMasker()->mask($value);

		self::assertIsArray($masked);

		self::assertSame(
			str_repeat('█', 16),
			$masked['card'],
		);

		$self = $masked['self'];

		self::assertIsArray($self);

		self::assertSame(
			str_repeat('█', 16),
			$self['card'],
		);

		self::assertSame(
			'[recursive array]',
			$self['self'],
		);

		self::assertSame(
			'4111111111111111',
			$value['card'],
		);
	}

	public function testHandlesMutuallyRecursiveArrays(): void
	{
		$first = [
			'card' => '4111111111111111',
		];

		$second = [
			'card' => '5555555555554444',
		];

		$first['next'] =& $second;
		$second['next'] =& $first;

		$masked = new StructuredDataMasker()->mask($first);

		self::assertIsArray($masked);

		self::assertSame(
			str_repeat('█', 16),
			$masked['card'],
		);

		$maskedSecond = $masked['next'];

		self::assertIsArray($maskedSecond);

		self::assertSame(
			str_repeat('█', 16),
			$maskedSecond['card'],
		);

		$maskedFirst = $maskedSecond['next'];

		self::assertIsArray($maskedFirst);

		self::assertSame(
			str_repeat('█', 16),
			$maskedFirst['card'],
		);

		self::assertSame(
			'[recursive array]',
			$maskedFirst['next'],
		);

		self::assertSame(
			'4111111111111111',
			$first['card'],
		);

		self::assertSame(
			'5555555555554444',
			$second['card'],
		);
	}

	public function testDoesNotTreatSharedArrayReferenceAsRecursion(): void
	{
		$shared = [
			'card' => '4111111111111111',
		];

		$value = [
			'first' => &$shared,
			'second' => &$shared,
		];

		self::assertSame(
			[
				'first' => [
					'card' => str_repeat('█', 16),
				],
				'second' => [
					'card' => str_repeat('█', 16),
				],
			],
			new StructuredDataMasker()->mask($value),
		);
	}

	public function testMasksArrayBelowMaximumNestingDepth(): void
	{
		$value = self::createNestedArray(
			31,
			'4111111111111111',
		);

		$current = new StructuredDataMasker()->mask($value);

		for ($level = 0; $level < 31; ++$level)
		{
			self::assertIsArray($current);
			self::assertArrayHasKey('nested', $current);

			$current = $current['nested'];
		}

		self::assertIsArray($current);

		self::assertSame(
			str_repeat('█', 16),
			$current['card'],
		);
	}

	public function testStopsAtMaximumNestingDepth(): void
	{
		$value = self::createNestedArray(
			32,
			'4111111111111111',
		);

		$current = new StructuredDataMasker()->mask($value);

		for ($level = 0; $level < 32; ++$level)
		{
			self::assertIsArray($current);
			self::assertArrayHasKey('nested', $current);

			$current = $current['nested'];
		}

		self::assertSame(
			'[maximum nesting depth exceeded]',
			$current,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function createNestedArray(
		int $nestingLevels,
		string $card,
	): array {
		$value = [
			'card' => $card,
		];

		for ($level = 0; $level < $nestingLevels; ++$level)
		{
			$value = [
				'nested' => $value,
			];
		}

		return $value;
	}
}
