# MonkeysLegion HTTP

**High-performance PSR-7 / PSR-15 / PSR-17 HTTP library for the MonkeysLegion framework** — a complete HTTP message implementation, a production-ready middleware stack, and a SAPI emitter. Built natively for PHP 8.4.

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/releases/8.4/)
[![Latest Stable Version](https://img.shields.io/packagist/v/monkeyscloud/monkeyslegion-http?logo=packagist&logoColor=white)](https://packagist.org/packages/monkeyscloud/monkeyslegion-http)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![CS: PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-ff69b4)](https://www.php-fig.org/psr/psr-12/)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-Level%209-brightgreen)](https://phpstan.org/)
[![CI](https://github.com/monkeyscloud/monkeyslegion-http/actions/workflows/ci.yml/badge.svg)](https://github.com/monkeyscloud/monkeyslegion-http/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/monkeyscloud/monkeyslegion-http)](https://packagist.org/packages/monkeyscloud/monkeyslegion-http)

---

## ✨ Features

- **📦 PSR-7 messages** — immutable `ServerRequest`, `Response`, `JsonResponse`, `Stream`, `Uri` (zero external message dependency)
- **🧩 PSR-15 middleware** — 14 production-ready middleware components
- **🏭 PSR-17 factory** — `HttpFactory` for every message type
- **🚀 O(1) dispatcher** — cursor-based `MiddlewareDispatcher`, no `array_shift` overhead
- **🔌 Pipeable pipeline** — Express-style `CoreRequestHandler` with `pipe()` and `lock()`
- **🌐 Full CORS** — wildcard `*`, subdomain patterns (`https://*.example.com`), credentials, preflight caching
- **🛡️ Security headers** — strict / relaxed / api presets (CSP, HSTS, frame-ancestors, …)
- **🔑 Auth + CSRF** — timing-safe bearer auth with optional JWT decoding, stateless double-submit CSRF
- **⏱️ Rate limiting** — sliding-window limiter with PSR-16 cache support and per-route overrides
- **📤 SAPI emitter** — chunked streaming emitter with `Content-Length` injection
- **🆘 Error handling** — OOM-safe global handler with PSR-3 logging and pluggable renderers
- **🧰 Helper functions** — `response()`, `json()`, `redirect()`, `html()`, and more
- **🧪 PHPStan Level 9** — maximum static analysis rigor
- **🆕 PHP 8.4 native** — `final` classes, `readonly` properties, property hooks, `match` expressions

---

## 📦 Installation

```bash
composer require monkeyscloud/monkeyslegion-http
```

> **Requires PHP 8.4+** and `psr/http-message` ^2.0 (installed automatically).

---

## 🚀 Quick Start

```php
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\JsonResponse;
use MonkeysLegion\Http\Emitter\SapiEmitter;

// Build request from PHP superglobals
$request = ServerRequest::fromGlobals();

// Convenience accessors
$email = $request->input('user.email');   // Dot-notation body access
$token = $request->bearerToken();          // Authorization: Bearer ...
$ip    = $request->ip();                   // Client IP
$agent = $request->userAgent();            // User-Agent header
$hash  = $request->fingerprint();          // SHA-256 request fingerprint

// Create a JSON response and emit it
$response = new JsonResponse(['status' => 'ok'], 200);
(new SapiEmitter())->emit($response);
```

### Middleware stack

```php
use MonkeysLegion\Http\MiddlewareDispatcher;
use MonkeysLegion\Http\CoreRequestHandler;
use MonkeysLegion\Http\Middleware\{
    RequestIdMiddleware,
    SecurityHeadersMiddleware,
    CorsMiddleware,
    RateLimitMiddleware,
    AuthMiddleware,
};

$dispatcher = new MiddlewareDispatcher(
    middlewareStack: [
        new RequestIdMiddleware(),
        new SecurityHeadersMiddleware('strict'),
        new CorsMiddleware(allowedOrigins: ['https://app.example.com']),
        new RateLimitMiddleware(limit: 100, window: 60),
        new AuthMiddleware(requiredToken: getenv('API_TOKEN')),
    ],
    finalHandler: new CoreRequestHandler($router),
);

$response = $dispatcher->handle($request);
```

---

## 📋 API Reference

### ServerRequest

```php
$email = $request->input('user.email', 'default@example.com'); // dot-notation
$all   = $request->all();                                      // all parsed fields
$only  = $request->only(['email', 'password']);                // subset of fields

$request->isJson();     // Expects JSON?
$request->isSecure();   // HTTPS?
$request->isAjax();     // XMLHttpRequest?
$request->isMethod('POST');
```

### JsonResponse

```php
use MonkeysLegion\Http\Message\JsonResponse;

$response = (new JsonResponse($data))->withEnvelope(message: 'Success');

$paginated = (new JsonResponse($items))
    ->withPagination(page: 1, perPage: 25, total: 100);
// { status, message, data, meta: { pagination: { total, page, per_page, last_page, has_more } } }
```

### CORS Middleware

```php
use MonkeysLegion\Http\Middleware\CorsMiddleware;

// Allow every origin (no credentials) → Access-Control-Allow-Origin: *
$cors = new CorsMiddleware();

// Allow specific origins
$cors = new CorsMiddleware(allowedOrigins: ['https://app.example.com']);

// '*' anywhere in the list allows every origin
$cors = new CorsMiddleware(allowedOrigins: ['https://api.example.com', '*']);

// Subdomain wildcard patterns
$cors = new CorsMiddleware(allowedOrigins: ['https://*.example.com']);

// Full configuration with credentials (origin is reflected, never '*')
$cors = new CorsMiddleware(
    allowedOrigins:   ['https://app.example.com'],
    allowedMethods:   ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    allowedHeaders:   ['Content-Type', 'Authorization', 'X-Request-Id'],
    exposedHeaders:   ['X-Request-Id', 'X-Response-Time'],
    allowCredentials: true,
    maxAge:           3600,
);
```

> ⚠️ Per the CORS specification, `Access-Control-Allow-Origin: *` cannot be combined
> with credentials. When `allowCredentials` is `true`, the middleware echoes the
> requesting origin instead.

### Rate Limiter

```php
use MonkeysLegion\Http\Middleware\RateLimitMiddleware;

$limiter = new RateLimitMiddleware(
    cache:   $psr16Cache,   // PSR-16 cache (Redis/Memcached) — falls back to in-memory
    limit:   100,           // Requests per window
    window:  60,            // Window duration in seconds
);

// Per-route override via request attribute:
// $request = $request->withAttribute('rate_limit', ['limit' => 10, 'window' => 60]);
```

### Security Headers

```php
use MonkeysLegion\Http\Middleware\SecurityHeadersMiddleware;

$strict  = new SecurityHeadersMiddleware('strict');   // Production APIs (CSP, HSTS, …)
$relaxed = new SecurityHeadersMiddleware('relaxed');  // Development
$api     = new SecurityHeadersMiddleware('api');      // API-optimized (no CSP)

$custom = new SecurityHeadersMiddleware('strict', [
    'Content-Security-Policy' => "default-src 'self'",
]);
```

### Auth Middleware

```php
use MonkeysLegion\Http\Middleware\AuthMiddleware;

$auth = new AuthMiddleware(
    requiredToken: getenv('API_TOKEN'),
    publicPaths:   ['/health', '/login'],
    jwtDecoder:    fn (string $token) => JWT::decode($token, $key), // optional
);
```

### Error Handler

```php
use MonkeysLegion\Core\Error\ErrorHandler;
use MonkeysLegion\Http\Error\Renderer\JsonErrorRenderer;

$handler = new ErrorHandler(debug: false);
$handler->useRenderer(new JsonErrorRenderer());
$handler->useLogger($psrLogger);
$handler->register();
```

Features: OOM protection via reserved memory, recursive-exception guards, nested-failure fallback renderers (HTML/JSON/plain text), and PSR-3 logging.

### PSR-17 Factory

```php
use MonkeysLegion\Http\Factory\HttpFactory;

$factory = new HttpFactory();

$response = $factory->createResponse(200, 'OK');
$stream   = $factory->createStream('Hello');
$uri      = $factory->createUri('https://example.com/api');
$request  = $factory->createServerRequest('GET', $uri);
```

### SAPI Emitter

```php
use MonkeysLegion\Http\Emitter\SapiEmitter;

$emitter = new SapiEmitter(chunkSize: 8192);
$emitter->emit($response);
```

- Auto-injects `Content-Length` when the body size is known
- Guards against `headers_sent()` — throws instead of silently corrupting output
- Skips the body for `204`/`304` and aborts on dropped connections

### Helper Functions

```php
$r = response('Hello World', 200, ['X-Custom' => 'value']); // text
$r = json(['status' => 'ok']);                               // JSON
$r = jsonSuccess($data, 'User created', 201);                // { status, message, data }
$r = jsonError('Validation failed', 422);                    // { status, message }
$r = redirect('/dashboard', 302);                            // Location header
$r = html('<h1>Hello</h1>');                                 // HTML
```

---

## 🧑‍💻 Development

```bash
# Run all quality checks (code style + static analysis + tests)
composer check

# Or individually
composer test        # PHPUnit (unit tests)
composer phpstan     # PHPStan Level 9
composer cs-check    # PSR-12 code style (dry run)
composer cs-fix      # Auto-fix code style
composer infection   # Mutation testing (MSI ≥ 70%)
```

### 🧪 Testing & Quality Gates

- **Unit tests** — `tests/Unit/`, no external services (`composer test`)
- **Integration tests** — real HTTP through `php -S` + Redis-backed rate limiting, gated behind `RUN_INTEGRATION_TESTS=1`
- **Mutation testing** — Infection with **MSI ≥ 70%** and **Covered MSI ≥ 71%** (`composer infection`)
- **Static analysis** — PHPStan **Level 9**, zero errors
- **Code style** — PSR-12 via PHP-CS-Fixer

Run everything with one command:

```bash
composer quality-report
```

Integration tests run in CI against a Redis service container (see `.github/workflows/ci.yml`). Locally:

```bash
composer test:integration:docker
```

See [TESTING.md](TESTING.md) for the complete testing guide.

- [Contributing](CONTRIBUTING.md) — how to get involved
- [Code of Conduct](CODE_OF_CONDUCT.md) — community guidelines
- [Security Policy](SECURITY.md) — how to report vulnerabilities
- [Roadmap](ROADMAP.md) — what's next

---

## 📄 Requirements

- **PHP 8.4+**
- `psr/http-message` ^2.0 · `psr/http-server-middleware` ^1.0 · `psr/http-server-handler` ^1.0 · `psr/http-factory` ^1.1
- `psr/simple-cache` ^3.0 — for `RateLimitMiddleware` shared storage

### Optional

- `psr/log` ^3.0 — PSR-3 logging for `ErrorHandler` and `LoggingMiddleware`

---

## 📜 License

MIT — see [LICENSE](LICENSE).
