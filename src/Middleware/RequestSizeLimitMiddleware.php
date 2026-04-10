<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Rejects requests whose body exceeds a configurable size limit.
 *
 * Returns 413 Payload Too Large with a JSON error body.
 * Prevents memory exhaustion attacks from oversized payloads.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RequestSizeLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param int $maxBytes Maximum allowed body size in bytes (default 10 MB).
     */
    public function __construct(
        private readonly int $maxBytes = 10_485_760,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Check Content-Length header first (cheap)
        $contentLength = $request->getHeaderLine('content-length');
        if ($contentLength !== '' && (int) $contentLength > $this->maxBytes) {
            return $this->reject();
        }

        // Check actual body size if seekable
        $body = $request->getBody();
        $size = $body->getSize();
        if ($size !== null && $size > $this->maxBytes) {
            return $this->reject();
        }

        return $handler->handle($request);
    }

    private function reject(): ResponseInterface
    {
        $json = json_encode([
            'status'  => 'error',
            'message' => sprintf('Request body exceeds maximum allowed size of %s bytes.', number_format($this->maxBytes)),
        ], JSON_UNESCAPED_SLASHES);

        return new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::createFromString($json),
            413,
            ['Content-Type' => 'application/json'],
        );
    }
}
