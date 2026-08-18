<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Monolog;

use Alkin\MaskedBundle\StructuredDataMasker;
use Monolog\Formatter\JsonFormatter;
use Throwable;
use UnexpectedValueException;

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
	 * @return array<
	 *     array-key,
	 *     string|int|array<string|int|array<string>>
	 * >
	 */
	protected function normalizeException(
		Throwable $e,
		int $depth = 0,
	): array {
		$normalized = parent::normalizeException(
			$e,
			$depth,
		);

		$masked = $this->structuredDataMasker->mask(
			$normalized,
		);

		if (!is_array($masked))
		{
			throw new UnexpectedValueException(
				'Normalized exception data must remain an array after masking.',
			);
		}

		/**
		 * StructuredDataMasker preserves the structure and scalar value
		 * categories of normalized Monolog exception data. Sensitive integer
		 * values may become masked strings, which are also valid here.
		 *
		 * @var array<
		 *     array-key,
		 *     string|int|array<string|int|array<string>>
		 * > $masked
		 */
		return $masked;
	}
}
