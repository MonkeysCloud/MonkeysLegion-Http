<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // Let everything run—controllers, other middleware, etc.
            return $handler->handle($request);
        } catch (\Throwable $e) {
            throw $e; // let the global error handler deal with it (HttpBootstrap)
        }
    }
}
