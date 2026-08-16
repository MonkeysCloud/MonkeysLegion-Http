# Testing Documentation

This document outlines all testing workflows for the MonkeysLegion HTTP library.

## Test Suites

The project has two test suites:

| Suite | Location | Description |
|-------|----------|-------------|
| **Unit** | `tests/Unit/` | PSR-7/PSR-15/PSR-17 behavior, middleware logic, error rendering — no external services |
| **Feature** | `tests/Feature/` | Real HTTP round-trips through PHP's built-in server and Redis-backed rate limiting |

Feature tests are skipped by default — they run only when `RUN_INTEGRATION_TESTS=1` is set.

## Quick Test Commands

### Run All Tests (Unit only, integration skipped)

```bash
composer test
```

### Run the Full Suite With Integration Coverage

```bash
docker compose -f docker-compose.integration.yml up -d --wait
RUN_INTEGRATION_TESTS=1 composer test
docker compose -f docker-compose.integration.yml down
```

Or use the one-shot helper:

```bash
composer test:integration:docker
```

### Run Only the Feature Suite

```bash
composer test:integration
```

(Requires the Redis service from `docker-compose.integration.yml` to be up.)

### Run a Single Test File

```bash
vendor/bin/phpunit tests/Unit/Message/StreamTest.php
```

### Run a Single Test Method

```bash
vendor/bin/phpunit --filter real_http_round_trip_json_response tests/Feature/EmitterFeatureTest.php
```

## Mutation Testing (Infection)

Mutation testing measures how well your tests detect injected bugs (mutants).

```bash
composer infection
```

Current thresholds (see `infection.json5`):

- **MSI**: ≥ 70%
- **Covered Code MSI**: ≥ 71%

The report is written to `build/infection.log`. If the MSI drops below the
threshold, CI fails — improve tests rather than lowering the bar.

## Static Analysis (PHPStan Level 9)

```bash
composer phpstan
```

The project targets **PHPStan level 9** — the strictest level. See
`phpstan.neon` for the configuration.

## Code Style (PHP-CS-Fixer, PSR-12)

```bash
composer cs-check   # dry run — fails on violations
composer cs-fix     # fixes in place
```

## Coverage

```bash
composer test:coverage
```

Writes an HTML report to `.build/coverage/`.

## All Quality Gates

```bash
composer check           # cs-check + phpstan + test
composer quality-report  # check + infection
```

## Test Conventions

- Tests live under `tests/Unit/` (no services) or `tests/Feature/` (real services).
- Use `#[Test]` attributes (PHPUnit 11) rather than `test*` method prefixes.
- Assertions must be meaningful — no tautologies like `assertTrue(true)`.
- Feature tests that need an external service must call
  `markTestSkipped()` unless `RUN_INTEGRATION_TESTS=1` is set, so the suite
  stays green on machines without Docker.

### Why Feature Tests Are Gated

The `EmitterFeatureTest` starts `php -S` on a free port and makes real TCP
requests; `RateLimitRedisFeatureTest` needs a live Redis. Both are valuable
in CI (see `.github/workflows/ci.yml`) but would fail on a developer machine
without those services, so they skip unless explicitly enabled.
