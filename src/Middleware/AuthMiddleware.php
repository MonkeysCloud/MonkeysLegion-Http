<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @param ResponseFactoryInterface $factory       PSR-17 response factory
     * @param string                   $requiredToken Bearer token to accept
     * @param string                   $realm         WWW-Authenticate realm header
     * @param string[]                 $publicPaths   URIs that bypass auth
     */
    public function __construct(
        private readonly ResponseFactoryInterface $factory,
        private readonly string                  $requiredToken,
        private readonly string                  $realm        = 'Protected',
        private readonly array                   $publicPaths  = ['/']
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // 1) Skip authentication on public paths
        if (in_array($path, $this->publicPaths, true)) {
            return $handler->handle($request);
        }

        // 2) Extract and validate Bearer token
        $hdr = $request->getHeaderLine('Authorization');
        if (! preg_match('/^Bearer\s+(.+)$/i', $hdr, $m)
            || $m[1] !== $this->requiredToken
        ) {
            // 401 + challenge
            $resp = $this->factory->createResponse(401);
            return $resp->withHeader(
                'WWW-Authenticate',
                sprintf('Bearer realm="%s"', $this->realm)
            );
        }

        // 3) Authorized
        return $handler->handle($request);
    }
}