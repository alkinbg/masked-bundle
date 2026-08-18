<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Monolog;

use Alkin\MaskedBundle\StructuredDataMasker;
use DateTimeInterface;
use JsonException;
use JsonSerializable;
use LogicException;
use Monolog\Formatter\JsonFormatter;
use Stringable;
use Throwable;

final class SensitiveJsonFormatter extends JsonFormatter
{
	public function __construct(
		private readonly StructuredDataMasker $structuredDataMasker,
		int $batchMode = self::BATCH_MODE_JSON,
		bool $appendNewline = true,
		bool $ignoreEmptyContextAndExtra = false,
		bool $includeStacktraces = false,
	) {
		parent::__construct(
			$batchMode,
			$appendNewline,
			$ignoreEmptyContextAndExtra,
			$includeStacktraces,
		);
	}

	/**
	 * @return null|scalar|array<array<mixed>|scalar|null|object>|object
	 * @throws JsonException
	 */
	#[\Override]
	protected function normalize(
		mixed $data,
		int $depth = 0,
	): mixed {
		if ($depth > $this->maxNormalizeDepth)
		{
			return $this->maskNormalized(
				parent::normalize($data, $depth),
			);
		}

		if (!is_object($data))
		{
			return $this->maskNormalized(
				parent::normalize($data, $depth),
			);
		}

		/*
		 * Preserve Monolog's native DateTime normalization.
		 *
		 * DateTimeInterface is not Stringable and must not fall through
		 * to generic JSON object handling.
		 */
		if ($data instanceof DateTimeInterface)
		{
			return $this->maskNormalized(
				parent::normalize($data, $depth),
			);
		}

		/*
		 * Throwable implements Stringable, but Monolog intentionally treats
		 * exceptions before generic Stringable objects so exception metadata
		 * and stacktrace configuration remain intact.
		 */
		if ($data instanceof Throwable)
		{
			return $this->maskNormalized(
				parent::normalize($data, $depth),
			);
		}

		/*
		 * JsonFormatter intentionally leaves JsonSerializable objects to the
		 * JSON encoder. Resolve exactly that JSON representation now so it can
		 * be masked before the complete record is encoded.
		 */
		if ($data instanceof JsonSerializable)
		{
			return $this->normalizeJsonRepresentation($data);
		}

		/*
		 * Preserve Monolog's __toString() behavior, including its exception
		 * handling when __toString() fails.
		 */
		if ($data instanceof Stringable)
		{
			return $this->maskNormalized(
				parent::normalize($data, $depth),
			);
		}

		/*
		 * Preserve Monolog's special handling of incomplete PHP objects.
		 */
		if (get_class($data) === '__PHP_Incomplete_Class')
		{
			return $this->maskNormalized(
				parent::normalize($data, $depth),
			);
		}

		/*
		 * Generic objects are normally left intact by JsonFormatter and are
		 * serialized later by json_encode(). Use Monolog's own JSON encoder
		 * here so public properties, backed enums and other PHP JSON semantics
		 * remain consistent with the native formatter.
		 */
		return $this->normalizeJsonRepresentation($data);
	}

	/**
	 * @return null|scalar|array<array<mixed>|scalar|null|object>|object
	 * @throws JsonException
	 */
	private function normalizeJsonRepresentation(
		object $data,
	): mixed {
		$json = $this->toJson(
			$data,
			true,
		);

		$decoded = json_decode(
			$json,
			true,
			512,
			JSON_THROW_ON_ERROR,
		);

		if (
			$decoded !== null
			&& !is_scalar($decoded)
			&& !is_array($decoded)
		)
		{
			throw new LogicException(
				'JSON object representation must decode to scalar, array, or null.',
			);
		}

		/**
		 * json_decode() with associative arrays can only produce scalars, null,
		 * or arrays containing further JSON-compatible values.
		 *
		 * @var null|scalar|array<array<mixed>|scalar|null> $decoded
		 */
		return $this->maskNormalized($decoded);
	}

	/**
	 * @param null|scalar|array<array<mixed>|scalar|null|object>|object $data
	 *
	 * @return null|scalar|array<array<mixed>|scalar|null|object>|object
	 */
	private function maskNormalized(
		mixed $data,
	): mixed {
		$masked = $this->structuredDataMasker->mask($data);

		if (
			$masked !== null
			&& !is_scalar($masked)
			&& !is_array($masked)
			&& !is_object($masked)
		)
		{
			throw new LogicException(
				'Normalized JSON formatter data must remain scalar, array, object, or null after masking.',
			);
		}

		/**
		 * StructuredDataMasker preserves normalized arrays recursively and
		 * may only replace sensitive scalar values with masked strings.
		 *
		 * @var null|scalar|array<array<mixed>|scalar|null|object>|object $masked
		 */
		return $masked;
	}
}
