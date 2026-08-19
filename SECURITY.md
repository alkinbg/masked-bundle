# Security Policy

## Supported Versions

Security fixes are provided for the latest published version of MaskedBundle.

| Version | Supported |
| ------- | --------- |
| 0.1.2   | ✅ |
| < 0.1.2 | ❌ |

Before reporting a vulnerability, please verify that it is reproducible with
the latest published release or with the current `main` branch.

## Reporting a Vulnerability

Please do not report security vulnerabilities through public GitHub issues,
pull requests, or discussions.

Report vulnerabilities privately by email to:

`alkinbg@gmail.com`

Use the subject:

`[MaskedBundle Security]`

A useful report should include, where applicable:

- the affected MaskedBundle version;
- the affected PHP and Symfony versions;
- a clear description of the security impact;
- the relevant configuration or integration;
- reproducible steps or a minimal proof of concept;
- any proposed mitigation or fix.

## Sensitive Data in Reports

Do not include real passwords, API tokens, access credentials, personal data,
production logs, or real payment-card information in a vulnerability report.

Use synthetic values and minimal test cases.

If additional sensitive information is genuinely necessary to understand the
issue, mention that first and agree on an appropriate way to exchange it before
sending it.

## Disclosure

Please allow a reasonable amount of time for investigation and remediation
before publicly disclosing a reported vulnerability.

When appropriate, security fixes and advisories will describe the affected
versions and the recommended upgrade path.

## Security Scope

MaskedBundle is a defensive sensitive-data masking component.

It is not encryption, tokenization, a payment-card vault, an access-control
system, or a guarantee of PCI DSS compliance.

Applications remain responsible for the security of their complete logging,
storage, and processing pipelines.
