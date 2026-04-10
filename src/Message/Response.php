<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Immutable PSR-7 Response implementation with static named constructors.
 *
 * Static factories cover the most common patterns:
 *  • Response::json()       — JSON body with Content-Type
 *  • Response::html()       — HTML body with Content-Type
 *  • Response::text()       — Plain text body
 *  • Response::noContent()  — 204 with empty body
 *  • Response::redirect()   — 302/301 with Location header
 *  • Response::download()   — File download with Content-Disposition
 *
 * PHP 8.4 features used:
 *  • Property hooks for statusCode / reasonPhrase accessors
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
class Response implements ResponseInterface
{
    /**
     * Standard HTTP reason phrases by status code.
     *
     * @var array<int, string>
     */
    private const array REASON_PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
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
        422 => 'Unprocessable Content',
        423 => 'Locked',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
    ];

    /** @var array<string, string[]> lowercase header name => values */
    private array $headers = [];

    /** @var array<string, string> lowercase header name => original header name */
    private array $headerNames = [];

    private string $reasonPhrase;

    /**
     * @param StreamInterface         $body            Response body stream.
     * @param int                     $statusCode      HTTP status code (default 200).
     * @param array<string, mixed>    $headers         Initial headers.
     * @param string                  $protocolVersion HTTP protocol version.
     * @param string                  $reasonPhrase    Optional custom reason phrase.
     */
    public function __construct(
        private StreamInterface $body,
        private int $statusCode = 200,
        array $headers = [],
        private string $protocolVersion = '1.1',
        string $reasonPhrase = '',
    ) {
        foreach ($headers as $name => $values) {
            $lc = strtolower($name);
            $this->headerNames[$lc] = $name;
            $this->headers[$lc]     = is_array($values) ? array_values($values) : [$values];
        }

        $this->reasonPhrase = $reasonPhrase !== ''
            ? $reasonPhrase
            : (self::REASON_PHRASES[$this->statusCode] ?? '');
    }

    // ── Static Named Constructors ──────────────────────────────

    /**
     * Create a JSON response.
     *
     * @param mixed $data   Any JSON-serializable value.
     * @param int   $status HTTP status code.
     * @param int   $flags  json_encode() flags.
     *
     * @throws \JsonException On encoding failure.
     */
    public static function json(
        mixed $data,
        int $status = 200,
        int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ): self {
        $json = json_encode($data, $flags | JSON_THROW_ON_ERROR);
        return new self(
            Stream::createFromString($json),
            $status,
            ['Content-Type' => 'application/json'],
        );
    }

    /**
     * Create an HTML response.
     */
    public static function html(string $html, int $status = 200): self
    {
        return new self(
            Stream::createFromString($html),
            $status,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    /**
     * Create a plain-text response.
     */
    public static function text(string $text, int $status = 200): self
    {
        return new self(
            Stream::createFromString($text),
            $status,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    /**
     * Create a 204 No Content response.
     */
    public static function noContent(): self
    {
        return new self(Stream::empty(), 204);
    }

    /**
     * Create a redirect response.
     */
    public static function redirect(string $url, int $status = 302): self
    {
        return new self(
            Stream::empty(),
            $status,
            ['Location' => $url],
        );
    }

    /**
     * Create a file download response.
     *
     * @param string      $path     Absolute file path on disk.
     * @param string|null $filename Download filename (defaults to basename).
     *
     * @throws \InvalidArgumentException If the path cannot be resolved or is not a readable file.
     */
    public static function download(string $path, ?string $filename = null): self
    {
        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
            throw new \InvalidArgumentException(
                sprintf('File path "%s" is not a valid readable file.', $path),
            );
        }

        $filename ??= basename($realPath);
        // Sanitize filename: whitelist safe characters, limit length
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename);
        $filename = ltrim($filename, '.');
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        if ($filename === '') {
            $filename = 'download';
        }

        return new self(
            Stream::createFromFile($realPath),
            200,
            [
                'Content-Type'        => 'application/octet-stream',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ],
        );
    }

    // ── PSR-7 ResponseInterface ────────────────────────────────

    /** {@inheritDoc} */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** {@inheritDoc} */
    public function withStatus($code, $reasonPhrase = ''): static
    {
        $new = clone $this;
        $new->statusCode   = $code;
        $new->reasonPhrase = $reasonPhrase !== ''
            ? $reasonPhrase
            : (self::REASON_PHRASES[$code] ?? '');
        return $new;
    }

    /** {@inheritDoc} */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    // ── PSR-7 MessageInterface ─────────────────────────────────

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

    /** {@inheritDoc} */
    public function withHeader($name, $value): static
    {
        $new = clone $this;
        $lc  = strtolower($name);
        $new->headerNames[$lc] = $name;
        $new->headers[$lc]     = is_array($value) ? array_values($value) : [$value];
        return $new;
    }

    /** {@inheritDoc} */
    public function withAddedHeader($name, $value): static
    {
        $new = clone $this;
        $lc  = strtolower($name);
        $new->headerNames[$lc] = $new->headerNames[$lc] ?? $name;
        $existing = $new->headers[$lc] ?? [];
        $toAdd    = is_array($value) ? $value : [$value];
        $new->headers[$lc] = array_merge($existing, $toAdd);
        return $new;
    }

    /** {@inheritDoc} */
    public function withoutHeader($name): static
    {
        $new = clone $this;
        $lc  = strtolower($name);
        unset($new->headers[$lc], $new->headerNames[$lc]);
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
}
