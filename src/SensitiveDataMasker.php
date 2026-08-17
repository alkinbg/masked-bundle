<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle;

use Alkin\MaskedBundle\Detection\PaymentCardDetector;

final readonly class SensitiveDataMasker
{
	public function __construct(
		private PaymentCardDetector $paymentCardDetector =
		new PaymentCardDetector(),
		private RangeRedactor $rangeRedactor = new RangeRedactor(),
	) {
	}

	public function mask(string $value): string
	{
		$matches = $this->paymentCardDetector->detect($value);

		return $this->rangeRedactor->redact(
			$value,
			$matches,
		);
	}
}
