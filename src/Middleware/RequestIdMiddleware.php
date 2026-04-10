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
 * Generates or propagates a unique request ID for distributed tracing.
 *
 * • Checks for an upstream X-Request-Id header (from load balancers / gateways)
 * • Generates UUID v4 if none exists
 * • Sets request attribute 'request_id' for downstream middleware/controllers
 * • Echoes the ID in the response X-Request-Id header
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
    /**
     * @param string $headerName   Header name for the request ID.
     * @param string $attributeName Request attribute name.
     */
    public function __construct(
        private readonly string $headerName    = 'X-Request-Id',
        private readonly string $attributeName = 'request_id',
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Accept upstream ID or generate new one
        $requestId = $request->getHeaderLine($this->headerName);
        if ($requestId === '') {
            $requestId = self::uuid4();
        }

        // Set on request for downstream consumers
        $request = $request
            ->withAttribute($this->attributeName, $requestId)
            ->withHeader($this->headerName, $requestId);

        // Process and echo in response
        $response = $handler->handle($request);

        return $response->withHeader($this->headerName, $requestId);
    }

    /**
     * Generate a RFC 4122 v4 UUID without external dependencies.
     */
    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        // Set version 4
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        // Set variant 10
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
