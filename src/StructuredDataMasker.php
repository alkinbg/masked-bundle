<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle;

final readonly class StructuredDataMasker
{
	public function __construct(
		private SensitiveDataMasker $sensitiveDataMasker =
		new SensitiveDataMasker(),
	) {
	}

	public function mask(mixed $value): mixed
	{
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

		$masked = [];

		foreach ($value as $key => $item)
		{
			$maskedKey = $this->maskArrayKey($key);

			$maskedKey = $this->ensureUniqueArrayKey(
				$maskedKey,
				$masked,
			);

			$masked[$maskedKey] = $this->mask($item);
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
