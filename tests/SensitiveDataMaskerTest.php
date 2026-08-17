<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests;

use Alkin\MaskedBundle\SensitiveDataMasker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveDataMasker::class)]
final class SensitiveDataMaskerTest extends TestCase
{
	public function testReturnsValueUnchangedWhenNothingSensitiveIsDetected(): void
	{
		$value = 'Nothing sensitive here.';

		self::assertSame(
			$value,
			new SensitiveDataMasker()->mask($value),
		);
	}

	public function testMasksDetectedPaymentCard(): void
	{
		$value = 'Card: 4111111111111111';

		self::assertSame(
			'Card: ' . str_repeat('█', 16),
			new SensitiveDataMasker()->mask($value),
		);
	}

	public function testMasksFormattedPaymentCard(): void
	{
		$value = 'Card: 4111 1111 1111 1111';

		self::assertSame(
			'Card: ' . str_repeat('█', 19),
			new SensitiveDataMasker()->mask($value),
		);
	}

	public function testMasksMultiplePaymentCards(): void
	{
		$value =
			'Primary: 4111111111111111; backup: 5555555555554444';

		self::assertSame(
			'Primary: '
			. str_repeat('█', 16)
			. '; backup: '
			. str_repeat('█', 16),
			new SensitiveDataMasker()->mask($value),
		);
	}

	public function testPreservesMultibyteSurroundingText(): void
	{
		$value = 'Плащане с карта 4111111111111111 е отказано.';

		self::assertSame(
			'Плащане с карта '
			. str_repeat('█', 16)
			. ' е отказано.',
			new SensitiveDataMasker()->mask($value),
		);
	}
}
