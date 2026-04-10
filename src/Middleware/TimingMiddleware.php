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
 * Adds W3C Server-Timing headers with nanosecond precision.
 *
 * Uses hrtime(true) for monotonic clock — unaffected by NTP drift.
 * Replaces the v1 LoggingMiddleware's basic timing.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class TimingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $start    = hrtime(true);
        $response = $handler->handle($request);
        $elapsed  = (hrtime(true) - $start) / 1e6; // nanoseconds → milliseconds

        return $response->withHeader(
            'Server-Timing',
            sprintf('total;dur=%.3f', $elapsed),
        );
    }
}
