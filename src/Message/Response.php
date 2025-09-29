<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * PSR-7 Response implementation.
 */
class Response implements ResponseInterface
{
    /**
     * Standard HTTP reason phrases by status code.
     *
     * @var array<int,string>
     */
    private static array $reasonPhrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non‑Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => "I'm a Teapot",
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
    ];

    /** @var array<string,string[]> lowercase header name => values */
    private array $headers = [];

    /** @var array<string,string> lowercase header name => original header name */
    private array $headerNames = [];

    private string $reasonPhrase;

    /**
     * @param StreamInterface $body Response body stream
     * @param int               $statusCode      HTTP status code (default 200)
     * @param array<string,mixed> $headers         Initial headers
     * @param string            $protocolVersion HTTP protocol version, e.g. "1.1"
     * @param string            $reasonPhrase    Optional custom reason phrase
     */
    public function __construct(
        private StreamInterface $body,
        private int $statusCode = 200,
        array $headers = [],
        private string $protocolVersion = '1.1',
        string $reasonPhrase = ''
    ) {
        // Normalize and store headers
        foreach ($headers as $name => $values) {
            $lc = strtolower($name);
            $this->headerNames[$lc] = $name;
            $this->headers[$lc]     = is_array($values) ? array_values($values) : [$values];
        }

        // Determine reason phrase: custom or standard
        $this->reasonPhrase = $reasonPhrase !== ''
            ? $reasonPhrase
            : (self::$reasonPhrases[$this->statusCode] ?? '');
    }

    /**
     * Clone helper: apply $updater to the clone, return new instance.
     */
    private function cloneWith(callable $updater): static
    {
        $new = clone $this;
        $updater($new);
        return $new;
    }

    /** {@inheritDoc} */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     */
    public function withProtocolVersion($version): static
    {
        return $this->cloneWith(fn($r) => $r->protocolVersion = $version);
    }

    /** {@inheritDoc} */
    public function getHeaders(): array
    {
        // Reconstruct original header names with their values
        $result = [];
        foreach ($this->headers as $lc => $values) {
            $name = $this->headerNames[$lc] ?? $lc;
            $result[$name] = $values;
        }
        return $result;
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

    /**
     * Return an instance with the provided header, replacing any existing values.
     */
    public function withHeader($name, $value): static
    {
        return $this->cloneWith(function ($r) use ($name, $value) {
            $lc = strtolower($name);
            $r->headerNames[$lc] = $name;
            $r->headers[$lc]     = is_array($value) ? array_values($value) : [$value];
        });
    }

    /**
     * Return an instance with the specified header added.
     */
    public function withAddedHeader($name, $value): static
    {
        return $this->cloneWith(function ($r) use ($name, $value) {
            $lc       = strtolower($name);
            $r->headerNames[$lc] = $r->headerNames[$lc] ?? $name;
            $existing = $r->headers[$lc] ?? [];
            $toAdd    = is_array($value) ? $value : [$value];
            $r->headers[$lc]     = array_merge($existing, $toAdd);
        });
    }

    /** {@inheritDoc} */
    public function withoutHeader($name): static
    {
        return $this->cloneWith(function ($r) use ($name) {
            $lc = strtolower($name);
            unset($r->headers[$lc], $r->headerNames[$lc]);
        });
    }

    /** {@inheritDoc} */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * Return an instance with the provided body stream.
     */
    public function withBody(StreamInterface $body): static
    {
        return $this->cloneWith(fn($r) => $r->body = $body);
    }

    /** {@inheritDoc} */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Return an instance with the specified status code and optional reason phrase.
     */
    public function withStatus($code, $reasonPhrase = ''): static
    {
        return $this->cloneWith(function ($r) use ($code, $reasonPhrase) {
            $r->statusCode   = $code;
            $r->reasonPhrase = $reasonPhrase !== ''
                ? $reasonPhrase
                : (self::$reasonPhrases[$code] ?? '');
        });
    }

    /** {@inheritDoc} */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function view(string $data): Response
    {
        // Clear the body before writing new data
        $this->body = $this->createStream();
        $this->body->write($data);
        return $this;
    }
    
    /**
     * Create a new writable stream for the response body.
     */
    private function createStream(): StreamInterface
    {
        // Use php://temp for an in-memory stream
        return new \MonkeysLegion\Http\Message\Stream(fopen('php://temp', 'r+'));
    }
}
