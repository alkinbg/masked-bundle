<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests;

use Alkin\MaskedBundle\Redactor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
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

	public function testHandlesMultibyteValues(): void
	{
		$redactor = new Redactor();

		self::assertSame(
			'████ла',
			$redactor->redact('парола', 2),
		);
	}

	public function testEmptyValueRemainsEmpty(): void
	{
		$redactor = new Redactor();

		self::assertSame('', $redactor->redact(''));
	}

	public function testRejectsNegativeVisibleCharacters(): void
	{
		$redactor = new Redactor();

		$this->expectException(InvalidArgumentException::class);

		$redactor->redact('secret', -1);
	}

	public function testRejectsEmptyMaskCharacter(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Redactor('');
	}

	public function testRejectsMultipleMaskCharacters(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Redactor('**');
	}
}