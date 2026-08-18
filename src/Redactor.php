<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle;

use InvalidArgumentException;

final readonly class Redactor
{
	private const string CHARACTER_ENCODING = 'UTF-8';

	public function __construct(
		private string $maskCharacter = '█',
	) {
		if (
			!mb_check_encoding(
				$this->maskCharacter,
				self::CHARACTER_ENCODING,
			)
			|| mb_strlen(
				$this->maskCharacter,
				self::CHARACTER_ENCODING,
			) !== 1
		)
		{
			throw new InvalidArgumentException(
				'The mask character must contain exactly one valid UTF-8 character.',
			);
		}
	}

	public function redact(
		string $value,
		int $visibleTrailingCharacters = 0,
	): string {
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

		if (!mb_check_encoding($value, self::CHARACTER_ENCODING))
		{
			/*
			 * Character boundaries are undefined for malformed UTF-8.
			 * Mask the complete byte sequence instead of exposing a
			 * trailing portion that could start inside an invalid
			 * multibyte sequence.
			 */
			return str_repeat(
				$this->maskCharacter,
				strlen($value),
			);
		}

		$length = mb_strlen(
			$value,
			self::CHARACTER_ENCODING,
		);

		/*
		 * If the requested visible part is equal to or longer than the
		 * sensitive value, mask everything instead of exposing the value.
		 */
		if (
			$visibleTrailingCharacters === 0
			|| $visibleTrailingCharacters >= $length
		)
		{
			return str_repeat(
				$this->maskCharacter,
				$length,
			);
		}

		$visible = mb_substr(
			$value,
			-$visibleTrailingCharacters,
			null,
			self::CHARACTER_ENCODING,
		);

		return str_repeat(
				$this->maskCharacter,
				$length - $visibleTrailingCharacters,
			) . $visible;
	}
}
