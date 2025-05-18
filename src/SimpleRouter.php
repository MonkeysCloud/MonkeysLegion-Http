<?php
declare(strict_types=1);

namespace MonkeysLegion\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SimpleRouter implements RequestHandlerInterface
{
    public function __construct(private ResponseFactoryInterface $factory) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $res = $this->factory->createResponse(200);
        $res->getBody()->write(
            sprintf("Hello from %s %s!", $request->getMethod(), $request->getUri()->getPath())
        );
        return $res;
    }
}