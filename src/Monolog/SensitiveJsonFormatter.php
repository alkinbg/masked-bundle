<?php

declare(strict_types=1);

namespace Masked\Bundle\Monolog;

use Masked\Bundle\StructuredDataMasker;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

final class SensitiveJsonFormatter extends JsonFormatter
{
    private const int MAX_JSON_NESTING_DEPTH = 32;

    private const int MAX_JSON_ITEM_COUNT = 1000;

    private const int MAX_TOTAL_JSON_ITEMS = 10000;

    private const string MAXIMUM_NESTING_DEPTH_PLACEHOLDER =
        '[maximum JSON masking nesting depth exceeded]';

    private const string MAXIMUM_ITEM_COUNT_PLACEHOLDER =
        '[maximum JSON masking item count exceeded]';

    private const string MAXIMUM_WORK_BUDGET_PLACEHOLDER =
        '[maximum JSON masking work budget exceeded]';

    private ?int $remainingMaskingItems = null;

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

    #[\Override]
    public function format(
        #[\SensitiveParameter]
        LogRecord $record,
    ): string {
        $startedMaskingOperation = $this->startMaskingOperation();

        try {
            return parent::format($record);
        } finally {
            if ($startedMaskingOperation) {
                $this->finishMaskingOperation();
            }
        }
    }

    /**
     * @param array<LogRecord> $records
     */
    #[\Override]
    public function formatBatch(
        #[\SensitiveParameter]
        array $records,
    ): string {
        $startedMaskingOperation = $this->startMaskingOperation();

        try {
            return parent::formatBatch($records);
        } finally {
            if ($startedMaskingOperation) {
                $this->finishMaskingOperation();
            }
        }
    }

    /**
     * @return scalar|array<mixed, mixed>|object|null
     *
     * @throws \JsonException
     */
    #[\Override]
    protected function normalize(
        #[\SensitiveParameter]
        mixed $data,
        int $depth = 0,
    ): mixed {
        $startedMaskingOperation = $this->startMaskingOperation();
        $isRootNormalization = 0 === $depth;

        try {
            $normalized = parent::normalize(
                $data,
                $depth,
            );

            if (!$isRootNormalization) {
                return $normalized;
            }

            return $this->maskNormalizedTree(
                $normalized,
                0,
            );
        } finally {
            if ($startedMaskingOperation) {
                $this->finishMaskingOperation();
            }
        }
    }

    /**
     * Masks one normalized JSON tree using the work budget shared by the
     * surrounding format or formatBatch operation.
     *
     * Monolog may leave objects inside values returned by
     * JsonSerializable::jsonSerialize(). Those objects are converted to
     * their JSON representation and masked before the final document is
     * encoded.
     *
     * @return scalar|array<mixed, mixed>|\stdClass|null
     *
     * @throws \JsonException
     */
    private function maskNormalizedTree(
        #[\SensitiveParameter]
        mixed $data,
        int $containerDepth,
    ): mixed {
        if ($data instanceof \stdClass) {
            if ($containerDepth >= self::MAX_JSON_NESTING_DEPTH) {
                return self::MAXIMUM_NESTING_DEPTH_PLACEHOLDER;
            }

            return $this->maskJsonObject(
                $data,
                $containerDepth,
            );
        }

        if (is_object($data)) {
            return $this->maskJsonRepresentation(
                $data,
                $containerDepth,
            );
        }

        if (is_array($data)) {
            if ($containerDepth >= self::MAX_JSON_NESTING_DEPTH) {
                return self::MAXIMUM_NESTING_DEPTH_PLACEHOLDER;
            }

            return $this->maskJsonArray(
                $data,
                $containerDepth,
            );
        }

        if (
            null !== $data
            && !is_scalar($data)
        ) {
            throw new \LogicException('Normalized JSON data must contain only objects, arrays, scalars, or null.');
        }

        return $this->maskScalar($data);
    }

