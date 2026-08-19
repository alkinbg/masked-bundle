<?php

declare(strict_types=1);

namespace Masked\Bundle\Detection;

/**
 * Holds prepared explicit sensitive values and shared search-work budgets for
 * one exact-value masking operation.
 *
 * @internal
 */
final class ExactValueDetectionContext
{
    private const int MAX_SENSITIVE_VALUE_COUNT = 1000;

    private const int MAX_TOTAL_SENSITIVE_VALUE_BYTES =
        1024 * 1024;

    private const int MAX_SEARCH_OPERATIONS = 10000;

    private const int MAX_SEARCH_WINDOW_BYTES =
        64 * 1024 * 1024;

    /*
     * Conservative upper bound for substring-search byte-comparison work.
     *
     * This complements the aggregate search-window budget by accounting for
     * both haystack and needle length.
     */
    private const int MAX_SEARCH_WORK_UNITS =
        1024 * 1024 * 1024;

    /**
     * Unique sensitive values sorted by nondecreasing byte length.
     *
     * @var list<string>
     */
    private array $sensitiveValues = [];

    private int $remainingSearchOperations =
        self::MAX_SEARCH_OPERATIONS;

    private int $remainingSearchWindowBytes =
        self::MAX_SEARCH_WINDOW_BYTES;

    private int $remainingSearchWorkUnits =
        self::MAX_SEARCH_WORK_UNITS;

    private bool $failClosed = false;

    private function __construct()
    {
    }

    /**
     * Creates one exact-value detection context for a masking operation.
     *
     * Supplied-value count and total supplied bytes are bounded before
     * deduplication so preparing explicit secrets cannot itself become an
     * unbounded operation.
     *
     * Duplicate values count towards the supplied-byte budget but are removed
     * from the prepared search list because rescanning for an identical value
     * cannot discover additional ranges.
     *
     * Prepared values are sorted by byte length so a detector can stop as soon
     * as the remaining values are longer than the current input.
     *
     * For inputs within the supplied-value count limit, an empty explicit
     * sensitive value always remains a programmer error.
     *
     * @param list<string> $sensitiveValues
     */
    public static function create(
        #[\SensitiveParameter]
        array $sensitiveValues,
    ): self {
        $context = new self();

        if ([] === $sensitiveValues) {
            return $context;
        }

        if (
            count($sensitiveValues)
            > self::MAX_SENSITIVE_VALUE_COUNT
        ) {
            $context->enterFailClosedState();

            return $context;
        }

        /*
         * This pass is bounded by MAX_SENSITIVE_VALUE_COUNT and preserves the
         * empty-value programmer-error contract independently of value order.
         */
        foreach ($sensitiveValues as $sensitiveValue) {
            if ('' === $sensitiveValue) {
                throw new \InvalidArgumentException('Sensitive values must not contain an empty string.');
            }
        }

        $seenSensitiveValues = [];
        $totalSensitiveValueBytes = 0;

        foreach ($sensitiveValues as $sensitiveValue) {
            $sensitiveValueByteLength =
                strlen($sensitiveValue);

            if (
                $sensitiveValueByteLength
                > self::MAX_TOTAL_SENSITIVE_VALUE_BYTES
                || $totalSensitiveValueBytes
                > self::MAX_TOTAL_SENSITIVE_VALUE_BYTES
                - $sensitiveValueByteLength
            ) {
                $context->enterFailClosedState();

                return $context;
            }

            $totalSensitiveValueBytes +=
                $sensitiveValueByteLength;

            /*
             * Prefix the key so PHP never interprets a numeric-looking
             * sensitive value as an integer array key.
             *
             * The cumulative supplied-byte budget is checked before this copy
             * is created, so preparation remains bounded.
             */
            $seenKey = "\0".$sensitiveValue;

            if (isset($seenSensitiveValues[$seenKey])) {
                continue;
            }

            $seenSensitiveValues[$seenKey] = true;
            $context->sensitiveValues[] = $sensitiveValue;
        }

        usort(
            $context->sensitiveValues,
            static fn (
                string $left,
                string $right,
            ): int => strlen($left) <=> strlen($right),
        );

        return $context;
    }

    /**
     * @return list<string>
     */
    public function sensitiveValues(): array
    {
        return $this->sensitiveValues;
    }

    public function isFailClosed(): bool
    {
        return $this->failClosed;
    }

    /**
     * Consumes one substring-search operation.
     *
     * Three independent budgets are charged before strpos() runs:
     *
     * - number of substring-search operations;
     * - aggregate haystack windows;
     * - conservative byte-comparison work derived from candidate positions and
     *   needle length.
     *
     * Once any budget is exhausted, the complete masking operation
     * permanently enters fail-closed state.
     */
    public function consumeSearch(
        int $searchWindowBytes,
        int $sensitiveValueByteLength,
    ): bool {
        if ($searchWindowBytes < 1) {
            throw new \LogicException('Exact-value search windows must contain at least one byte.');
        }

        if ($sensitiveValueByteLength < 1) {
            throw new \LogicException('Exact-value search needles must contain at least one byte.');
        }

        if (
            $sensitiveValueByteLength
            > $searchWindowBytes
        ) {
            throw new \LogicException('Exact-value search needles cannot exceed the search window.');
        }

        if ($this->failClosed) {
            return false;
        }

        if (
            $this->remainingSearchOperations <= 0
            || $searchWindowBytes
            > $this->remainingSearchWindowBytes
            || $this->wouldExceedSearchWorkBudget(
                $searchWindowBytes,
                $sensitiveValueByteLength,
            )
        ) {
            $this->enterFailClosedState();

            return false;
        }

        --$this->remainingSearchOperations;

        $this->remainingSearchWindowBytes -=
            $searchWindowBytes;

        $candidatePositions =
            $searchWindowBytes
            - $sensitiveValueByteLength
            + 1;

        /*
         * wouldExceedSearchWorkBudget() guarantees that the multiplication
         * below cannot exceed the remaining fixed budget or overflow.
         *
         * Charge one needle-length unit for search preparation plus the
         * conservative maximum comparison work across candidate positions.
         */
        $searchWorkUnits =
            $sensitiveValueByteLength
            + (
                $candidatePositions
                * $sensitiveValueByteLength
            );

        $this->remainingSearchWorkUnits -=
            $searchWorkUnits;

        return true;
    }

    private function wouldExceedSearchWorkBudget(
        int $searchWindowBytes,
        int $sensitiveValueByteLength,
    ): bool {
        if (
            $sensitiveValueByteLength
            > $this->remainingSearchWorkUnits
        ) {
            return true;
        }

        $remainingAfterPreparation =
            $this->remainingSearchWorkUnits
            - $sensitiveValueByteLength;

        $candidatePositions =
            $searchWindowBytes
            - $sensitiveValueByteLength
            + 1;

        return $candidatePositions
            > intdiv(
                $remainingAfterPreparation,
                $sensitiveValueByteLength,
            );
    }

    private function enterFailClosedState(): void
    {
        $this->failClosed = true;

        $this->remainingSearchOperations = 0;
        $this->remainingSearchWindowBytes = 0;
        $this->remainingSearchWorkUnits = 0;

        /*
         * No further exact-value searches will run in this operation. Drop
         * prepared secret references immediately rather than retaining them
         * for the remainder of the surrounding structured/logging traversal.
         */
        $this->sensitiveValues = [];
    }
}
