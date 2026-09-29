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
 * Propagates a correlation ID across services for distributed tracing.
 *
 * • Reads X-Correlation-Id header from upstream (gateways, load balancers)
 * • Falls back to the request_id attribute if no correlation ID is present
 * • Generates a new UUID if neither exists
 * • Sets request attribute 'correlation_id' for downstream consumers
 * • Echoes the ID in the response X-Correlation-Id header
 *
 * Should run AFTER RequestIdMiddleware in the pipeline.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CorrelationIdMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $headerName    = 'X-Correlation-Id',
        private readonly string $attributeName = 'correlation_id',
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Accept upstream correlation ID
        $correlationId = $request->getHeaderLine($this->headerName);

        // Fall back to request_id if no correlation ID
        if ($correlationId === '') {
            $correlationId = (string) ($request->getAttribute('request_id') ?? '');
        }

        // Generate new if neither exists
        if ($correlationId === '') {
            $correlationId = self::uuid4();
        }

        // Set on request for downstream consumers
        $request = $request
            ->withAttribute($this->attributeName, $correlationId)
            ->withHeader($this->headerName, $correlationId);

        // Process and echo in response
        $response = $handler->handle($request);

        return $response->withHeader($this->headerName, $correlationId);
    }

    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
