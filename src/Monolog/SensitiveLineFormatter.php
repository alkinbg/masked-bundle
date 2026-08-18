<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Monolog;

use Alkin\MaskedBundle\SensitiveDataMasker;
use Monolog\Formatter\LineFormatter;
use Throwable;

final class SensitiveLineFormatter extends LineFormatter
{
	public function __construct(
		private readonly SensitiveDataMasker $sensitiveDataMasker,
		?string $format = null,
		?string $dateFormat = null,
		bool $allowInlineLineBreaks = false,
		bool $ignoreEmptyContextAndExtra = false,
		bool $includeStacktraces = false,
	) {
		parent::__construct(
			$format,
			$dateFormat,
			$allowInlineLineBreaks,
			$ignoreEmptyContextAndExtra,
			$includeStacktraces,
		);
	}

	protected function normalizeException(
		Throwable $e,
		int $depth = 0,
	): string {
		return $this->sensitiveDataMasker->mask(
			parent::normalizeException($e, $depth),
		);
	}
}
