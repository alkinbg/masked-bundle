<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Monolog;

use Alkin\MaskedBundle\StructuredDataMasker;
use Monolog\Formatter\JsonFormatter;

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
     * @return scalar|array<mixed, mixed>|\stdClass|null
     *
     * @throws \JsonException
     */
    #[\Override]
    protected function normalize(
        mixed $data,
        int $depth = 0,
    ): mixed {
        $normalized = parent::normalize(
            $data,
            $depth,
        );

        if (is_object($normalized)) {
            return $this->normalizeJsonRepresentation($normalized);
        }

        return $this->maskNormalized($normalized);
    }

    /**
     * @return scalar|array<mixed, mixed>|\stdClass|null
     *
     * @throws \JsonException
     */
    private function normalizeJsonRepresentation(
        object $data,
    ): mixed {
        $decoded = json_decode(
            $this->toJson($data, true),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $this->maskDecodedJson($decoded);
    }

    /**
     * @return scalar|array<mixed, mixed>|\stdClass|null
     */
    private function maskDecodedJson(
        mixed $data,
    ): mixed {
        if ($data instanceof \stdClass) {
            $properties = [];

            foreach (get_object_vars($data) as $key => $value) {
                $properties[$key] = $this->maskDecodedJson($value);
            }

            $maskedProperties = $this->structuredDataMasker->mask(
                $properties,
            );

            if (!is_array($maskedProperties)) {
                throw new \LogicException('Masked JSON object properties must remain an array.');
            }

            return (object) $maskedProperties;
        }

        if (is_array($data)) {
            $values = [];

            foreach ($data as $key => $value) {
                $values[$key] = $this->maskDecodedJson($value);
            }

            return $this->maskNormalized($values);
        }

        if (
            null !== $data
            && !is_scalar($data)
        ) {
            throw new \LogicException('Decoded JSON data must contain only objects, arrays, scalars, or null.');
        }

        return $this->maskNormalized($data);
    }

    /**
     * @param scalar|array<mixed, mixed>|null $data
     *
     * @return scalar|array<mixed, mixed>|null
     */
    private function maskNormalized(
        mixed $data,
    ): mixed {
        $masked = $this->structuredDataMasker->mask($data);

        if (
            null !== $masked
            && !is_scalar($masked)
            && !is_array($masked)
        ) {
            throw new \LogicException('Normalized JSON formatter data must remain scalar, array, or null after masking.');
        }

        /** @var scalar|array<mixed, mixed>|null $masked */
        return $masked;
    }
}
