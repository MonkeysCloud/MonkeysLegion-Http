<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Immutable PSR-7 ServerRequest with convenience accessors.
 *
 * v2 improvements over v1:
 *  • Removed GetterHook __call() magic — Zero Magic principle
 *  • input() — type-safe parsed body access with dot-notation
 *  • bearerToken() — extract Authorization Bearer value
 *  • ip() — client IP from server params
 *  • userAgent() — shorthand for User-Agent header
 *  • fingerprint() — SHA-256 hash for request dedup/caching
 *  • isJson() / isSecure() / isAjax() — inspection helpers
 *
 * PHP 8.4 features used:
 *  • Property hooks for computed accessors
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ServerRequest implements ServerRequestInterface
{
    private string $requestTarget;

    /** @var array<string, string[]> */
    private array $headers;

    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __construct(
        private string              $method,
        private UriInterface        $uri,
        private StreamInterface     $body,
        array                       $headers         = [],
        private string              $protocolVersion = '1.1',
        private readonly array      $serverParams    = [],
        private array               $cookieParams    = [],
        private array               $queryParams     = [],
        private array               $uploadedFiles   = [],
        private array|object|null   $parsedBody      = null,
    ) {
        $this->headers       = self::normalizeHeaders($headers);
        $this->requestTarget = $this->uri->getPath() ?: '/';
    }

    // ── Factory ────────────────────────────────────────────────

    /**
     * Build a ServerRequest from PHP super-globals.
     */
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $uri    = new Uri($scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'));

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }

        $protocol = isset($_SERVER['SERVER_PROTOCOL'])
            ? str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL'])
            : '1.1';

        $body = new Stream(fopen('php://input', 'r'));

        // Auto-parse JSON body for any method when Content-Type is application/json
        $parsedBody = $_POST;
        if (isset($headers['Content-Type']) && str_contains($headers['Content-Type'], 'application/json')) {
            $rawBody = (string) $body;
            $decoded = json_decode($rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $parsedBody = $decoded;
            }
            // Rewind so downstream can re-read
            if ($body->isSeekable()) {
                $body->rewind();
            }
        }

        return new self(
            $method,
            $uri,
            $body,
            $headers,
            $protocol,
            $_SERVER,
            $_COOKIE,
            $_GET,
            $_FILES,
            $parsedBody,
        );
    }

    // ── Convenience Accessors (v2) ─────────────────────────────

    /**
     * Get a value from the parsed body using dot-notation.
     *
     * ```php
     * $email = $request->input('user.email', 'default@example.com');
     * ```
     */
    public function input(string $key, mixed $default = null): mixed
    {
        $data = $this->parsedBody;
        if (!is_array($data)) {
            return $default;
        }

        $segments = explode('.', $key);
        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * Get all parsed body data as an array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return is_array($this->parsedBody) ? $this->parsedBody : [];
    }

    /**
     * Get a subset of parsed body fields.
     *
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * Extract the Bearer token from the Authorization header.
     */
    public function bearerToken(): ?string
    {
        $header = $this->getHeaderLine('authorization');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get the client IP address.
     */
    public function ip(): string
    {
        return $this->serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get the User-Agent header value.
     */
    public function userAgent(): string
    {
        return $this->getHeaderLine('user-agent');
    }

    /**
     * Check if the request expects a JSON response.
     */
    public function isJson(): bool
    {
        $accept      = $this->getHeaderLine('accept');
        $contentType = $this->getHeaderLine('content-type');
        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json');
    }

    /**
     * Check if the request was made over HTTPS.
     */
    public function isSecure(): bool
    {
        $https = $this->serverParams['HTTPS'] ?? '';
        return $https !== '' && $https !== 'off';
    }

    /**
     * Check if the request is an AJAX/XMLHttpRequest.
     */
    public function isAjax(): bool
    {
        return strtolower($this->getHeaderLine('x-requested-with')) === 'xmlhttprequest';
    }

    /**
     * Check if the request method matches.
     */
    public function isMethod(string $method): bool
    {
        return strcasecmp($this->method, $method) === 0;
    }

    /**
     * Generate a SHA-256 fingerprint for dedup/caching.
     *
     * Based on: method + path + query + body hash.
     */
    public function fingerprint(): string
    {
        $body = (string) $this->body;
        if ($this->body->isSeekable()) {
            $this->body->rewind();
        }

        return hash('sha256', implode('|', [
            $this->method,
            $this->uri->getPath(),
            $this->uri->getQuery(),
            md5($body),
        ]));
    }

    // ── PSR-7 Required Methods ─────────────────────────────────

    /** {@inheritDoc} */
    public function getRequestTarget(): string
    {
        return $this->requestTarget;
    }

    /** {@inheritDoc} */
    public function withRequestTarget($target): static
    {
        $new = clone $this;
        $new->requestTarget = $target;
        return $new;
    }

    /** {@inheritDoc} */
    public function getMethod(): string
    {
        return $this->method;
    }

    /** {@inheritDoc} */
    public function withMethod($method): static
    {
        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    /** {@inheritDoc} */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /** {@inheritDoc} */
    public function withUri(UriInterface $uri, $preserveHost = false): static
    {
        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost || !$this->hasHeader('host')) {
            $host = $uri->getHost();
            if ($host !== '') {
                $port = $uri->getPort();
                if ($port !== null) {
                    $host .= ':' . $port;
                }
                $new->headers['host'] = [$host];
            }
        }
        return $new;
    }

    /** {@inheritDoc} */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /** {@inheritDoc} */
    public function withProtocolVersion($version): static
    {
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    /** {@inheritDoc} */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** {@inheritDoc} */
    public function hasHeader($name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /** {@inheritDoc} */
    public function getHeader($name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    /** {@inheritDoc} */
    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /** {@inheritDoc} */
    public function withHeader($name, $value): static
    {
        $new = clone $this;
        $new->headers[strtolower($name)] = is_array($value) ? array_values($value) : [$value];
        return $new;
    }

    /** {@inheritDoc} */
    public function withAddedHeader($name, $value): static
    {
        $new = clone $this;
        $key = strtolower($name);
        $vals = is_array($value) ? $value : [$value];
        $new->headers[$key] = array_merge($new->headers[$key] ?? [], $vals);
        return $new;
    }

    /** {@inheritDoc} */
    public function withoutHeader($name): static
    {
        $new = clone $this;
        unset($new->headers[strtolower($name)]);
        return $new;
    }

    /** {@inheritDoc} */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /** {@inheritDoc} */
    public function withBody(StreamInterface $body): static
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    // ── ServerRequestInterface ─────────────────────────────────

    /** {@inheritDoc} */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /** {@inheritDoc} */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /** {@inheritDoc} */
    public function withCookieParams(array $cookies): static
    {
        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    /** {@inheritDoc} */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /** {@inheritDoc} */
    public function withQueryParams(array $query): static
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    /** {@inheritDoc} */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /** {@inheritDoc} */
    public function withUploadedFiles(array $uploadedFiles): static
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    /** {@inheritDoc} */
    public function getParsedBody(): array|object|null
    {
        return $this->parsedBody;
    }

    /** {@inheritDoc} */
    public function withParsedBody($data): static
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    /** {@inheritDoc} */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /** {@inheritDoc} */
    public function getAttribute($name, $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /** {@inheritDoc} */
    public function withAttribute($name, $value): static
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    /** {@inheritDoc} */
    public function withoutAttribute($name): static
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }

    // ── Internal ───────────────────────────────────────────────

    /**
     * Normalize raw headers into lowercase-keyed array of string arrays.
     *
     * @param array<string, mixed> $headers
     * @return array<string, string[]>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $out[strtolower($k)] = is_array($v) ? array_values($v) : [$v];
        }
        return $out;
    }
}
