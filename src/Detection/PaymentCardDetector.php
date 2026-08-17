<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Detection;

use RuntimeException;
use UnexpectedValueException;

final class PaymentCardDetector
{
	/*
	 * Conservative bounds used for automatic free-text detection.
	 *
	 * These values define what this detector scans automatically; they are
	 * intentionally not a complete definition of every PAN length permitted
	 * by payment-card standards. Structured sensitive fields can be handled
	 * independently by higher-level masking logic.
	 */
	private const int MIN_DETECTABLE_PAN_LENGTH = 13;
	private const int MAX_DETECTABLE_PAN_LENGTH = 19;

	/**
	 * Matches sequences of ASCII digit groups separated by supported
	 * formatting characters.
	 *
	 * [0-9] is used intentionally instead of \d. A PAN consists of ASCII
	 * decimal digits, and keeping this explicit prevents the meaning of the
	 * pattern from changing if Unicode mode is introduced in the future.
	 *
	 * The Unicode modifier is intentionally not used. Detection must keep
	 * working even when the surrounding input contains invalid UTF-8 bytes.
	 *
	 * Supported separators:
	 * - space
	 * - horizontal tab
	 * - hyphen
	 * - UTF-8 non-breaking space
	 */
	private const string SEQUENCE_PATTERN =
		'~(?<![0-9])[0-9]+(?:(?:[ \t-]|\xC2\xA0)+[0-9]+)*(?![0-9])~';

	/**
	 * @return list<SensitiveDataMatch>
	 *
	 * @throws RuntimeException When the input cannot be scanned safely.
	 */
	public function detect(string $value): array
	{
		if ($value === '')
		{
			return [];
		}

		$result = preg_match_all(
			self::SEQUENCE_PATTERN,
			$value,
			$sequences,
			PREG_OFFSET_CAPTURE,
		);

		if ($result === false)
		{
			throw new RuntimeException(
				'Failed to scan input for payment-card candidates: '
				. preg_last_error_msg(),
			);
		}

		if ($result === 0)
		{
			return [];
		}

		/** @var list<array{0: string, 1: int<-1, max>}> $capturedSequences */
		$capturedSequences = $sequences[0];

		$matches = [];

		foreach ($capturedSequences as $sequence)
		{
			/*
			 * Keep the PREG_OFFSET_CAPTURE tuple explicit instead of destructuring
			 * it. Index 0 is the matched value and index 1 is its byte offset.
			 * This makes the boundary with the PCRE API visible at the point of use.
			 */
			$sequenceValue = $sequence[0];
			$sequenceByteOffset = $sequence[1];

			/*
			 * A full match should always have a non-negative offset. Treat an
			 * unexpected negative offset as an error instead of silently skipping
			 * a potentially sensitive sequence.
			 */
			if ($sequenceByteOffset < 0)
			{
				throw new UnexpectedValueException(
					'PCRE returned a negative byte offset for a full sequence match.',
				);
			}

			foreach ($this->detectInSequence($sequenceValue) as $match)
			{
				$matches[] = new SensitiveDataMatch(
					byteOffset: $sequenceByteOffset + $match->byteOffset,
					byteLength: $match->byteLength,
				);
			}
		}

		return $this->mergeOverlappingMatches($matches);
	}

