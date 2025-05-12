<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Trait to catch simple "getXxx()" calls and map them to properties.
 *
 * If a method call starts with "get" and a corresponding property exists,
 * it returns that property’s value. Otherwise, it throws a BadMethodCallException.
 */
trait GetterHook
{
    public function __call(string $name, array $args): mixed
    {
        // Only handle methods beginning with "get"
        if (str_starts_with($name, 'get')) {
            // Derive the property name by removing "get" and lowercasing first char
            $prop = lcfirst(substr($name, 3));
            if (property_exists($this, $prop)) {
                return $this->$prop;
            }
        }

        // If no matching getter, signal a missing method
        throw new \BadMethodCallException("Method {$name} does not exist");
    }
}

/**
 * PSR‑7 ServerRequest implementation.
 *
 * Uses immutable clones for mutators and the GetterHook for simple getters.
 */
class ServerRequest implements ServerRequestInterface
{
    use GetterHook;

    /**
     * Create a ServerRequest populated from PHP globals.
     */
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Build URI
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $uriStr = $scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
        $uri    = new Uri($uriStr);

        // Collect headers from $_SERVER
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }

        // Create body stream
        $body = new Stream(fopen('php://input', 'r'));

        // Protocol version
        $protocol = isset($_SERVER['SERVER_PROTOCOL'])
            ? str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL'])
            : '1.1';

        return new self(
            $method,
            $uri,
            $headers,
            $body,
            $protocol,
            $_SERVER,
            $_COOKIE,
            $_GET,
            $_FILES,
            $_POST
        );
    }
    use GetterHook;

    /** @var string The request URI path or target */
    private string $requestTarget;

    /** @var array<string,string[]> Lowercased header name → array of values */
    private array $headers;

    /** @var array<string,mixed> Custom request attributes */
    private array $attributes = [];

    /**
     * Constructor.
     *
     * @param string            $method          HTTP method (GET, POST, etc.)
     * @param UriInterface      $uri             Fully parsed URI object
     * @param array<string,mixed> $headers         Initial headers (name → value or array)
     * @param StreamInterface   $body            Request body stream
     * @param string            $protocolVersion HTTP protocol version (default "1.1")
     * @param array<string,mixed> $serverParams    $_SERVER parameters (optional)
     * @param array<string,mixed> $cookieParams    $_COOKIE parameters (initialized if omitted)
     * @param array<string,mixed> $queryParams     $_GET parameters (initialized if omitted)
     * @param array<string,mixed> $uploadedFiles   $_FILES parameters (initialized if omitted)
     * @param array|object|null $parsedBody      $_POST data (initialized if omitted)
     */
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
        private array|object|null   $parsedBody     = null
    ) {
        // Normalize headers to lowercase keys with array values
        $this->headers = $this->normalizeHeaders($headers);

        // Initialize superglobals if not passed in
        $this->cookieParams  = $_COOKIE  ?? $this->cookieParams;
        $this->queryParams   = $_GET     ?? $this->queryParams;
        $this->parsedBody    = $_POST    ?? $this->parsedBody;
        $this->uploadedFiles = $_FILES   ?? $this->uploadedFiles;

        // The default request target is the URI path or "/"
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
     * Normalize header names to lowercase keys and ensure each value is an array.
     *
     * @param array<string,mixed> $headers
     * @return array<string,string[]>
     */
    private function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $key => $vals) {
            $lk = strtolower($key);
            $out[$lk] = is_array($vals) ? array_values($vals) : [$vals];
        }
        return $out;
    }
}
