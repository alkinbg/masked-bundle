<?php

declare(strict_types=1);

namespace Masked\Detection;

/**
 * Represents the byte range of sensitive data detected inside a string.
 *
 * Offsets and lengths are expressed in bytes so they can be used safely
 * with offsets returned by PCRE using PREG_OFFSET_CAPTURE.
 */
final readonly class SensitiveDataMatch
{
    public function __construct(
        public int $byteOffset,
        public int $byteLength,
    ) {
        if ($this->byteOffset < 0) {
            throw new \InvalidArgumentException('The byte offset cannot be negative.');
        }

        if ($this->byteLength < 1) {
            throw new \InvalidArgumentException('The byte length must be greater than zero.');
        }
    }

    public function endByteOffsetExclusive(): int
    {
        return $this->byteOffset + $this->byteLength;
    }
}
