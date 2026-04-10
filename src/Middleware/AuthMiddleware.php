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
 * Bearer token authentication middleware with JWT decoding support.
 *
 * v2 improvements over v1:
 *  • Timing-safe token comparison via hash_equals()
 *  • Sets `uid` attribute on request for downstream consumers
 *  • Optional JWT decode callback (no hard dependency on JWT library)
 *  • Configurable public path exclusions with PathMatcher
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @param string        $requiredToken Bearer token to accept (static auth).
     * @param list<string>  $publicPaths   URI paths that bypass authentication.
     * @param string        $realm         WWW-Authenticate realm value.
     * @param callable|null $jwtDecoder    Optional: fn(string $token): array|false
     *                                    Returns claims array or false on invalid token.
     */
    public function __construct(
        private readonly string   $requiredToken = '',
        private readonly array    $publicPaths   = ['/'],
        private readonly string   $realm         = 'Protected',
        private readonly mixed    $jwtDecoder    = null,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Skip auth on public paths
        if (PathMatcher::isMatch($request->getUri()->getPath(), $this->publicPaths)) {
            return $handler->handle($request);
        }

        // Extract Bearer token
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $this->unauthorized();
        }

        $token = $matches[1];

        // JWT decoder mode
        if ($this->jwtDecoder !== null) {
            $claims = ($this->jwtDecoder)($token);
            if ($claims === false || !is_array($claims)) {
                return $this->unauthorized();
            }

            $request = $request
                ->withAttribute('uid', $claims['sub'] ?? $claims['uid'] ?? null)
                ->withAttribute('jwt_claims', $claims);

            return $handler->handle($request);
        }

        // Static token mode — timing-safe comparison
        if ($this->requiredToken === '' || !hash_equals($this->requiredToken, $token)) {
            return $this->unauthorized();
        }

        return $handler->handle($request);
    }

    private function unauthorized(): ResponseInterface
    {
        $json = json_encode([
            'status'  => 'error',
            'message' => 'Unauthorized.',
        ], JSON_UNESCAPED_SLASHES);

        return (new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::createFromString($json),
            401,
            ['Content-Type' => 'application/json'],
        ))->withHeader('WWW-Authenticate', sprintf('Bearer realm="%s"', $this->realm));
    }
}