    /**
     * @return scalar|array<mixed, mixed>|\stdClass|null
     *
     * @throws \JsonException
     */
    private function maskJsonRepresentation(
        #[\SensitiveParameter]
        object $data,
        int $containerDepth,
    ): mixed {
        $decoded = json_decode(
            $this->toJson($data, true),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $this->maskNormalizedTree(
            $decoded,
            $containerDepth,
        );
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @return array<mixed, mixed>
     *
     * @throws \JsonException
     */
    private function maskJsonArray(
        #[\SensitiveParameter]
        array $data,
        int $containerDepth,
    ): array {
        $masked = [];
        $processedItems = 0;
        $isList = array_is_list($data);

        foreach ($data as $key => $value) {
            if ($processedItems >= self::MAX_JSON_ITEM_COUNT) {
                $this->appendArrayTruncationPlaceholder(
                    $masked,
                    self::MAXIMUM_ITEM_COUNT_PLACEHOLDER,
                    $isList,
                );

                break;
            }

            if (!$this->consumeMaskingItem()) {
                $this->appendArrayTruncationPlaceholder(
                    $masked,
                    self::MAXIMUM_WORK_BUDGET_PLACEHOLDER,
                    $isList,
                );

                break;
            }

            ++$processedItems;

            $maskedKey = $this->maskJsonKey($key);
            $maskedKey = $this->ensureUniqueArrayKey(
                $maskedKey,
                $masked,
            );

            $masked[$maskedKey] = $this->maskNormalizedTree(
                $value,
                $containerDepth + 1,
            );
        }

        return $masked;
    }

    /**
     * @throws \JsonException
     */
    private function maskJsonObject(
        #[\SensitiveParameter]
        \stdClass $data,
        int $containerDepth,
    ): \stdClass {
        $maskedProperties = [];
        $processedItems = 0;

        foreach (get_object_vars($data) as $key => $value) {
            if ($processedItems >= self::MAX_JSON_ITEM_COUNT) {
                $this->appendObjectTruncationPlaceholder(
                    $maskedProperties,
                    self::MAXIMUM_ITEM_COUNT_PLACEHOLDER,
                );

                break;
            }

            if (!$this->consumeMaskingItem()) {
                $this->appendObjectTruncationPlaceholder(
                    $maskedProperties,
                    self::MAXIMUM_WORK_BUDGET_PLACEHOLDER,
                );

                break;
            }

            ++$processedItems;

            $maskedKey = $this->maskJsonKey($key);
            $maskedKey = (string) $this->ensureUniqueArrayKey(
                $maskedKey,
                $maskedProperties,
            );

            $maskedProperties[$maskedKey] = $this->maskNormalizedTree(
                $value,
                $containerDepth + 1,
            );
        }

        return (object) $maskedProperties;
    }

    private function maskJsonKey(
        #[\SensitiveParameter]
        int|string $key,
    ): int|string {
        $maskedKey = $this->structuredDataMasker->mask($key);

        if (!is_int($maskedKey) && !is_string($maskedKey)) {
            throw new \LogicException('Masked JSON keys must remain integers or strings.');
        }

        return $maskedKey;
    }

    /**
     * @param scalar|null $data
     *
     * @return scalar|null
     */
    private function maskScalar(
        #[\SensitiveParameter]
        mixed $data,
    ): mixed {
        $masked = $this->structuredDataMasker->mask($data);

        if (
            null !== $masked
            && !is_scalar($masked)
        ) {
            throw new \LogicException('Masked JSON scalar data must remain scalar or null.');
        }

        /** @var scalar|null $masked */
        return $masked;
    }

    /**
     * @param array<mixed, mixed> $masked
     */
    private function appendArrayTruncationPlaceholder(
        array &$masked,
        string $placeholder,
        bool $isList,
    ): void {
        if ($isList) {
            $masked[] = $placeholder;

            return;
        }

        $placeholderKey = $this->ensureUniqueArrayKey(
            '...',
            $masked,
        );

        $masked[$placeholderKey] = $placeholder;
    }

    /**
     * @param array<string, mixed> $maskedProperties
     */
    private function appendObjectTruncationPlaceholder(
        array &$maskedProperties,
        string $placeholder,
    ): void {
        $placeholderKey = (string) $this->ensureUniqueArrayKey(
            '...',
            $maskedProperties,
        );

        $maskedProperties[$placeholderKey] = $placeholder;
    }

    /**
     * @param array<mixed, mixed> $masked
     */
    private function ensureUniqueArrayKey(
        int|string $key,
        array $masked,
    ): int|string {
        if (!array_key_exists($key, $masked)) {
            return $key;
        }

        $baseKey = (string) $key;
        $suffix = 2;

        do {
            $candidate = $baseKey.'#'.$suffix;
            ++$suffix;
        } while (array_key_exists($candidate, $masked));

        return $candidate;
    }

    private function startMaskingOperation(): bool
    {
        if (null !== $this->remainingMaskingItems) {
            return false;
        }

        $this->remainingMaskingItems = self::MAX_TOTAL_JSON_ITEMS;

        return true;
    }

    private function finishMaskingOperation(): void
    {
        $this->remainingMaskingItems = null;
    }

    private function consumeMaskingItem(): bool
    {
        if (null === $this->remainingMaskingItems) {
            throw new \LogicException('JSON masking work must run inside a masking operation.');
        }

        if ($this->remainingMaskingItems <= 0) {
            return false;
        }

        --$this->remainingMaskingItems;

        return true;
    }
}
