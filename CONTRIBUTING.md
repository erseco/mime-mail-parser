# Contributing

Contributions are welcome.

## Development setup

Requirements:

- PHP 8.2 or newer
- Composer

Install dependencies:

```bash
composer install
```

Run the complete local validation suite:

```bash
composer lint
composer analyse
composer test
composer test-coverage
```

The coverage suite must remain at or above 90%.

## Pull requests

- Add or update tests for behaviour changes.
- Keep backwards compatibility unless the change is explicitly intended for a major release.
- Include real-world or malformed MIME fixtures when they help reproduce a parser issue.
- Keep public API changes documented in the README.
- Ensure Composer validation, coding standards, static analysis, tests, and coverage pass.

## Security issues

Do not open public issues for suspected vulnerabilities. Follow [SECURITY.md](SECURITY.md).
