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
 * CORS middleware with full preflight support.
 *
 * Features:
 *  • Wildcard support: '*' anywhere in the allow-list allows every origin
 *  • Subdomain wildcards: 'https://*.example.com' matches any subdomain
 *  • Preflight OPTIONS handling with Access-Control-Max-Age
 *  • Supports credentials, exposed headers, allowed methods
 *  • Origin reflection: echoes requesting origin when it matches the allow-list
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string>  $allowedOrigins   Allowed origin URLs; '*' (anywhere in the list)
     *                                        allows every origin, and patterns such as
     *                                        'https://*.example.com' match subdomains.
     * @param list<string>  $allowedMethods   HTTP methods to accept.
     * @param list<string>  $allowedHeaders   Request headers to accept.
     * @param list<string>  $exposedHeaders   Response headers the browser may read.
     * @param bool          $allowCredentials Whether to send credentials header.
     * @param int           $maxAge           Preflight cache duration in seconds.
     */
    public function __construct(
        private readonly array $allowedOrigins   = ['*'],
        private readonly array $allowedMethods   = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private readonly array $allowedHeaders   = ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],
        private readonly array $exposedHeaders   = [],
        private readonly bool  $allowCredentials = false,
        private readonly int   $maxAge           = 86400,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $origin = $request->getHeaderLine('Origin');

        // No Origin header → not a CORS request
        if ($origin === '') {
            return $handler->handle($request);
        }

        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return $handler->handle($request);
        }

        // Preflight request
        if ($request->getMethod() === 'OPTIONS') {
            return $this->preflight($origin);
        }

        // Actual request — add CORS headers to response
        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $origin);
    }

    // ── Internal ───────────────────────────────────────────────

    private function isOriginAllowed(string $origin): bool
    {
        // Literal '*' anywhere in the list allows every origin.
        if (\in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        // Exact origin match.
        if (\in_array($origin, $this->allowedOrigins, true)) {
            return true;
        }

        // Pattern match, e.g. 'https://*.example.com'.
        foreach ($this->allowedOrigins as $allowed) {
            if (\str_contains($allowed, '*') && $this->originMatchesPattern($origin, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match an origin against a wildcard pattern (e.g. 'https://*.example.com').
     * The '*' cannot span the '//' scheme separator.
     */
    private function originMatchesPattern(string $origin, string $pattern): bool
    {
        // '~' delimiter so '/' inside the character class does not need escaping.
        $regex = '~^' . \str_replace('\*', '[^/]+', \preg_quote($pattern, '~')) . '$~i';
        return \preg_match($regex, $origin) === 1;
    }

    private function preflight(string $origin): ResponseInterface
    {
        $response = new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::empty(),
            204,
        );

        $response = $this->addCorsHeaders($response, $origin);

        return $response
            ->withHeader('Access-Control-Allow-Methods', \implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', \implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
    }

    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        // Use '*' only for a wildcard allow-list without credentials; browsers reject
        // 'Access-Control-Allow-Origin: *' when 'Allow-Credentials' is set, so the
        // specific origin is reflected in that case.
        $originValue = (\in_array('*', $this->allowedOrigins, true) && !$this->allowCredentials) ? '*' : $origin;

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $originValue)
            ->withHeader('Vary', 'Origin');

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->exposedHeaders !== []) {
            $response = $response->withHeader('Access-Control-Expose-Headers', \implode(', ', $this->exposedHeaders));
        }

        return $response;
    }
}
