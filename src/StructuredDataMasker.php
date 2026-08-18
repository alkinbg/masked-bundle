<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle;

use ReflectionReference;

final readonly class StructuredDataMasker
{
	private const int MAX_ARRAY_NESTING_DEPTH = 32;

	private const string RECURSIVE_ARRAY_PLACEHOLDER =
		'[recursive array]';

	private const string MAXIMUM_NESTING_DEPTH_PLACEHOLDER =
		'[maximum nesting depth exceeded]';

	public function __construct(
		private SensitiveDataMasker $sensitiveDataMasker =
		new SensitiveDataMasker(),
	) {
	}

	public function mask(mixed $value): mixed
	{
		return $this->maskValue(
			$value,
			[],
			0,
		);
	}

	/**
	 * @param array<string, true> $activeArrayReferenceIds
	 */
	private function maskValue(
		mixed $value,
		array $activeArrayReferenceIds,
		int $arrayDepth,
	): mixed {
		if (is_string($value))
		{
			return $this->sensitiveDataMasker->mask($value);
		}

		if (is_int($value))
		{
			$valueAsString = (string)$value;

			$masked = $this->sensitiveDataMasker->mask(
				$valueAsString,
			);

			if ($masked !== $valueAsString)
			{
				return $masked;
			}

			return $value;
		}

		if (!is_array($value))
		{
			return $value;
		}

		if ($arrayDepth >= self::MAX_ARRAY_NESTING_DEPTH)
		{
			return self::MAXIMUM_NESTING_DEPTH_PLACEHOLDER;
		}

		return $this->maskArray(
			$value,
			$activeArrayReferenceIds,
			$arrayDepth,
		);
	}

	/**
	 * @param array<int|string, mixed> $value
	 * @param array<string, true> $activeArrayReferenceIds
	 *
	 * @return array<int|string, mixed>
	 */
	private function maskArray(
		array $value,
		array $activeArrayReferenceIds,
		int $arrayDepth,
	): array {
		$masked = [];

		foreach ($value as $key => $item)
		{
			$maskedKey = $this->maskArrayKey($key);

			$maskedKey = $this->ensureUniqueArrayKey(
				$maskedKey,
				$masked,
			);

			if (is_array($item))
			{
				$reference = ReflectionReference::fromArrayElement(
					$value,
					$key,
				);

				if ($reference !== null)
				{
					$referenceId = 'ref:' . $reference->getId();

					if (isset($activeArrayReferenceIds[$referenceId]))
					{
						$masked[$maskedKey] =
							self::RECURSIVE_ARRAY_PLACEHOLDER;

						continue;
					}

					$nestedActiveArrayReferenceIds =
						$activeArrayReferenceIds;

					$nestedActiveArrayReferenceIds[$referenceId] =
						true;

					$masked[$maskedKey] = $this->maskValue(
						$item,
						$nestedActiveArrayReferenceIds,
						$arrayDepth + 1,
					);

					continue;
				}
			}

			$masked[$maskedKey] = $this->maskValue(
				$item,
				$activeArrayReferenceIds,
				$arrayDepth + 1,
			);
		}

		return $masked;
	}

	private function maskArrayKey(int|string $key): int|string
	{
		$keyAsString = (string)$key;

		$maskedKey = $this->sensitiveDataMasker->mask(
			$keyAsString,
		);

		if ($maskedKey === $keyAsString)
		{
			return $key;
		}

		return $maskedKey;
	}

	/**
	 * @param array<int|string, mixed> $masked
	 */
	private function ensureUniqueArrayKey(
		int|string $key,
		array $masked,
	): int|string {
		if (!array_key_exists($key, $masked))
		{
			return $key;
		}

		$baseKey = (string)$key;
		$suffix = 2;

		do
		{
			$candidate = $baseKey . '#' . $suffix;
			++$suffix;
		} while (array_key_exists($candidate, $masked));

		return $candidate;
	}
}
