<?php

declare(strict_types=1);

namespace Masked\Bundle;

/**
 * @internal
 */
final readonly class Redactor
{
    private const string CHARACTER_ENCODING = 'UTF-8';

    /**
     * Mask characters must be standalone characters suitable for textual
     * output. Letters, numbers, punctuation and symbols are allowed.
     *
     * Unicode control, format, separator and combining-mark characters are
     * intentionally rejected.
     */
    private const string MASK_CHARACTER_PATTERN =
        '~\A[\p{L}\p{N}\p{P}\p{S}]\z~u';

    public function __construct(
        private string $maskCharacter = '█',
    ) {
        if (
            !mb_check_encoding(
                $this->maskCharacter,
                self::CHARACTER_ENCODING,
            )
            || 1 !== mb_strlen(
                $this->maskCharacter,
                self::CHARACTER_ENCODING,
            )
            || 1 !== preg_match(
                self::MASK_CHARACTER_PATTERN,
                $this->maskCharacter,
            )
        ) {
            throw new \InvalidArgumentException('The mask character must contain exactly one valid UTF-8 character from the letter, number, punctuation, or symbol categories.');
        }
    }

    public function redact(
        #[\SensitiveParameter]
        string $value,
        int $visibleTrailingCharacters = 0,
    ): string {
        if ($visibleTrailingCharacters < 0) {
            throw new \InvalidArgumentException('The number of visible trailing characters cannot be negative.');
        }

        if ('' === $value) {
            return '';
        }

        if (!mb_check_encoding($value, self::CHARACTER_ENCODING)) {
            /*
             * Character boundaries are undefined for malformed UTF-8.
             * Mask the complete byte sequence instead of exposing a
             * trailing portion that could start inside an invalid
             * multibyte sequence.
             */
            return str_repeat(
                $this->maskCharacter,
                strlen($value),
            );
        }

        $length = mb_strlen(
            $value,
            self::CHARACTER_ENCODING,
        );

        /*
         * If the requested visible part is equal to or longer than the
         * sensitive value, mask everything instead of exposing the value.
         */
        if (
            0 === $visibleTrailingCharacters
            || $visibleTrailingCharacters >= $length
        ) {
            $redacted = str_repeat(
                $this->maskCharacter,
                $length,
            );
        } else {
            $visible = mb_substr(
                $value,
                -$visibleTrailingCharacters,
                null,
                self::CHARACTER_ENCODING,
            );

            $redacted = str_repeat(
                $this->maskCharacter,
                $length - $visibleTrailingCharacters,
            ).$visible;
        }

        /*
         * A sensitive value may already consist of the configured mask
         * character, or its masked prefix may already look exactly like the
         * generated output.
         *
         * Never return a non-empty sensitive value byte-for-byte unchanged.
         * Prefixing one additional mask character keeps the configured
         * masking style and preserves any explicitly visible trailing part.
         */
        if ($redacted === $value) {
            return $this->maskCharacter.$redacted;
        }

        return $redacted;
    }
}
