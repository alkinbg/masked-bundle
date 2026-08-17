<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle;

use InvalidArgumentException;

final readonly class Redactor
{
	public function __construct(
		private string $maskCharacter = '█',
	) {
		if (mb_strlen($this->maskCharacter) !== 1)
		{
			throw new InvalidArgumentException(
				'The mask character must contain exactly one character.',
			);
		}
	}

	public function redact(string $value, int $visibleTrailingCharacters = 0): string
	{
		if ($visibleTrailingCharacters < 0)
		{
			throw new InvalidArgumentException(
				'The number of visible trailing characters cannot be negative.',
			);
		}

		if ($value === '')
		{
			return '';
		}

		$length = mb_strlen($value);

		/*
		 * If the requested visible part is equal to or longer than the
		 * sensitive value, mask everything instead of exposing the value.
		 */
		if (
			$visibleTrailingCharacters === 0
			|| $visibleTrailingCharacters >= $length
		)
		{
			return str_repeat($this->maskCharacter, $length);
		}

		$visible = mb_substr($value, -$visibleTrailingCharacters);

		return str_repeat(
				$this->maskCharacter,
				$length - $visibleTrailingCharacters,
			) . $visible;
	}
}