<?php
declare(strict_types=1);

namespace MonkeysLegion\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use MonkeysLegion\Router\Router;

final class RouteRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Router $router
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // delegate straight through to your Router
        return $this->router->dispatch($request);
    }
}