	/**
	 * @return list<SensitiveDataMatch>
	 *
	 * @throws RuntimeException When the sequence cannot be scanned safely.
	 */
	private function detectInSequence(string $sequence): array
	{
		// Keep ASCII digit semantics consistent with SEQUENCE_PATTERN.
		$result = preg_match_all(
			'~[0-9]+~',
			$sequence,
			$groups,
			PREG_OFFSET_CAPTURE,
		);

		if ($result === false)
		{
			throw new RuntimeException(
				'Failed to scan a payment-card candidate sequence: '
				. preg_last_error_msg(),
			);
		}

		if ($result === 0)
		{
			return [];
		}

		/** @var list<array{0: string, 1: int<-1, max>}> $digitGroups */
		$digitGroups = $groups[0];

		$matches = [];
		$groupCount = count($digitGroups);

		/*
		 * Index-based loops are intentional here. Detection examines every
		 * contiguous window of digit groups and must be able to stop as soon as
		 * the candidate exceeds the maximum detectable PAN length.
		 */
		for ($startIndex = 0; $startIndex < $groupCount; ++$startIndex)
		{
			$startGroup = $digitGroups[$startIndex];
			$startByteOffset = $startGroup[1];

			if ($startByteOffset < 0)
			{
				throw new UnexpectedValueException(
					'PCRE returned a negative byte offset for a full digit-group match.',
				);
			}

			$digitCount = 0;

			for (
				$endIndex = $startIndex;
				$endIndex < $groupCount;
				++$endIndex
			)
			{
				$endGroup = $digitGroups[$endIndex];

				/*
				 * Same PREG_OFFSET_CAPTURE tuple shape as above: the matched
				 * value is at index 0 and its byte offset is at index 1.
				 */
				$endGroupValue = $endGroup[0];
				$endGroupByteOffset = $endGroup[1];

				if ($endGroupByteOffset < 0)
				{
					throw new UnexpectedValueException(
						'PCRE returned a negative byte offset for a full digit-group match.',
					);
				}

				$endGroupByteLength = strlen($endGroupValue);
				$digitCount += $endGroupByteLength;

				if ($digitCount > self::MAX_DETECTABLE_PAN_LENGTH)
				{
					break;
				}

				if ($digitCount < self::MIN_DETECTABLE_PAN_LENGTH)
				{
					continue;
				}

				$endByteOffsetExclusive =
					$endGroupByteOffset + $endGroupByteLength;

				$candidate = substr(
					$sequence,
					$startByteOffset,
					$endByteOffsetExclusive - $startByteOffset,
				);

				if (!$this->isValidPanCandidate($candidate))
				{
					continue;
				}

				$matches[] = new SensitiveDataMatch(
					byteOffset: $startByteOffset,
					byteLength: $endByteOffsetExclusive - $startByteOffset,
				);
			}
		}

		return $matches;
	}

	private function isValidPanCandidate(string $candidate): bool
	{
		$pan = str_replace(
			[
				' ',
				"\t",
				'-',
				"\xC2\xA0",
			],
			'',
			$candidate,
		);

		$length = strlen($pan);

		if (
			$length < self::MIN_DETECTABLE_PAN_LENGTH
			|| $length > self::MAX_DETECTABLE_PAN_LENGTH
		)
		{
			return false;
		}

		/*
		 * Validation remains defensive even though candidates currently
		 * originate from a digit-only regex. This keeps the Luhn implementation
		 * independent from assumptions made by its caller.
		 */
		if (strspn($pan, '0123456789') !== $length)
		{
			return false;
		}

		if ($this->consistsOfSingleRepeatedDigit($pan))
		{
			return false;
		}

		return $this->passesLuhn($pan);
	}

	private function consistsOfSingleRepeatedDigit(string $pan): bool
	{
		$firstDigit = $pan[0];
		$length = strlen($pan);

		for ($index = 1; $index < $length; ++$index)
		{
			if ($pan[$index] !== $firstDigit)
			{
				return false;
			}
		}

		return true;
	}

	private function passesLuhn(string $pan): bool
	{
		$sum = 0;
		$length = strlen($pan);
		$parity = $length % 2;

		for ($index = 0; $index < $length; ++$index)
		{
			$digit = (int)$pan[$index];

			if ($index % 2 === $parity)
			{
				$digit *= 2;

				if ($digit > 9)
				{
					$digit -= 9;
				}
			}

			$sum += $digit;
		}

		return $sum % 10 === 0;
	}

	/**
	 * @param list<SensitiveDataMatch> $matches
	 *
	 * @return list<SensitiveDataMatch>
	 */
	private function mergeOverlappingMatches(array $matches): array
	{
		if (count($matches) < 2)
		{
			return $matches;
		}

		usort(
			$matches,
			static function (
				SensitiveDataMatch $left,
				SensitiveDataMatch $right,
			): int {
				$offsetComparison =
					$left->byteOffset <=> $right->byteOffset;

				if ($offsetComparison !== 0)
				{
					return $offsetComparison;
				}

				/*
				 * For candidates starting at the same byte, process the
				 * longest range first.
				 */
				return $right->byteLength <=> $left->byteLength;
			},
		);

		$merged = [];

		foreach ($matches as $match)
		{
			$lastIndex = count($merged) - 1;

			if ($lastIndex < 0)
			{
				$merged[] = $match;

				continue;
			}

			$previous = $merged[$lastIndex];

			/*
			 * Touching ranges are intentionally kept separate. Only true
			 * overlaps are merged.
			 */
			if (
				$match->byteOffset
				>= $previous->endByteOffsetExclusive()
			)
			{
				$merged[] = $match;

				continue;
			}

			$endByteOffsetExclusive = max(
				$previous->endByteOffsetExclusive(),
				$match->endByteOffsetExclusive(),
			);

			$merged[$lastIndex] = new SensitiveDataMatch(
				byteOffset: $previous->byteOffset,
				byteLength: $endByteOffsetExclusive - $previous->byteOffset,
			);
		}

		return array_values($merged);
	}
}
