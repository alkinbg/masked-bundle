# MaskedBundle

Sensitive data detection and masking for Symfony applications.

MaskedBundle provides reusable masking services for Symfony applications and optional Monolog integration for preventing sensitive payment-card data from being written to logs.

The current detector focuses on payment card numbers found in free text.

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

If the bundle is not registered automatically, add it to `config/bundles.php`:

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

The mask character must contain exactly one character.

## Masking strings

`SensitiveDataMasker` is the primary service for masking sensitive data in strings.

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

## Masking structured data

`StructuredDataMasker` recursively processes strings and integers inside arrays.

```php
use Alkin\MaskedBundle\StructuredDataMasker;

$masked = $structuredDataMasker->mask([
    'customer' => [
        'card' => '4111111111111111',
    ],
]);
```

Array keys are also checked for sensitive data.

Objects are intentionally preserved and are not traversed or mutated.

## Payment card detection

Automatic payment-card detection uses a conservative free-text heuristic.

Currently it:

- detects candidates containing 13 to 19 ASCII digits;
- supports spaces, horizontal tabs, hyphens and UTF-8 non-breaking spaces between digit groups;
- validates candidates using the Luhn algorithm;
- rejects candidates consisting of a single repeated digit;
- supports multiple payment-card numbers in the same string;
- operates safely when surrounding text contains multibyte characters.

The 13–19 digit range is a detector boundary, not a claim that it represents every payment-card identifier permitted by every payment-card standard.

MaskedBundle does not perform card-brand, BIN or IIN identification.

## Monolog processor

When Monolog is available, MaskedBundle registers a Monolog processor automatically.

The processor masks sensitive data in:

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

Both detected card numbers are masked before the record continues through the Monolog pipeline.

### Objects and exceptions

Objects are deliberately preserved by the processor.

This includes `Throwable` instances.

Preserving objects avoids changing their identity or behavior before other processors and handlers receive them. However, sensitive data contained inside an exception message can become visible later when a formatter converts the exception to text or JSON.

For this reason MaskedBundle provides formatter-aware integrations.

## SensitiveLineFormatter

Use `SensitiveLineFormatter` when a handler uses line-oriented log output and exception messages may contain sensitive data.

```yaml
monolog:
    handlers:
        main:
            type: stream
            path: '%kernel.logs_dir%/%kernel.environment%.log'
            formatter: 'Alkin\MaskedBundle\Monolog\SensitiveLineFormatter'
```

The formatter preserves Monolog's native `LineFormatter` exception handling and masks sensitive data after the exception has been normalized.

The original `Throwable` object is not modified.

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

The formatter masks normalized exception data before JSON serialization.

This avoids post-processing an already encoded JSON string and preserves valid JSON output.

## Choosing a Monolog formatter

The sensitive formatters are registered as Symfony services but are not forced onto handlers automatically.

Choose the formatter explicitly for handlers where exception or formatter-level masking is required.

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
```

Monolog integrations are:

```text
Alkin\MaskedBundle\Monolog\SensitiveDataProcessor
Alkin\MaskedBundle\Monolog\SensitiveLineFormatter
Alkin\MaskedBundle\Monolog\SensitiveJsonFormatter
```

Lower-level detection and range-redaction components are available internally to the bundle architecture, but most applications should start with `SensitiveDataMasker` or `StructuredDataMasker`.

## Security considerations

MaskedBundle is a defensive logging and data-redaction component.

It is not:

- encryption;
- tokenization;
- a payment-card vault;
- a substitute for access control;
- a guarantee of PCI DSS compliance.

Automatic detection is heuristic by design. Applications handling known sensitive structured fields should not rely exclusively on free-text detection as their only security control.

The Monolog processor intentionally does not traverse arbitrary objects.

`SensitiveLineFormatter` and `SensitiveJsonFormatter` specifically protect Monolog exception normalization. They do not provide a general guarantee that arbitrary custom objects or custom formatters cannot expose sensitive data.

Applications using custom formatters should review their serialization behavior separately.

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