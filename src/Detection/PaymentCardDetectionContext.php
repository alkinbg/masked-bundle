<?php

declare(strict_types=1);

namespace Masked\Bundle\Detection;

/**
 * Holds the candidate-validation work budget shared by one payment-card
 * detection operation.
 *
 * @internal
 */
final class PaymentCardDetectionContext
{
    private const int MAX_CANDIDATE_CHECKS = 10000;

    private int $remainingCandidateChecks =
        self::MAX_CANDIDATE_CHECKS;

    private bool $failClosed = false;

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function isFailClosed(): bool
    {
        return $this->failClosed;
    }

    /**
     * Consumes one payment-card candidate validation.
     *
     * Once the shared budget is exhausted, the complete operation
     * permanently enters fail-closed state.
     */
    public function consumeCandidateCheck(): bool
    {
        if ($this->failClosed) {
            return false;
        }

        if ($this->remainingCandidateChecks <= 0) {
            $this->enterFailClosedState();

            return false;
        }

        --$this->remainingCandidateChecks;

        return true;
    }

    private function enterFailClosedState(): void
    {
        $this->failClosed = true;
        $this->remainingCandidateChecks = 0;
    }
}
