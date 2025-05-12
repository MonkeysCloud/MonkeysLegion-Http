<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use MonkeysLegion\Http\Message\Uri;

/**
 * Trait to catch simple “getXxx()” calls and map them to properties.
 */
trait GetterHook
{
    public function __call(string $name, array $args): mixed
    {
        if (str_starts_with($name, 'get')) {
            $prop = lcfirst(substr($name, 3));
            if (property_exists($this, $prop)) {
                return $this->$prop;
            }
        }
        throw new \BadMethodCallException("Method {$name} does not exist");
    }
}

/**
 * PSR-7 ServerRequest implementation.
 */
class ServerRequest implements ServerRequestInterface
{
    use GetterHook;

    /**
     * Build a ServerRequest from PHP super-globals.
     */
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Build URI
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $uriStr = $scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
        $uri    = new Uri($uriStr);

        // Collect headers
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }

        // Body stream
        $body = new Stream(fopen('php://input', 'r'));

        // HTTP version
        $protocol = isset($_SERVER['SERVER_PROTOCOL'])
            ? str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL'])
            : '1.1';

        /**                  ↓ order: method, uri, body, headers … */
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
            $_POST
        );
    }

    // ---------------------------------------------------------------------
    // Properties and constructor
    // ---------------------------------------------------------------------

    private string $requestTarget;
    private array  $headers;
    private array  $attributes = [];

    public function __construct(
        private string              $method,
        private UriInterface        $uri,
        private StreamInterface     $body,
        array                       $headers        = [],
        private string              $protocolVersion = '1.1',
        private array               $serverParams    = [],
        private array               $cookieParams    = [],
        private array               $queryParams     = [],
        private array               $uploadedFiles   = [],
        private array|object|null   $parsedBody      = null,
    ) {
        $this->headers = $this->normalizeHeaders($headers);

        $this->cookieParams  = $_COOKIE  ?? $this->cookieParams;
        $this->queryParams   = $_GET     ?? $this->queryParams;
        $this->parsedBody    = $_POST    ?? $this->parsedBody;
        $this->uploadedFiles = $_FILES   ?? $this->uploadedFiles;

        $this->requestTarget = $this->uri->getPath() ?: '/';
    }

    // ─── PSR‑7 Required Methods ──────────────────────────────────────────────

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
        // Join multiple values with commas
        return implode(', ', $this->getHeader($name));
    }

    /** {@inheritDoc} */
    public function withHeader($name, $value): static
    {
        $new = clone $this;
        $new->headers[strtolower($name)] = is_array($value)
            ? array_values($value)
            : [$value];
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

    // ─── ServerRequestInterface Extensions ───────────────────────────────────

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

    // ─── Internal Helper ──────────────────────────────────────────────────────


    /**
     * @param array<string,mixed> $headers
     * @return array<string,string[]>
     */
    private function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $lk = strtolower($k);
            $out[$lk] = is_array($v) ? array_values($v) : [$v];
        }
        return $out;
    }
}
