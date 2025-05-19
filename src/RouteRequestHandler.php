<?php
declare(strict_types=1);

namespace MonkeysLegion\Http;

use MonkeysLegion\Router\Router;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Tiny adaptor that lets the Router act as a PSR-15 RequestHandler.
 */
final class RouteRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Router $router
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->router->dispatch($request);
    }
}