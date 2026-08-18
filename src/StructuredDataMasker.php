<?php

declare(strict_types=1);

namespace Masked;

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

    /**
     * @param list<string> $sensitiveValues
     */
    public function mask(
        mixed $value,
        array $sensitiveValues = [],
    ): mixed {
        $this->validateSensitiveValues($sensitiveValues);

        return $this->maskValue(
            $value,
            $sensitiveValues,
            [],
            0,
        );
    }

    /**
     * @param list<string>        $sensitiveValues
     * @param array<string, true> $activeArrayReferenceIds
     */
    private function maskValue(
        mixed $value,
        array $sensitiveValues,
        array $activeArrayReferenceIds,
        int $arrayDepth,
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
        array $value,
        array $sensitiveValues,
        array $activeArrayReferenceIds,
        int $arrayDepth,
    ): array {
        $masked = [];

        foreach ($value as $key => $item) {
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
                    );

                    continue;
                }
            }

            $masked[$maskedKey] = $this->maskValue(
                $item,
                $sensitiveValues,
                $activeArrayReferenceIds,
                $arrayDepth + 1,
            );
        }

        return $masked;
    }

    /**
     * @param list<string> $sensitiveValues
     */
    private function maskArrayKey(
        int|string $key,
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
        array $sensitiveValues,
    ): void {
        foreach ($sensitiveValues as $sensitiveValue) {
            if ('' === $sensitiveValue) {
                throw new \InvalidArgumentException('Sensitive values must not contain an empty string.');
            }
        }
    }
}
