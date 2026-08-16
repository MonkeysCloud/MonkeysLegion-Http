# MonkeysLegion HTTP — Agent Instructions

## Project

PSR-7 / PSR-15 / PSR-17 HTTP library for the MonkeysLegion framework.
Namespace: `MonkeysLegion\Http\`.

- `src/Message/` — PSR-7 messages (Request, Response, Stream, Uri, ServerRequest, JsonResponse)
- `src/Middleware/` — PSR-15 middleware (CORS, CSRF, Auth, RateLimit, ETag, etc.)
- `src/Emitter/` — `SapiEmitter` (header()/echo based response emission)
- `src/Error/` — error handler + renderers
- `src/Negotiation/` — content negotiation (Accept parsing)
- `src/Factory/` — PSR-17 `HttpFactory`

## Must Follow

### Code Style
- **PSR-12** enforced by `.php-cs-fixer.php` (`@PHP84Migration` ruleset)
- **Strict types**: `declare(strict_types=1);` in every file
- **Native function calls** prefixed with `\` (e.g. `\strlen`, `\fopen`)
- **Single quotes** for strings, trailing commas in multiline arrays/arguments/parameters
- Ordered imports, no unused imports, no empty phpdoc
- Return types on all methods; `void` on mutators

### PHP 8.4
- `readonly` properties where the value is immutable
- Named arguments in calls with 3+ parameters
- Property hooks where appropriate (see `src/Message/Uri.php`)

### Architecture
- Messages are **immutable** — `with*()` methods return clones, never mutate `$this`
- Middleware implements `Psr\Http\Server\MiddlewareInterface`; handlers implement `RequestHandlerInterface`
- **Never depend on the real SAPI in unit tests** — SapiEmitter is covered by the feature test via `php -S`
- Rate limiting keys by `uid` request attribute (authenticated) or client IP (anonymous); supports PSR-16 caches and per-route overrides via the `rate_limit` request attribute
- CORS middleware supports `*` anywhere in the allow list and `*.example.com` subdomain patterns

### Testing
- **Unit tests** (`tests/Unit/`) use mocks/fakes — no external services
- **Feature tests** (`tests/Feature/`) need `RUN_INTEGRATION_TESTS=1` + Docker (Redis) or start `php -S` themselves
- Feature tests that need external services must `markTestSkipped()` when `RUN_INTEGRATION_TESTS` is unset
- No tautological assertions like `assertTrue(true)`
- New code should maintain the Infection MSI ≥ 70% (see `infection.json5`)

### Quality Gates
Run `composer quality-report` to check everything:
- `cs-check` — PSR-12, zero violations
- `phpstan` — Level 9, zero errors (config: `phpstan.neon`)
- `test` — PHPUnit 11.x, all pass
- `infection` — MSI ≥ 70%, Covered MSI ≥ 71%

### Commit Style
- No emojis in commit messages
- Present tense imperative: "Add X", "Fix Y", "Refactor Z"
- One logical commit per change

## Integration Test Notes

`tests/Feature/EmitterFeatureTest.php` spawns `php -S` via `proc_open`:

- **Use an array command** (not a string) so `proc_open` exec()s php directly —
  a string command goes through `sh -c` and `proc_terminate()` would orphan
  the real server process.
- **Redirect the child's stdout/stderr to files**, not pipes, or the
  long-running server keeps the parent's stdout pipe open and hangs any
  process that pipes PHPUnit output.
- tearDown terminates the server and escalates to SIGKILL if needed.
