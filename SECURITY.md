# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | ✅ Active development |

## Reporting a Vulnerability

We take the security of **MonkeysLegion HTTP** seriously. If you believe you have found a security vulnerability, please **do not** open a public issue.

Instead, report it privately via one of the following methods:

- **GitHub Security Advisory**: Navigate to the repository's **Security > Advisories** tab and submit a private advisory.
- **Email**: Send your report to **<security@monkeyscloud.com>**.

### What to include

When reporting a vulnerability, please include as much of the following as possible:

- Type of vulnerability (e.g., header injection, path traversal, timing attack, CSRF bypass)
- Affected component(s) (CorsMiddleware, CsrfMiddleware, AuthMiddleware, Stream, Uri, etc.)
- Steps to reproduce the issue
- Proof of concept or exploit code (if available)
- Potential impact and attack surface

We will acknowledge receipt within **48 hours** and provide an initial assessment within **5 business days**. We will keep you informed throughout the fix and release process.

## Scope

The following are in scope for security reports:

- The HTTP message implementations (`ServerRequest`, `Response`, `Stream`, `Uri`)
- The middleware stack (`AuthMiddleware`, `CsrfMiddleware`, `CorsMiddleware`, `RateLimitMiddleware`, `IpFilterMiddleware`, `RequestSizeLimitMiddleware`, `TrustedProxyMiddleware`, etc.)
- The SAPI emitter and error handling

The following are **out of scope**:

- The PHP runtime or its bundled extensions
- Applications that consume this library
- Third-party dependencies listed in `require-dev`

## Security Best Practices When Using This Package

### Always use HTTPS in production

Set `secureCookie: true` (the default) on `CsrfMiddleware` so CSRF cookies are only sent over HTTPS, and enable `Strict-Transport-Security` via `SecurityHeadersMiddleware('strict')` or `('api')`.

### CORS

Avoid `allowCredentials: true` together with a wildcard origin. When credentials are enabled, the middleware reflects the requesting origin instead of sending `*`, which is required by the CORS specification. Prefer an explicit allow-list of origins in production:

```php
$cors = new CorsMiddleware(
    allowedOrigins:  ['https://app.example.com'],
    allowCredentials: true,
);
```

### Rate limiting

Use a shared PSR-16 cache (Redis/Memcached) for `RateLimitMiddleware` in multi-process deployments — the in-memory fallback is per-process and cannot enforce a global limit:

```php
$limiter = new RateLimitMiddleware(cache: $redisPsr16Adapter, limit: 100, window: 60);
```

### Trusted proxies

Only mark proxies you control as trusted. An attacker can spoof `X-Forwarded-For` otherwise:

```php
$proxy = new TrustedProxyMiddleware(trustedProxies: ['10.0.0.0/8', '203.0.113.5']);
```

### Credential management

Never hardcode tokens or API keys. Read them from environment variables or a secure configuration system:

```php
$auth = new AuthMiddleware(requiredToken: getenv('API_TOKEN'));
```

## Disclosure Policy

We follow a coordinated disclosure process:

1. **Report received** — acknowledged within 48 hours
2. **Investigation** — initial assessment within 5 business days
3. **Fix preparation** — patch developed and reviewed
4. **Release** — new version published with fix
5. **Public disclosure** — advisory published after release

We aim to complete this process within **14 days** for critical vulnerabilities.

## Recognition

We believe in crediting security researchers who help us improve our security. With your permission, we will acknowledge your contribution in our release notes and security advisories.

---

Thank you for helping keep **MonkeysLegion HTTP** and its community safe.
