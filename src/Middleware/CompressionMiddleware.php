<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonKeysLegion Framework — HTTP Package
 *
 * Compresses response bodies using gzip or brotli based on Accept-Encoding.
 *
 * • Negotiates encoding via Accept-Encoding header
 * • Compresses with gzencode (gzip) or brotli_compress (if ext-brotli)
 * • Skips: small responses (< min_size), already-compressed MIME types,
 *   streaming responses (text/event-stream), 304/204 responses
 * • Sets Content-Encoding, Vary: Accept-Encoding headers
 * • Updates Content-Length after compression
 *
 * Pipeline order: ETagMiddleware → ConditionalMiddleware → CompressionMiddleware
 * (ETag must be computed on the UNCOMPRESSED body)
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class CompressionMiddleware implements MiddlewareInterface
{
    /** @var list<string> MIME types that are already compressed */
    private const array SKIP_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'video/mp4',
        'video/webm',
        'audio/mpeg',
        'audio/ogg',
        'application/zip',
        'application/gzip',
        'application/x-gzip',
        'application/x-bzip2',
        'application/x-7z-compressed',
        'font/woff',
        'font/woff2',
    ];

    /** @var list<int> Status codes that should not be compressed */
    private const array SKIP_STATUS_CODES = [204, 304];

    /**
     * @param int  $minSize     Minimum response size in bytes to compress (default: 1024).
     * @param int  $gzipLevel   gzip compression level 1-9 (default: 6).
     * @param bool $enableBrotli Whether to use brotli if available (default: true).
     */
    public function __construct(
        private readonly int  $minSize = 1024,
        private readonly int  $gzipLevel = 6,
        private readonly bool $enableBrotli = true,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        // Skip non-compressible status codes
        if (in_array($response->getStatusCode(), self::SKIP_STATUS_CODES, true)) {
            return $response;
        }

        // Skip if already encoded
        if ($response->hasHeader('Content-Encoding')) {
            return $response;
        }

        // Skip streaming responses
        $contentType = $response->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'text/event-stream')) {
            return $response;
        }

        // Skip already-compressed MIME types
        if ($this->isCompressedMimeType($contentType)) {
            return $response;
        }

        // Read body
        $body = $response->getBody();
        if (!$body->isSeekable()) {
            return $response; // Can't rewind to read
        }

        $body->rewind();
        $content = $body->getContents();

        // Skip small responses
        if (strlen($content) < $this->minSize) {
            $body->rewind();
            return $response;
        }

        // Negotiate encoding
        $acceptEncoding = $request->getHeaderLine('Accept-Encoding');
        $encoding = $this->negotiateEncoding($acceptEncoding);

        if ($encoding === null) {
            // Client doesn't support gzip/brotli — return uncompressed
            $body->rewind();
            return $response;
        }

        // Compress
        $compressed = match ($encoding) {
            'br'    => $this->compressBrotli($content),
            'gzip'  => gzencode($content, $this->gzipLevel),
            default => false,
        };

        if ($compressed === false) {
            // Compression failed — return uncompressed
            $body->rewind();
            return $response;
        }

        // Only use compression if it actually reduces size
        if (strlen($compressed) >= strlen($content)) {
            $body->rewind();
            return $response;
        }

        // Build compressed response
        $stream = \MonkeysLegion\Http\Message\Stream::createFromString($compressed);

        $response = $response
            ->withBody($stream)
            ->withHeader('Content-Encoding', $encoding)
            ->withHeader('Content-Length', (string) strlen($compressed));

        // Add Vary header (merge with existing)
        $existingVary = $response->getHeaderLine('Vary');
        if ($existingVary !== '') {
            // Ensure Accept-Encoding is in the Vary header
            if (!str_contains(strtolower($existingVary), 'accept-encoding')) {
                $response = $response->withHeader('Vary', $existingVary . ', Accept-Encoding');
            }
        } else {
            $response = $response->withHeader('Vary', 'Accept-Encoding');
        }

        return $response;
    }

    /**
     * Negotiate the best encoding based on Accept-Encoding header.
     *
     * @return string|null 'gzip', 'br', or null if no supported encoding.
     */
    private function negotiateEncoding(string $acceptEncoding): ?string
    {
        if ($acceptEncoding === '') {
            return null;
        }

        // Parse Accept-Encoding header (supports q-values)
        $encodings = $this->parseAcceptEncoding($acceptEncoding);

        // Prefer brotli if available (better compression ratio)
        if ($this->enableBrotli && function_exists('brotli_compress')) {
            if (isset($encodings['br']) && $encodings['br'] > 0) {
                return 'br';
            }
        }

        // Fall back to gzip
        if (isset($encodings['gzip']) && $encodings['gzip'] > 0) {
            return 'gzip';
        }

        // Some clients send '*' to mean "any"
        if (isset($encodings['*']) && $encodings['*'] > 0) {
            return function_exists('brotli_compress') ? 'br' : 'gzip';
        }

        return null;
    }

    /**
     * Parse Accept-Encoding header into a map of encoding → q-value.
     *
     * @return array<string, float>
     */
    private function parseAcceptEncoding(string $header): array
    {
        $encodings = [];
        $parts = explode(',', $header);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;

            // Check for q-value: "gzip;q=0.8"
            if (preg_match('/^([a-zA-Z*]+)(?:;\s*q=([0-9.]+))?$/', $part, $matches)) {
                $encoding = strtolower($matches[1]);
                $qValue = isset($matches[2]) ? (float) $matches[2] : 1.0;
                $encodings[$encoding] = $qValue;
            }
        }

        return $encodings;
    }

    /**
     * Compress with brotli if the extension is available.
     */
    private function compressBrotli(string $data): string|false
    {
        if (!function_exists('brotli_compress')) {
            return false;
        }
        return @brotli_compress($data, $this->gzipLevel);
    }

    /**
     * Check if a Content-Type is already compressed.
     */
    private function isCompressedMimeType(string $contentType): bool
    {
        // Extract MIME type (ignore charset etc.)
        $mime = strtolower(trim(explode(';', $contentType)[0]));
        return in_array($mime, self::SKIP_MIME_TYPES, true);
    }
}
