<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Monolog;

use Alkin\MaskedBundle\StructuredDataMasker;
use JsonSerializable;
use Monolog\Formatter\LineFormatter;
use Monolog\Utils;

final class SensitiveLineFormatter extends LineFormatter
{
    public function __construct(
        private readonly StructuredDataMasker $structuredDataMasker,
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

    /**
     * @return scalar|array<mixed[]|scalar|null>|null
     */
    protected function normalize(
        mixed $data,
        int $depth = 0,
    ): mixed {
        if ($depth > $this->maxNormalizeDepth) {
            return $this->maskNormalized(
                parent::normalize($data, $depth),
            );
        }

        /*
         * NormalizerFormatter normally accepts the value returned from
         * JsonSerializable without recursively normalizing it. Normalize it
         * explicitly so objects nested inside that value cannot bypass
         * masking later when LineFormatter serializes the normalized record.
         */
        if (
            $data instanceof \JsonSerializable
            && !$data instanceof \DateTimeInterface
            && !$data instanceof \Throwable
        ) {
            $normalized = [
                Utils::getClass($data) => $this->normalize(
                    $data->jsonSerialize(),
                    $depth + 1,
                ),
            ];

            return $this->maskNormalized($normalized);
        }

        return $this->maskNormalized(
            parent::normalize($data, $depth),
        );
    }

    /**
     * @param scalar|array<mixed[]|scalar|null>|null $data
     *
     * @return scalar|array<mixed[]|scalar|null>|null
     */
    private function maskNormalized(mixed $data): mixed
    {
        $masked = $this->structuredDataMasker->mask($data);

        if (
            null !== $masked
            && !is_scalar($masked)
            && !is_array($masked)
        ) {
            throw new \LogicException('Normalized line formatter data must remain scalar, array, or null after masking.');
        }

        /* @var null|scalar|array<mixed[]|scalar|null> $masked */
        return $masked;
    }
}
