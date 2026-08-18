# MaskedBundle

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

- PHP 8.4 or newer
- Symfony 8.1 or newer
- `ext-mbstring`

Monolog integration is optional.

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
    Alkin\MaskedBundle\MaskedBundle::class => ['all' => true],
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

The mask character must contain exactly one valid UTF-8 character.

## Masking strings

`SensitiveDataMasker` is the primary service for masking sensitive data in
strings.

```php
use Alkin\MaskedBundle\SensitiveDataMasker;

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

## Masking structured data

`StructuredDataMasker` recursively processes strings and integers inside
arrays.

```php
use Alkin\MaskedBundle\StructuredDataMasker;

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

Recursive arrays and excessive nesting are handled defensively so malformed or
cyclic data cannot cause unbounded recursion.

## Payment card detection

Automatic payment-card detection uses a conservative free-text heuristic.

Currently it:

- detects candidates containing 13 to 19 ASCII digits;
- supports spaces, horizontal tabs, hyphens and UTF-8 non-breaking spaces between digit groups;
- validates candidates using the Luhn algorithm;
- rejects candidates consisting of a single repeated digit;
- supports multiple payment-card numbers in the same string;
- operates safely when surrounding text contains multibyte characters.

The 13–19 digit range is a detector boundary, not a claim that it represents
every payment-card identifier permitted by every payment-card standard.

MaskedBundle does not perform card-brand, BIN or IIN identification.

## SensitiveLogger

`SensitiveLogger` is the recommended integration when an application knows
specific sensitive values at the point where a log entry is created.

It masks the message and structured context before delegating them to any
PSR-3 logger.

```php
use Alkin\MaskedBundle\Logging\SensitiveLogger;
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

Arbitrary objects inside the context are intentionally preserved. Object
serialization remains the responsibility of downstream processors and
formatters.

## Monolog processor

When Monolog and Symfony MonologBundle are available, MaskedBundle registers
its sensitive data processor with MonologBundle.

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

## SensitiveLineFormatter

Use `SensitiveLineFormatter` for line-oriented log output:

```yaml
monolog:
    handlers:
        main:
            type: stream
            path: '%kernel.logs_dir%/%kernel.environment%.log'
            formatter: 'Alkin\MaskedBundle\Monolog\SensitiveLineFormatter'
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
            formatter: 'Alkin\MaskedBundle\Monolog\SensitiveJsonFormatter'
```

The formatter masks normalized log data and JSON object representations before
the complete log record is serialized.

This avoids post-processing an already encoded JSON string and preserves valid
JSON output.

## Choosing a Monolog formatter

The sensitive formatters are registered as Symfony services but are not forced
onto handlers automatically.

Use:

```text
Alkin\MaskedBundle\Monolog\SensitiveLineFormatter
```

for line-oriented output, or:

```text
Alkin\MaskedBundle\Monolog\SensitiveJsonFormatter
```

for JSON output.

Custom Monolog formatters are not automatically replaced or decorated.

## Direct services

The main application-level services are:

```text
Alkin\MaskedBundle\SensitiveDataMasker
Alkin\MaskedBundle\StructuredDataMasker
Alkin\MaskedBundle\Logging\SensitiveLogger
```

Monolog integrations are:

```text
Alkin\MaskedBundle\Monolog\SensitiveDataProcessor
Alkin\MaskedBundle\Monolog\SensitiveLineFormatter
Alkin\MaskedBundle\Monolog\SensitiveJsonFormatter
```

Lower-level detection and range-redaction components are part of the bundle
architecture, but most applications should start with `SensitiveDataMasker`,
`StructuredDataMasker` or `SensitiveLogger`.

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

`StructuredDataMasker` intentionally does not inspect private or protected
state inside arbitrary objects.

The Monolog processor also preserves arbitrary objects. Sensitive formatters
protect the normalized representations they handle, but custom processors,
handlers or formatters may introduce or serialize additional data after
masking has occurred.

Applications with custom logging pipelines should review those components
separately.

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
