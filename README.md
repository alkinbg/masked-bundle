# MaskedBundle

[![CI](https://github.com/alkinbg/masked-bundle/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/alkinbg/masked-bundle/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.4.1%2B-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-8.1%2B-000000?logo=symfony&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

Sensitive data detection and masking for Symfony applications.

MaskedBundle provides reusable masking services for Symfony applications and
optional Monolog integration for masking sensitive data in logs and diagnostic
output.

It supports two complementary approaches:

- automatic detection for sensitive data that can be identified with sufficient confidence;
- explicit sensitive values supplied by the application when automatic detection is not appropriate.

The current automatic detector focuses on payment card numbers found in free
text.

## Requirements

- PHP 8.4.1 or newer
- Symfony 8.1 or newer
- `ext-mbstring`

Monolog integration is optional.

The Monolog integrations are tested with `monolog/monolog ^3.6` and are
registered only when Monolog API 3 is available. Other Monolog major APIs are
intentionally not activated until compatibility has been explicitly verified.

## Installation

Install the bundle with Composer:

```bash
composer require alkinbg/masked-bundle
```

If the bundle is not registered automatically, add it to
`config/bundles.php`:

```php
<?php

return [
    Masked\Bundle\MaskedBundle::class => ['all' => true],
];
```

For Symfony Monolog integration, install MonologBundle:

```bash
composer require symfony/monolog-bundle
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

The mask character must contain exactly one valid UTF-8 letter, number,
punctuation mark or symbol. Unicode control, format, separator and combining
mark characters are rejected so masking cannot introduce line breaks, control
characters or invisible spacing.

For valid UTF-8 values, masking normally keeps the same number of characters.
If the generated masked value would be identical to the original sensitive
value, one additional mask character is prefixed. This ensures that a
non-empty value which was actually redacted is never returned unchanged.

## Masking strings

`SensitiveDataMasker` is the primary service for masking sensitive data in
strings.

```php
use Masked\Bundle\SensitiveDataMasker;

final class PaymentService
{
    public function __construct(
        private readonly SensitiveDataMasker $masker,
    ) {
    }

    public function example(): string
    {
        return $this->masker->mask(
            'Card: 4111111111111111',
        );
    }
}
```

Result:

```text
Card: ████████████████
```

Strings without detected sensitive data are returned unchanged.

## Explicit sensitive values

Not every secret can or should be detected automatically.

Passwords, API tokens, refresh tokens and application-specific credentials are
examples of values that are better supplied explicitly.

`SensitiveDataMasker` accepts a list of sensitive values for the current
operation:

```php
$token = 'secret-access-token';

$masked = $sensitiveDataMasker->mask(
    'Authentication failed for token ' . $token,
    sensitiveValues: [$token],
);
```

Result:

```text
Authentication failed for token ███████████████████
```

Explicit values are matched exactly and case-sensitively.

They are supplied per operation. MaskedBundle does not keep them in a shared
static or mutable secret registry.

Automatic detection and explicit sensitive values can be used together in the
same call.

Exact-value detection uses bounded search budgets for pathological inputs. 
If continuing the scan would exceed a search budget while scanning a string, 
that current string is treated as sensitive rather than returning a partially scanned value.

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

## Masking structured data

`StructuredDataMasker` recursively processes strings and integers inside
arrays.

```php
use Masked\Bundle\StructuredDataMasker;

$masked = $structuredDataMasker->mask([
    'customer' => [
        'card' => '4111111111111111',
    ],
]);
```

Array keys are also checked for sensitive data.

Explicit values can be supplied in the same way:

```php
$token = 'secret-access-token';

$masked = $structuredDataMasker->mask(
    [
        'authentication' => [
            'token' => $token,
        ],
    ],
    sensitiveValues: [$token],
);
```

Objects are intentionally preserved and are not traversed or mutated by
`StructuredDataMasker`.

Recursive arrays, excessive nesting and unusually large array structures are
handled defensively. A single array is limited to 1,000 processed entries and
one structured masking operation is limited to 10,000 processed array entries
in total.

When either work limit is reached, remaining unprocessed data is omitted and a
safe placeholder is inserted. Unprocessed input is never copied into the
masked result.

## Payment card detection

Automatic payment-card detection uses a conservative free-text heuristic.

Currently it:

- detects candidates containing 13 to 19 ASCII digits;
- supports spaces, horizontal tabs, hyphens and UTF-8 non-breaking spaces between digit groups;
- validates candidates using the Luhn algorithm;
- rejects candidates consisting of a single repeated digit;
- supports multiple payment-card numbers in the same string;
- operates safely when surrounding text contains multibyte characters.

Payment-card candidates are scanned incrementally without materializing all
numeric sequences or digit groups in memory. Candidate validation is limited
to 10,000 checks per masking operation.

Each call to `SensitiveDataMasker::mask()` starts a fresh candidate-validation
budget. `StructuredDataMasker` shares one budget across all supported scalar
values and array keys in the complete traversal. `SensitiveLogger` shares one
budget between the log message and structured context.

`SensitiveDataProcessor` shares one candidate-validation budget across the
message, context and extra data of a complete `LogRecord`.
`SensitiveJsonFormatter` shares one budget across all supported scalar values
and keys processed by a single `format()` or `formatBatch()` operation.

If the budget is exhausted, the complete current input is treated as sensitive.
The same fail-closed state applies to later supported scalar values and array
keys processed within the same shared-budget operation.

The 13–19 digit range is a detector boundary, not a claim that it represents
every payment-card identifier permitted by every payment-card standard.

MaskedBundle does not perform card-brand, BIN or IIN identification.

## SensitiveLogger

`SensitiveLogger` is the recommended integration when an application knows
specific sensitive values at the point where a log entry is created.

It masks the message and structured context before delegating them to any
PSR-3 logger.

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

    public function reportFailure(
        string $token,
        string $username,
    ): void {
        $this->sensitiveLogger->log(
            $this->logger,
            'warning',
            'Authentication failed for token ' . $token,
            [
                'token' => $token,
                'user' => $username,
            ],
            sensitiveValues: [$token],
        );
    }
}
```

The sensitive values exist only for that call. No shared logger state is
modified.

Automatic detection is also applied, so explicitly supplied secrets and
detectable payment-card numbers can be protected in the same log operation.

The payment-card candidate-validation budget is shared between the log message
and structured context. It is not restarted separately for each context value.

Arbitrary objects inside the context are intentionally preserved. Object
serialization remains the responsibility of downstream processors and
formatters.

## Monolog processor

When a compatible Monolog 3 installation and Symfony MonologBundle are
available, MaskedBundle registers its sensitive data processor with
MonologBundle.

The processor automatically masks detectable sensitive data in:

- log messages;
- scalar and array values in `context`;
- scalar and array values in `extra`.

For example:

```php
$logger->info(
    'Processing card 4111111111111111',
    [
        'backup_card' => '5555555555554444',
    ],
);
```

Both detected card numbers are masked before the record continues through the
Monolog pipeline.

The processor does not maintain dynamic explicit secrets. Use
`SensitiveLogger` when known sensitive values need to be supplied for a
specific log operation.

### Objects

Objects are deliberately preserved by the processor.

Preserving objects avoids changing their identity or behavior before other
processors and handlers receive them.

Because objects may later be normalized or serialized by a formatter,
MaskedBundle also provides formatter-aware integrations.

Processor-only masking does not sanitize sensitive data introduced later when
preserved exceptions, `Stringable`, `JsonSerializable` or other objects are
rendered or serialized. Use `SensitiveLineFormatter` or
`SensitiveJsonFormatter` on handlers that may render such objects.

## SensitiveLineFormatter

Use `SensitiveLineFormatter` for line-oriented log output:

```yaml
monolog:
    handlers:
        main:
            type: stream
            path: '%kernel.logs_dir%/%kernel.environment%.log'
            formatter: 'Masked\Bundle\Monolog\SensitiveLineFormatter'
```

The formatter delegates normalization and line rendering to Monolog and then
masks sensitive information in the resulting line before it is returned.

This preserves Monolog's native `LineFormatter` behavior while protecting
sensitive data that appears in messages, exceptions, context, extra data or
rendered object representations, without mutating the original log record or
its objects.

## SensitiveJsonFormatter

For JSON logs, use `SensitiveJsonFormatter`:

```yaml
monolog:
    handlers:
        main:
            type: stream
            path: '%kernel.logs_dir%/%kernel.environment%.json'
            formatter: 'Masked\Bundle\Monolog\SensitiveJsonFormatter'
```

The formatter masks normalized log data and JSON object representations before
the complete log record is serialized.

This avoids post-processing an already encoded JSON string and preserves valid
JSON output.
JSON masking is bounded independently from Monolog normalization. Containers
are limited to 1,000 processed entries, nesting is limited to 32 masking
levels, and one `format()` or `formatBatch()` operation is limited to 10,000
processed entries in total.

The payment-card candidate-validation budget is also shared across the complete
`format()` or `formatBatch()` operation rather than restarted for each
normalized key or scalar value.

When a masking limit is reached, remaining unprocessed normalized data is
omitted and a safe placeholder is inserted. Raw unprocessed values are never
copied into the final JSON output.

## Choosing a Monolog formatter

The sensitive formatters are registered as Symfony services but are not forced
onto handlers automatically.

Use:

```text
Masked\Bundle\Monolog\SensitiveLineFormatter
```

for line-oriented output, or:

```text
Masked\Bundle\Monolog\SensitiveJsonFormatter
```

for JSON output.

Custom Monolog formatters are not automatically replaced or decorated.

## Direct services

The main application-level services are:

```text
Masked\Bundle\SensitiveDataMasker
Masked\Bundle\StructuredDataMasker
Masked\Bundle\Logging\SensitiveLogger
```

Monolog integrations are:

```text
Masked\Bundle\Monolog\SensitiveDataProcessor
Masked\Bundle\Monolog\SensitiveLineFormatter
Masked\Bundle\Monolog\SensitiveJsonFormatter
```

These classes, together with `Masked\Bundle\MaskedBundle` and the documented
`masked` configuration, form the supported public API.

Lower-level redaction and detection classes are implementation details and are
marked `@internal`. They remain autoloadable because the bundle uses them
internally, but applications should not depend on their constructors, methods
or concrete class structure. They may change between minor releases when
needed to evolve the implementation.

## Security considerations

MaskedBundle is a defensive logging and data-redaction component.

It is not:

- encryption;
- tokenization;
- a payment-card vault;
- a substitute for access control;
- a guarantee of PCI DSS compliance.

Automatic detection is heuristic by design.

Applications handling known passwords, tokens, credentials or other
application-specific secrets should supply them explicitly instead of relying
on free-text detection.

Explicit sensitive values are deliberately scoped to individual masking or
logging operations. MaskedBundle does not maintain a process-wide secret
registry.
Range redaction bounds normalization to 10,000 supplied match ranges. All
ranges are validated first; if more valid ranges are supplied, the complete
input is redacted rather than sorting an unbounded match list.

`StructuredDataMasker` intentionally does not inspect private or protected
state inside arbitrary objects.

The Monolog processor also preserves arbitrary objects. Sensitive formatters
protect the normalized representations they handle, but custom processors,
handlers or formatters may introduce or serialize additional data after
masking has occurred.

Applications with custom logging pipelines should review those components
separately.

MaskedBundle marks package-owned parameters that may contain unmasked sensitive
data with PHP's `#[\\SensitiveParameter]` attribute so those argument values are
redacted from stack frames owned by the bundle.

The attribute only protects the parameter at the frame where it is declared.
Applications that require exception traces to contain no function arguments at
all should additionally configure PHP with `zend.exception_ignore_args=1`.

## Acknowledgements

MaskedBundle owes an important part of its origin to
[Fuko\Masked](https://github.com/fuko-php/masked), created by
[Kaloyan K. Tsvetkov](https://github.com/kktsvetkov).

The original project introduced a simple and practical idea: sensitive values
should be easy to declare and safely removed from diagnostic output before
they accidentally reach logs, dumps or other places where they do not belong.

That idea stayed with me.

While contributing automatic payment-card detection to Fuko\Masked, I started
thinking about how the same principle could be approached today in a modern
Symfony application: with dependency injection, explicit service boundaries,
automatic detection where it can be trusted, and integrations designed for
long-running applications and contemporary logging pipelines.

MaskedBundle grew from that thought.

MaskedBundle is not a drop-in port of Fuko\Masked and does not attempt to
preserve its API. Its Symfony-facing architecture was designed for modern
dependency injection, stateless operation and contemporary logging pipelines.

The payment-card detection work has clear lineage to earlier work contributed
to Fuko\Masked. That provenance and the original project's MIT license are
documented in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

My sincere thanks to КТ
([Kaloyan K. Tsvetkov](https://github.com/kktsvetkov)) for the original idea,
for creating Fuko\Masked, and, more importantly, for the inspiration that
eventually led to this project.

## Development

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

## License

MaskedBundle is released under the MIT License.

Third-party attribution and license notices are documented in
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
