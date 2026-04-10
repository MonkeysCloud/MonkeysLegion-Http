<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Structured request/response logging middleware.
 *
 * v2 improvements:
 *  • Accepts PSR-3 logger (no longer uses error_log only)
 *  • Falls back to error_log when no logger injected
 *  • Uses hrtime for precise timing
 *  • Includes request_id if available
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $start    = hrtime(true);
        $response = $handler->handle($request);
        $elapsed  = (hrtime(true) - $start) / 1e6;

        $context = [
            'method'     => $request->getMethod(),
            'uri'        => (string) $request->getUri(),
            'status'     => $response->getStatusCode(),
            'duration_ms' => round($elapsed, 2),
            'request_id' => $request->getAttribute('request_id'),
        ];

        $message = sprintf(
            '[%s] %s %s – %d (%.2fms)',
            date('c'),
            $context['method'],
            $context['uri'],
            $context['status'],
            $context['duration_ms'],
        );

        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        } else {
            error_log($message);
        }

        return $response;
    }
}