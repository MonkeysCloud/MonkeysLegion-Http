# MonkeysLegion HTTP

[![PHP](https://img.shields.io/badge/php-%5E8.4-8892BF.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

> High-performance PSR-7/PSR-15/PSR-17 HTTP message implementations, middleware stack, and SAPI emitter for the MonkeysLegion framework. Zero external dependencies beyond PSR interfaces.

## Installation

```bash
composer require monkeyscloud/monkeyslegion-http
```

## Features

| Feature | Description |
|---|---|
| **PSR-7 Messages** | Immutable `ServerRequest`, `Response`, `JsonResponse`, `Stream`, `Uri` |
| **PSR-15 Middleware** | 14 production-ready middleware components |
| **PSR-17 Factories** | `HttpFactory` for all message types |
| **SAPI Emitter** | Chunked streaming response emitter |
| **Error Handler** | OOM-safe error handler with PSR-3 logging |
| **Content Negotiation** | Accept header parsing and payload selection |
| **Helper Functions** | `response()`, `json()`, `redirect()`, `html()` |
| **PHP 8.4 Native** | `final` classes, `readonly` properties, `match` expressions |

## Quick Start

```php
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\JsonResponse;
use MonkeysLegion\Http\Emitter\SapiEmitter;

// Build request from PHP superglobals
$request = ServerRequest::fromGlobals();

// Convenience accessors
$email = $request->input('user.email');      // Dot-notation body access
$token = $request->bearerToken();            // Authorization: Bearer ...
$ip    = $request->ip();                     // Client IP
$agent = $request->userAgent();              // User-Agent header
$hash  = $request->fingerprint();            // SHA-256 request fingerprint

// Inspection helpers
$request->isJson();     // Expects JSON?
$request->isSecure();   // HTTPS?
$request->isAjax();     // XMLHttpRequest?
$request->isMethod('POST');

// Create responses
$response = new JsonResponse(['status' => 'ok'], 200);

// Emit to client
(new SapiEmitter())->emit($response);
```

## ServerRequest Convenience API

```php
// Dot-notation input access
$email = $request->input('user.email', 'default@example.com');

// Get all parsed body fields
$all = $request->all();

// Get only specific fields
$credentials = $request->only(['email', 'password']);

// Bearer token extraction
$jwt = $request->bearerToken();

// Request fingerprint for dedup/caching
$fingerprint = $request->fingerprint(); // SHA-256 of method|path|query|body
```

## JsonResponse

```php
use MonkeysLegion\Http\Message\JsonResponse;

// Simple JSON response
$response = new JsonResponse(['users' => $users]);

// With status code
$response = new JsonResponse(['error' => 'Not Found'], 404);

// Envelope format: { status, data, meta, message }
$response = (new JsonResponse($data))
    ->withEnvelope(message: 'Success');

// With pagination metadata
$response = (new JsonResponse($items))
    ->withPagination(page: 1, perPage: 25, total: 100);
```

## Middleware Stack

### Available Middleware

| Middleware | Purpose |
|---|---|
| `AuthMiddleware` | Bearer token authentication with JWT support |
| `CorsMiddleware` | Full CORS preflight handling |
| `CsrfMiddleware` | CSRF token verification |
| `RateLimitMiddleware` | Sliding-window rate limiting with PSR-16 cache |
| `SecurityHeadersMiddleware` | Security headers (strict/relaxed/api presets) |
| `ETagMiddleware` | Conditional GET with ETag/If-None-Match |
| `IpFilterMiddleware` | IP whitelist/blacklist filtering |
| `RequestIdMiddleware` | UUID request correlation ID |
| `RequestSizeLimitMiddleware` | Request body size enforcement |
| `TimingMiddleware` | X-Response-Time header |
| `LoggingMiddleware` | PSR-3 request/response logging |
| `TrustedProxyMiddleware` | X-Forwarded-* header handling |
| `ContentNegotiationMiddleware` | Accept header content negotiation |
| `ErrorHandlerMiddleware` | Exception → HTTP response conversion |

### Middleware Dispatcher

O(1) cursor-based dispatcher — no `array_shift` overhead:

```php
use MonkeysLegion\Http\MiddlewareDispatcher;
use MonkeysLegion\Http\CoreRequestHandler;

$dispatcher = new MiddlewareDispatcher(
    middlewareStack: [
        new RequestIdMiddleware(),
        new SecurityHeadersMiddleware('strict'),
        new CorsMiddleware(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET', 'POST', 'PUT', 'DELETE'],
        ),
        new RateLimitMiddleware(maxRequests: 100, windowSeconds: 60),
        new AuthMiddleware(requiredToken: 'my-api-key'),
    ],
    finalHandler: new CoreRequestHandler($router),
);

$response = $dispatcher->handle($request);
```

### CORS Middleware

```php
use MonkeysLegion\Http\Middleware\CorsMiddleware;

$cors = new CorsMiddleware(
    allowedOrigins:  ['https://app.example.com'],
    allowedMethods:  ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    allowedHeaders:  ['Content-Type', 'Authorization', 'X-Request-ID'],
    exposedHeaders:  ['X-Request-ID', 'X-Response-Time'],
    maxAge:          3600,
    allowCredentials: true,
);
```

### Rate Limiter

```php
use MonkeysLegion\Http\Middleware\RateLimitMiddleware;

$limiter = new RateLimitMiddleware(
    maxRequests:   100,        // Requests per window
    windowSeconds: 60,         // 1-minute window
    cache:         $psr16Cache, // Optional PSR-16 cache (falls back to in-memory)
);

// Per-route override via request attribute:
// $request = $request->withAttribute('rate_limit', 10);
```

### Security Headers

```php
use MonkeysLegion\Http\Middleware\SecurityHeadersMiddleware;

// Three built-in presets
$strict  = new SecurityHeadersMiddleware('strict');   // Production APIs
$relaxed = new SecurityHeadersMiddleware('relaxed');  // Development
$api     = new SecurityHeadersMiddleware('api');      // API-optimized

// Custom overrides
$custom = new SecurityHeadersMiddleware('strict', [
    'Content-Security-Policy' => "default-src 'self'",
]);
```

### Auth Middleware

```php
use MonkeysLegion\Http\Middleware\AuthMiddleware;

$auth = new AuthMiddleware(
    requiredToken: 'my-secret-token',
    publicPaths:   ['/health', '/login', '/register'],
    jwtDecoder:    fn(string $token) => JWT::decode($token, $key), // Optional
);
```

## Error Handler

Global error handler with OOM protection, recursive-exception guards, and PSR-3 logging:

```php
use MonkeysLegion\Http\Error\ErrorHandler;
use MonkeysLegion\Http\Error\Renderer\JsonErrorRenderer;
use MonkeysLegion\Http\Error\Renderer\HtmlErrorRenderer;

$handler = new ErrorHandler();
$handler->setRenderer(new JsonErrorRenderer(debug: false));
$handler->setLogger($psrLogger);
$handler->register();
```

### Error Renderers

| Renderer | Output |
|---|---|
| `JsonErrorRenderer` | Structured JSON error response |
| `HtmlErrorRenderer` | Styled HTML error page |
| `PlainTextErrorRenderer` | Plain text error output |

## PSR-17 Factory

```php
use MonkeysLegion\Http\Factory\HttpFactory;

$factory = new HttpFactory();

$response = $factory->createResponse(200, 'OK');
$stream   = $factory->createStream('Hello');
$uri      = $factory->createUri('https://example.com/api');
$request  = $factory->createServerRequest('GET', $uri);
```

## SAPI Emitter

```php
use MonkeysLegion\Http\Emitter\SapiEmitter;

$emitter = new SapiEmitter(chunkSize: 8192);
$emitter->emit($response);
```

Features:
- Auto-injects `Content-Length` when body size is known
- Guards against `headers_sent()` — throws instead of silent corruption
- Configurable chunk size for streaming large responses

## Helper Functions

```php
// Plain text response
$r = response('Hello World', 200, ['X-Custom' => 'value']);

// JSON response
$r = json(['status' => 'ok']);

// Redirect response
$r = redirect('/dashboard', 302);

// HTML response
$r = html('<h1>Hello</h1>');
```

## Requirements

- PHP 8.4+
- `psr/http-message` ^2.0
- `psr/http-server-middleware` ^1.0
- `psr/http-server-handler` ^1.0
- `psr/http-factory` ^1.1

### Optional

- `psr/simple-cache` ^3.0 — For `RateLimitMiddleware` persistent storage
- `psr/log` ^3.0 — For `ErrorHandler` and `LoggingMiddleware`

## License

MIT
