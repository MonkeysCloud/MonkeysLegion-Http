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
 * Handles Last-Modified / If-Modified-Since conditional requests.
 *
 * Works alongside ETagMiddleware (which handles ETag/If-None-Match):
 *   1. Sets the Last-Modified header on responses (from response attribute
 *      'last_modified' or a callback, or from the response's existing header)
 *   2. Checks If-Modified-Since — returns 304 Not Modified if unchanged
 *   3. Only applies to GET/HEAD requests with 2xx responses
 *
 * Pipeline order: ETagMiddleware → ConditionalMiddleware → CompressionMiddleware
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class ConditionalMiddleware implements MiddlewareInterface
{
    /** @var callable(ResponseInterface): ?\DateTimeInterface|null */
    private $modifiedSinceProvider;

    /**
     * @param callable(ResponseInterface): ?\DateTimeInterface|null $modifiedSinceProvider
     *        Callback to extract the last-modified time from a response.
     *        If null, uses the response's 'Last-Modified' header or request attribute.
     */
    public function __construct(
        ?callable $modifiedSinceProvider = null,
    ) {
        $this->modifiedSinceProvider = $modifiedSinceProvider;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        // Only handle conditional responses for GET/HEAD
        $method = $request->getMethod();
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return $response;
        }

        // Only handle successful responses
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            return $response;
        }

        // Determine last-modified time
        $lastModified = $this->resolveLastModified($response, $request);

        if ($lastModified !== null) {
            // Set Last-Modified header if not already set
            if (!$response->hasHeader('Last-Modified')) {
                $response = $response->withHeader(
                    'Last-Modified',
                    $lastModified->format('D, d M Y H:i:s') . ' GMT',
                );
            }

            // Check If-Modified-Since
            $ifModifiedSince = $request->getHeaderLine('If-Modified-Since');
            if ($ifModifiedSince !== '') {
                $requestTime = $this->parseHttpDate($ifModifiedSince);
                if ($requestTime !== null && $requestTime >= $lastModified) {
                    // Not modified — return 304 with empty body
                    return $response
                        ->withStatus(304)
                        ->withBody(\MonkeysLegion\Http\Message\Stream::empty());
                }
            }
        }

        return $response;
    }

    /**
     * Resolve the last-modified time for a response.
     */
    private function resolveLastModified(
        ResponseInterface $response,
        ServerRequestInterface $request,
    ): ?\DateTimeInterface {
        // 1. Try the callback provider
        if ($this->modifiedSinceProvider !== null) {
            $result = ($this->modifiedSinceProvider)($response);
            if ($result !== null) {
                return $result;
            }
        }

        // 2. Try request attribute 'last_modified'
        $attr = $request->getAttribute('last_modified');
        if ($attr instanceof \DateTimeInterface) {
            return $attr;
        }
        if (is_string($attr) && $attr !== '') {
            $parsed = $this->parseHttpDate($attr);
            if ($parsed !== null) {
                return $parsed;
            }
            // Try as a Unix timestamp
            if (is_numeric($attr)) {
                return (new \DateTimeImmutable())->setTimestamp((int) $attr);
            }
        }
        if (is_int($attr) && $attr > 0) {
            return (new \DateTimeImmutable())->setTimestamp($attr);
        }

        // 3. Try existing Last-Modified header on response
        $header = $response->getHeaderLine('Last-Modified');
        if ($header !== '') {
            return $this->parseHttpDate($header);
        }

        return null;
    }

    /**
     * Parse an HTTP date string (RFC 7231 format).
     *
     * Format: D, d M Y H:i:s GMT (e.g., "Wed, 21 Oct 2015 07:28:00 GMT")
     */
    private function parseHttpDate(string $date): ?\DateTimeImmutable
    {
        try {
            $dt = \DateTimeImmutable::createFromFormat(
                'D, d M Y H:i:s T',
                $date,
                new \DateTimeZone('GMT'),
            );
            if ($dt !== false) {
                return $dt;
            }

            // Try alternative format without timezone abbreviation
            $dt = \DateTimeImmutable::createFromFormat(
                'D, d M Y H:i:s \G\M\T',
                $date,
            );
            if ($dt !== false) {
                return $dt;
            }
        } catch (\Throwable) {
            // Ignore parse errors
        }

        return null;
    }
}
