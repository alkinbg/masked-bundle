# Contributing to MaskedBundle

Thank you for considering a contribution to MaskedBundle.

Contributions are welcome, especially focused bug fixes, regression tests,
documentation improvements, performance improvements, and well-scoped changes
that preserve the bundle's defensive masking behavior.

## Before opening an issue

Please search existing issues first to avoid duplicates.

Security vulnerabilities must not be reported in a public issue. Please follow
the instructions in `SECURITY.md` instead.

Do not include real passwords, API tokens, credentials, personal data,
production logs, or real payment-card information in issues, pull requests,
examples, or tests. Use synthetic data only.

## Development requirements

MaskedBundle currently requires:

- PHP 8.4.1 or newer
- Symfony 8.1 or newer
- `ext-mbstring`

Install development dependencies with Composer:

```bash
composer update --prefer-dist
```

## Quality checks

Before submitting a pull request, run:

```bash
composer validate --strict
composer format:check
composer test
composer analyse
```

All checks should pass locally.

## Tests

Bug fixes should include a regression test whenever practical.

Tests involving sensitive-data detection must use synthetic values only.
Never copy real credentials, production logs, personal information, or actual
payment-card data into the test suite.

Security-sensitive behavior should remain bounded and fail closed when a
documented work limit is exhausted.

## Coding guidelines

Keep changes focused and avoid unrelated refactoring.

Prefer small, explicit changes over introducing abstractions without a clear
need.

New runtime dependencies should only be introduced when they provide a clear
benefit that cannot reasonably be achieved with the existing dependency set.

To automatically apply the project's coding style:

```bash
composer format
```

## Public API

The supported public PHP API is documented in the README.

Classes and services marked `@internal` are implementation details and should
not be treated as extension points.

Changes to documented public behavior or public API should include matching
tests and documentation.

Breaking public API changes should not be introduced as part of an unrelated
bug fix or refactor.

## Documentation

Update `README.md` and `docs/index.md` when a change affects documented
behavior, configuration, integrations, or public API.

The two documents should remain consistent with each other.

## Pull requests

A pull request should explain:

- what problem it solves;
- why the change is needed;
- how the implementation addresses the problem;
- how the behavior was tested.

Keep each pull request focused on one logical change.

Do not commit generated dependency directories such as `vendor/`.

## Commit messages

Use short, descriptive commit subjects.

Prefixes such as `fix:`, `feat:`, `docs:`, `test:`, `perf:`, and `ci:` are
welcome when they accurately describe the change.

## License

By contributing to this repository, you agree that your contribution may be
distributed under the project's MIT License.
