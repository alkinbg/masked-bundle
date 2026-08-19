# MaskedBundle

MaskedBundle provides sensitive-data detection and masking services for modern
Symfony applications.

It combines two complementary approaches:

- automatic detection where sensitive data can be recognized with sufficient
  confidence;
- explicit sensitive values supplied by the application for the current
  operation.

The current automatic detector focuses on payment-card numbers found in free
text.

## Requirements

- PHP 8.4.1 or newer
- Symfony 8.1 or newer
- `ext-mbstring`

Monolog integration is optional.

The Monolog integrations are tested with `monolog/monolog ^3.6` and are
registered only for Monolog API 3.

## Installation

Install the bundle with Composer:

```bash
composer require alkinbg/masked-bundle
```

If Symfony Flex does not register the bundle automatically, add it to
`config/bundles.php`:

```php
<?php

return [
    Masked\Bundle\MaskedBundle::class => ['all' => true],
];
```

## Configuration

No configuration is required.

The default mask character is:

```text
█
```

It can be changed in `config/packages/masked.yaml`:

```yaml
masked:
    mask_character: '*'
```

The value must contain exactly one valid UTF-8 letter, number, punctuation
mark or symbol. Unicode control, format, separator and combining mark
characters are rejected so masking cannot introduce line breaks, control
characters or invisible spacing.
For valid UTF-8 values, masking normally keeps the same number of characters.
If the generated masked value would be identical to the original sensitive
value, one additional mask character is prefixed. This ensures that a
non-empty value which was actually redacted is never returned unchanged.

## Masking strings

`Masked\Bundle\SensitiveDataMasker` is the main service for masking strings.

```php
use Masked\Bundle\SensitiveDataMasker;

final class PaymentService
{
    public function __construct(
        private readonly SensitiveDataMasker $masker,
    ) {
    }

    public function mask(string $value): string
    {
        return $this->masker->mask($value);
    }
}
```

For example:

```php
$masked = $masker->mask(
    'Card: 4111111111111111',
);
```

produces:

```text
Card: ████████████████
```

## Explicit sensitive values

Secrets that cannot be detected reliably should be supplied explicitly.

Typical examples include:

- passwords;
- API tokens;
- refresh tokens;
- application-specific credentials.

```php
$token = 'secret-access-token';

$masked = $masker->mask(
    'Authentication failed for token '.$token,
    sensitiveValues: [$token],
);
```

Explicit values are matched exactly and case-sensitively.

They exist only for the current masking operation. MaskedBundle does not keep a
shared mutable or process-wide secret registry.

Automatic detection and explicit values can be combined in the same call.

Exact-value detection uses bounded search budgets for pathological inputs. If a
search budget is exhausted while scanning a string, that current string is
treated as sensitive rather than returning a partially scanned value.

Explicit-value detection accepts at most 1,000 supplied values and at most
1 MiB of supplied sensitive-value data per operation, including duplicates.

Substring searching is limited to 10,000 operations, 64 MiB of aggregate
search windows, and a conservative search-work budget of 1,073,741,824 units, calculated from
both input-window and sensitive-value length.

For structured masking, these exact-value search budgets are shared across all
supported scalar values and array keys in the complete traversal.
SensitiveLogger also shares one exact-value detection context between the log
message and context.

If any explicit-value resource limit is exceeded, exact-value detection enters
fail-closed state for the remainder of that masking operation. Supported string
and integer values processed after that point are treated as sensitive rather
than receiving a fresh search budget.

## Structured data

`Masked\Bundle\StructuredDataMasker` recursively masks supported scalar values
inside arrays.

```php
use Masked\Bundle\StructuredDataMasker;

$masked = $structuredDataMasker->mask([
    'customer' => [
        'card' => '4111111111111111',
    ],
]);
```

String array keys are also checked.

Objects are deliberately preserved rather than traversed or mutated.

Recursive arrays, excessive nesting and unusually large array structures are
bounded defensively.

A single array is limited to 1,000 processed entries and one structured masking
operation is limited to 10,000 processed array entries in total. When a limit
is reached, remaining unprocessed data is omitted and represented by a safe
placeholder rather than being returned without masking.

## Payment-card detection

Automatic payment-card detection is intentionally conservative.

Candidates:

- contain 13 to 19 ASCII digits;
- may contain spaces, horizontal tabs, hyphens or UTF-8 non-breaking spaces
  between digit groups;
- must pass the Luhn algorithm;
- must not consist of one repeated digit.

Payment-card candidates are scanned incrementally without materializing all
numeric sequences or digit groups in memory. Candidate validation is limited
to 10,000 checks per masking operation.

Each call to `SensitiveDataMasker::mask()` starts a fresh candidate-validation
budget. `StructuredDataMasker` shares one budget across all supported scalar
values and array keys in the complete traversal. `SensitiveLogger` shares one
budget between the log message and structured context.

If the budget is exhausted, the complete current input is treated as sensitive.
The same fail-closed state applies to later supported scalar values and array
keys processed by the same structured masking or sensitive logging operation.

