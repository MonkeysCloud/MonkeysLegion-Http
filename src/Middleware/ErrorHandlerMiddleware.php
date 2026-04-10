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
 * Catches exceptions thrown by downstream middleware/handlers
 * and re-throws for the global ErrorHandler to process.
 *
 * This middleware exists as a explicit "catch boundary" in the
 * pipeline — it ensures exceptions bubble to the registered
 * global handler rather than producing raw PHP errors.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
