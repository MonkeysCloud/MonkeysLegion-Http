<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory) {}

    public function process(ServerRequestInterface $request,
                            RequestHandlerInterface $handler): ResponseInterface
    {
        // toy example: require header “X-Api-Key: secret”
        if ($request->getHeaderLine('X-Api-Key') !== 'secret') {
            $res = $this->responseFactory->createResponse(401);
            $res->getBody()->write('Unauthorized');
            return $res;
        }
        return $handler->handle($request);
    }
}