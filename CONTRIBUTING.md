# Contributing to MonkeysLegion HTTP

First off, thank you for considering contributing! 🎉

This is a community-driven project and we welcome all forms of contributions — whether it's a new feature, a bug fix, documentation improvement, or a feature request.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Environment](#development-environment)
- [PHP Version & Standards](#php-version--standards)
- [Architecture Overview](#architecture-overview)
- [Testing](#testing)
- [Static Analysis](#static-analysis)
- [Code Style](#code-style)
- [Pull Request Process](#pull-request-process)
- [Questions?](#questions)

---

## Code of Conduct

This project and everyone participating in it is governed by the [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior by opening a [GitHub Issue](https://github.com/monkeyscloud/monkeyslegion-http/issues).

---

## Getting Started

1. **Fork** the repository on GitHub.
2. **Clone** your fork locally:

   ```bash
   git clone https://github.com/your-username/monkeyslegion-http.git
   cd monkeyslegion-http
   ```

3. **Install dependencies**:

   ```bash
   composer install
   ```

4. **Create a branch** for your changes:

   ```bash
   git checkout -b feature/my-feature
   ```

---

## Development Environment

- **PHP 8.4+** is required — the package uses PHP 8.4 features (`readonly` classes, property hooks, `match` expressions, etc.)
- **Composer 2.x** is required

### Quick validation

Run all quality checks with a single command:

```bash
composer check
```

This runs:

1. `composer cs-check` — PSR-12 code style
2. `composer phpstan` — PHPStan Level 9 static analysis
3. `composer test` — PHPUnit test suite

---

## PHP Version & Standards

| Requirement | Standard |
|-------------|----------|
| **PHP Version** | 8.4+ only |
| **Code Style** | [PSR-12](https://www.php-fig.org/psr/psr-12/) |
| **Autoloading** | [PSR-4](https://www.php-fig.org/psr/psr-4/) |
| **Static Analysis** | PHPStan Level 9 |
| **Testing** | PHPUnit 11.x |
| **Type System** | Strict types everywhere, native PHP 8.4 features preferred |

### PHP 8.4 Features We Embrace

- `final` classes for middleware and messages
- `readonly` promoted constructor properties for immutable value objects
- Named arguments for methods with 3+ parameters
- `match` expressions over `switch` statements

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Message Layer (PSR-7)                    │
│   ServerRequest · Response · JsonResponse · Stream · Uri    │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                 Middleware Stack (PSR-15)                    │
│   Auth · Cors · Csrf · RateLimit · SecurityHeaders · ETag    │
│   IpFilter · RequestId · RequestSizeLimit · Timing · Logging │
│   TrustedProxy · ContentNegotiation · ErrorHandler           │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│              MiddlewareDispatcher / CoreRequestHandler       │
│              (O(1) cursor dispatch / pipeable pipeline)      │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                 Emitter · Error Handler · Helpers            │
│   SapiEmitter · ErrorHandler · response()/json()/redirect()  │
└──────────────────────────────────────────────────────────────┘
```

The package implements the PSR-7 message interfaces directly (no external PSR-7 dependency) and provides a PSR-17 factory, so it can be used standalone or as the HTTP layer of the MonkeysLegion framework.

---

## Testing

- **All tests must pass** before a PR is merged
- Run tests with: `composer test`
- Tests live in `tests/Unit/` (no external services) and `tests/Feature/` (real services), namespace `MonkeysLegion\Http\Tests\...`
- **Edge cases are highly valued**: empty inputs, invalid headers, header injection attempts, oversized payloads, malformed URIs, CIDR edge cases
- **Mutation testing is enforced** — `composer infection` requires **MSI ≥ 70%** (Covered MSI ≥ 71%). New code must not let mutants escape.

### Test conventions

- Unit tests: `tests/Unit/*Test.php`; feature tests: `tests/Feature/*Test.php`
- Use `PHPUnit\Framework\Attributes\Test` attributes
- Feature tests requiring external services must skip unless `RUN_INTEGRATION_TESTS=1` is set
- Add a regression test with every bug fix

### Integration tests

Feature tests exercise real infrastructure: the SAPI emitter over a live `php -S` server and Redis-backed rate limiting. Run them locally with:

```bash
composer test:integration:docker
```

See [TESTING.md](TESTING.md) for the full testing guide.

---

## Static Analysis

We enforce **PHPStan Level 9** — no exceptions. Run before submitting:

```bash
composer phpstan
```

This ensures:

- Strict return type declarations
- Proper handling of nullable and mixed values
- No unused or uninitialized properties
- Template/generic array shape verification

---

## Code Style

We follow **PSR-12** with the following additional conventions:

- **Strict types**: `declare(strict_types=1)` at the top of every file
- **No `echo` or `var_dump`** in library code
- **Named arguments** preferred for clarity in method calls with 3+ parameters
- **`match` expressions** preferred over `switch` statements

Auto-fix code style with:

```bash
composer cs-fix
```

---

## Pull Request Process

1. **Before submitting**, run `composer quality-report` (code style + static analysis + tests + mutation testing) and ensure everything passes.
2. **Keep PRs focused** — one feature or fix per PR. Large PRs are harder to review.
3. **Update documentation** — if you change behavior, update the README and relevant docblocks.
4. **Add tests** — new features require tests. Bug fixes require a regression test.
5. **Describe your changes** — provide a clear summary and motivation in the PR description.

### PR checklist

- [ ] I have run `composer quality-report` and all checks pass
- [ ] I have added/updated tests to cover my changes
- [ ] I have updated documentation (README, docblocks) as needed
- [ ] My code follows PSR-12 and project conventions

---

## Questions?

- Open a [Discussion](https://github.com/monkeyscloud/monkeyslegion-http/discussions) for questions
- Open an [Issue](https://github.com/monkeyscloud/monkeyslegion-http/issues) for bug reports or feature requests

Thank you for contributing! 🚀
