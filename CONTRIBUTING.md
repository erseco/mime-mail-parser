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
- Keep fuzz cases deterministic so CI failures are reproducible.
- Keep public API changes documented in the README.
- Ensure Composer validation, coding standards, static analysis, tests, and coverage pass.

## Security issues

Do not open public issues for suspected vulnerabilities. Follow [SECURITY.md](SECURITY.md).


## Benchmarks

Run the lightweight parser benchmark harness with:

```bash
composer bench
```

Benchmarks are intentionally informational rather than a CI gate because shared runners are too variable for stable performance thresholds.


## Release process

For v1.1.0:

1. Merge the stacked pull requests in dependency order.
2. Confirm the `main` workflow is green, including the 90% coverage gate.
3. Replace `Unreleased` in `CHANGELOG.md` with the release date.
4. Create and push the annotated `v1.1.0` tag from the resulting `main` commit.
5. Let the release workflow build the archive and create the GitHub release.