The detector can find multiple payment-card numbers in the same string and is
safe with multibyte surrounding text.

The detector does not perform card-brand, BIN or IIN identification.

## SensitiveLogger

`Masked\Bundle\Logging\SensitiveLogger` is intended for log calls where the
application knows specific sensitive values.

```php
use Masked\Bundle\Logging\SensitiveLogger;
use Psr\Log\LoggerInterface;

final class AuthenticationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SensitiveLogger $sensitiveLogger,
    ) {
    }

    public function reportFailure(string $token): void
    {
        $this->sensitiveLogger->log(
            $this->logger,
            'warning',
            'Authentication failed for token '.$token,
            [
                'token' => $token,
            ],
            sensitiveValues: [$token],
        );
    }
}
```

The message and supported structured context values are masked before the
record is delegated to the underlying PSR-3 logger.
The message and context share the same exact-value search budgets and
payment-card candidate-validation budget.

## Monolog integration

MaskedBundle provides three Monolog integration services:

```text
Masked\Bundle\Monolog\SensitiveDataProcessor
Masked\Bundle\Monolog\SensitiveLineFormatter
Masked\Bundle\Monolog\SensitiveJsonFormatter
```

The integration is optional and is activated only for Monolog API 3.

The currently tested dependency range is:

```text
monolog/monolog ^3.6
```

Future Monolog major APIs are not activated automatically until their
compatibility has been explicitly verified.

### Processor

The processor automatically masks detectable sensitive data in:

- messages;
- scalar and array values in `context`;
- scalar and array values in `extra`.

Arbitrary objects are deliberately preserved so their identity and behavior are
not changed before downstream Monolog processing.

### Line formatter

`SensitiveLineFormatter` delegates normal line formatting to Monolog and then
masks the resulting plain-text line.

This protects sensitive data that appears during normalization or rendering,
including rendered object and exception representations.

### JSON formatter

`SensitiveJsonFormatter` masks normalized data and JSON object representations
before the complete log record is encoded as JSON.

It deliberately does not post-process an already serialized JSON document.
Formatter masking has its own bounded traversal. At most 1,000 entries are
processed per container, nesting is limited to 32 masking levels, and a single
`format()` or `formatBatch()` operation processes at most 10,000 entries in
total.

If a masking limit is reached, unprocessed normalized data is omitted and a
safe placeholder is emitted instead.

## Service architecture

Application-facing services are available through their PHP class names:

```text
Masked\Bundle\SensitiveDataMasker
Masked\Bundle\StructuredDataMasker
Masked\Bundle\Logging\SensitiveLogger
```

Monolog integration classes are also exposed through class aliases.

Implementation-level services use private `.masked.*` service IDs and should
not be treated as application API.

The supported public PHP API consists of:

- `Masked\Bundle\MaskedBundle`
- `Masked\Bundle\SensitiveDataMasker`
- `Masked\Bundle\StructuredDataMasker`
- `Masked\Bundle\Logging\SensitiveLogger`
- `Masked\Bundle\Monolog\SensitiveDataProcessor`
- `Masked\Bundle\Monolog\SensitiveLineFormatter`
- `Masked\Bundle\Monolog\SensitiveJsonFormatter`

Lower-level redaction and detection classes are marked `@internal`. They are
autoloadable implementation details rather than supported extension points,
and applications should not depend on their concrete API.

## Security considerations

MaskedBundle is a defensive redaction component.

It is not:

- encryption;
- tokenization;
- a payment-card vault;
- a substitute for access control;
- a guarantee of PCI DSS compliance.

Automatic detection is heuristic.

Applications should explicitly provide known secrets instead of assuming that
all sensitive information can be recognized automatically.
Range redaction bounds normalization to 10,000 supplied match ranges. All
ranges are validated first; if more valid ranges are supplied, the complete
input is redacted rather than sorting an unbounded match list.

Custom processors, handlers and formatters can introduce or serialize new data
after MaskedBundle has processed a record. Applications with custom logging
pipelines should review those components independently.

MaskedBundle marks package-owned parameters that may contain unmasked sensitive
data with PHP's `#[\\SensitiveParameter]` attribute so those argument values are
redacted from stack frames owned by the bundle.

The attribute only protects the parameter at the frame where it is declared.
Applications that require exception traces to contain no function arguments at
all should additionally configure PHP with `zend.exception_ignore_args=1`.

## Project lineage

MaskedBundle is a modern Symfony-oriented project inspired by
[Fuko\Masked](https://github.com/fuko-php/masked), created by Kaloyan K.
Tsvetkov.

It is not a drop-in port and does not preserve the original API.

Payment-card detection has historical lineage to earlier work contributed to
Fuko\Masked.

Full attribution and the relevant MIT license notice are documented in
[THIRD_PARTY_NOTICES.md](../THIRD_PARTY_NOTICES.md).

## Development

Run the tests:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Check coding standards:

```bash
composer format:check
```

Validate Composer metadata:

```bash
composer validate --strict
```
