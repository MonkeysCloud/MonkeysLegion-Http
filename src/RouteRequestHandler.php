<?php
declare(strict_types=1);

namespace MonkeysLegion\Http;

use MonkeysLegion\Router\Router;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouteRequestHandler implements RequestHandlerInterface
{
    public function __construct(private Router $router) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Router::dispatch() must return a ResponseInterface
        return $this->router->dispatch($request);
    }
}