<?php

declare(strict_types=1);

namespace Masked\Bundle;

final readonly class StructuredDataMasker
{
    private const int MAX_ARRAY_NESTING_DEPTH = 32;

    private const int MAX_ARRAY_ITEM_COUNT = 1000;

    private const int MAX_TOTAL_ARRAY_ITEMS = 10000;

    private const string RECURSIVE_ARRAY_PLACEHOLDER = '[recursive array]';

    private const string MAXIMUM_NESTING_DEPTH_PLACEHOLDER = '[maximum nesting depth exceeded]';

    private const string MAXIMUM_ARRAY_ITEM_COUNT_PLACEHOLDER = '[maximum array item count exceeded]';

    private const string MAXIMUM_WORK_BUDGET_PLACEHOLDER = '[maximum masking work budget exceeded]';

    public function __construct(
        private SensitiveDataMasker $sensitiveDataMasker =
        new SensitiveDataMasker(),
    ) {
    }

    /**
     * @param list<string> $sensitiveValues
     */
    public function mask(
        #[\SensitiveParameter]
        mixed $value,
        #[\SensitiveParameter]
        array $sensitiveValues = [],
    ): mixed {
        $this->validateSensitiveValues($sensitiveValues);

        $remainingArrayItems = self::MAX_TOTAL_ARRAY_ITEMS;

        return $this->maskValue(
            $value,
            $sensitiveValues,
            [],
            0,
            $remainingArrayItems,
        );
    }

    /**
     * @param list<string>        $sensitiveValues
     * @param array<string, true> $activeArrayReferenceIds
     */
    private function maskValue(
        #[\SensitiveParameter]
        mixed $value,
        #[\SensitiveParameter]
        array $sensitiveValues,
        array $activeArrayReferenceIds,
        int $arrayDepth,
        int &$remainingArrayItems,
    ): mixed {
        if (is_string($value)) {
            return $this->sensitiveDataMasker->mask(
                $value,
                $sensitiveValues,
            );
        }

        if (is_int($value)) {
            $valueAsString = (string) $value;

            $masked = $this->sensitiveDataMasker->mask(
                $valueAsString,
                $sensitiveValues,
            );

            if ($masked !== $valueAsString) {
                return $masked;
            }

            return $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        if ($arrayDepth >= self::MAX_ARRAY_NESTING_DEPTH) {
            return self::MAXIMUM_NESTING_DEPTH_PLACEHOLDER;
        }

        return $this->maskArray(
            $value,
            $sensitiveValues,
            $activeArrayReferenceIds,
            $arrayDepth,
            $remainingArrayItems,
        );
    }

    /**
     * @param array<int|string, mixed> $value
     * @param list<string>             $sensitiveValues
     * @param array<string, true>      $activeArrayReferenceIds
     *
     * @return array<int|string, mixed>
     */
    private function maskArray(
        #[\SensitiveParameter]
        array $value,
        #[\SensitiveParameter]
        array $sensitiveValues,
        array $activeArrayReferenceIds,
        int $arrayDepth,
        int &$remainingArrayItems,
    ): array {
        $masked = [];
        $processedItems = 0;

        foreach ($value as $key => $item) {
            if ($processedItems >= self::MAX_ARRAY_ITEM_COUNT) {
                $this->appendTruncationPlaceholder(
                    $masked,
                    self::MAXIMUM_ARRAY_ITEM_COUNT_PLACEHOLDER,
                );

                break;
            }

            if ($remainingArrayItems <= 0) {
                $this->appendTruncationPlaceholder(
                    $masked,
                    self::MAXIMUM_WORK_BUDGET_PLACEHOLDER,
                );

                break;
            }

            ++$processedItems;
            --$remainingArrayItems;

            $maskedKey = $this->maskArrayKey(
                $key,
                $sensitiveValues,
            );

            $maskedKey = $this->ensureUniqueArrayKey(
                $maskedKey,
                $masked,
            );

            if (is_array($item)) {
                $reference = \ReflectionReference::fromArrayElement(
                    $value,
                    $key,
                );

                if (null !== $reference) {
                    $referenceId = 'ref:'.$reference->getId();

                    if (isset($activeArrayReferenceIds[$referenceId])) {
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
                        $sensitiveValues,
                        $nestedActiveArrayReferenceIds,
                        $arrayDepth + 1,
                        $remainingArrayItems,
                    );

                    continue;
                }
            }

            $masked[$maskedKey] = $this->maskValue(
                $item,
                $sensitiveValues,
                $activeArrayReferenceIds,
                $arrayDepth + 1,
                $remainingArrayItems,
            );
        }

        return $masked;
    }

    /**
     * Appends a truncation marker without exposing any unprocessed input.
     *
     * Lists remain lists. Associative arrays use a unique textual marker key
     * so existing application data is never overwritten.
     *
     * @param array<int|string, mixed> $masked
     */
    private function appendTruncationPlaceholder(
        array &$masked,
        string $placeholder,
    ): void {
        if (array_is_list($masked)) {
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
     * @param list<string> $sensitiveValues
     */
    private function maskArrayKey(
        #[\SensitiveParameter]
        int|string $key,
        #[\SensitiveParameter]
        array $sensitiveValues,
    ): int|string {
        $keyAsString = (string) $key;

        $maskedKey = $this->sensitiveDataMasker->mask(
            $keyAsString,
            $sensitiveValues,
        );

        if ($maskedKey === $keyAsString) {
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

    /**
     * @param list<string> $sensitiveValues
     */
    private function validateSensitiveValues(
        #[\SensitiveParameter]
        array $sensitiveValues,
    ): void {
        foreach ($sensitiveValues as $sensitiveValue) {
            if ('' === $sensitiveValue) {
                throw new \InvalidArgumentException('Sensitive values must not contain an empty string.');
            }
        }
    }
}
