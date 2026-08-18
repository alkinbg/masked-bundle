<?php

declare(strict_types=1);

namespace Masked\Bundle\Monolog;

use Masked\Bundle\StructuredDataMasker;
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
        #[\SensitiveParameter]
        mixed $data,
        int $depth = 0,
    ): mixed {
        return $this->maskNormalizedTree(
            parent::normalize(
                $data,
                $depth,
            ),
        );
    }

    /**
     * Masks the complete normalized JSON tree.
     *
     * Monolog may leave objects inside values returned by
     * JsonSerializable::jsonSerialize(). Those objects must be normalized
     * and masked before the final JSON document is encoded.
     *
     * @return scalar|array<mixed, mixed>|\stdClass|null
     *
     * @throws \JsonException
     */
    private function maskNormalizedTree(
        #[\SensitiveParameter]
        mixed $data,
    ): mixed {
        if ($data instanceof \stdClass) {
            return $this->maskJsonObject($data);
        }

        if (is_object($data)) {
            return $this->maskJsonRepresentation($data);
        }

        if (is_array($data)) {
            $normalized = [];

            foreach ($data as $key => $value) {
                $normalized[$key] = $this->maskNormalizedTree(
                    $value,
                );
            }

            return $this->maskScalarOrArray($normalized);
        }

        if (
            null !== $data
            && !is_scalar($data)
        ) {
            throw new \LogicException('Normalized JSON data must contain only objects, arrays, scalars, or null.');
        }

        return $this->maskScalarOrArray($data);
    }

    /**
     * @return scalar|array<mixed, mixed>|\stdClass|null
     *
     * @throws \JsonException
     */
    private function maskJsonRepresentation(
        #[\SensitiveParameter]
        object $data,
    ): mixed {
        $decoded = json_decode(
            $this->toJson($data, true),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $this->maskNormalizedTree($decoded);
    }

    /**
     * @throws \JsonException
     */
    private function maskJsonObject(
        #[\SensitiveParameter]
        \stdClass $data,
    ): \stdClass {
        $properties = [];

        foreach (get_object_vars($data) as $key => $value) {
            $properties[$key] = $this->maskNormalizedTree(
                $value,
            );
        }

        $maskedProperties = $this->structuredDataMasker->mask(
            $properties,
        );

        if (!is_array($maskedProperties)) {
            throw new \LogicException('Masked JSON object properties must remain an array.');
        }

        return (object) $maskedProperties;
    }

    /**
     * @param scalar|array<mixed, mixed>|null $data
     *
     * @return scalar|array<mixed, mixed>|null
     */
    private function maskScalarOrArray(
        #[\SensitiveParameter]
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
