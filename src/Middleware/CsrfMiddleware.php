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
 * CSRF protection via double-submit cookie pattern (stateless).
 *
 * v2 improvements over v1:
 *  • No direct $_SESSION access — uses request attributes
 *  • Double-submit cookie: token stored in cookie AND request body/header
 *  • Configurable token field name and header name
 *  • Returns 403 Forbidden (not 400) per standard practice
 *  • Timing-safe comparison via hash_equals()
 *
 * Flow:
 *  1. On safe methods (GET/HEAD/OPTIONS): set CSRF cookie if absent
 *  2. On state-changing methods: validate token from body/header matches cookie
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * @param string $cookieName  Name of the CSRF cookie.
     * @param string $fieldName   Form field name for the token.
     * @param string $headerName  HTTP header name for the token.
     * @param int    $tokenLength Token length in bytes (hex-encoded = 2x).
     * @param bool   $secureCookie Only send cookie over HTTPS.
     */
    public function __construct(
        private readonly string $cookieName   = 'csrf_token',
        private readonly string $fieldName    = '_csrf',
        private readonly string $headerName   = 'X-CSRF-Token',
        private readonly int    $tokenLength  = 32,
        private readonly bool   $secureCookie = true,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $method = strtoupper($request->getMethod());

        // Safe methods — ensure cookie exists, attach token to request attribute
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $token = $this->getTokenFromCookie($request);
            if ($token === null) {
                $token = $this->generateToken();
            }

            $request  = $request->withAttribute('csrf_token', $token);
            $response = $handler->handle($request);

            // Set/refresh the CSRF cookie
            return $this->setCookie($response, $token);
        }

        // State-changing methods — validate
        $cookieToken  = $this->getTokenFromCookie($request);
        $requestToken = $this->getTokenFromRequest($request);

        if ($cookieToken === null
            || $requestToken === null
            || !hash_equals($cookieToken, $requestToken)
        ) {
            return $this->reject();
        }

        $request = $request->withAttribute('csrf_token', $cookieToken);

        return $handler->handle($request);
    }

    // ── Internal ───────────────────────────────────────────────

    private function getTokenFromCookie(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();
        return $cookies[$this->cookieName] ?? null;
    }

    private function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        // Check header first
        $header = $request->getHeaderLine($this->headerName);
        if ($header !== '') {
            return $header;
        }

        // Check parsed body
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body[$this->fieldName])) {
            return (string) $body[$this->fieldName];
        }

        return null;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes($this->tokenLength));
    }

    private function setCookie(ResponseInterface $response, string $token): ResponseInterface
    {
        $parts = [
            sprintf('%s=%s', $this->cookieName, $token),
            'Path=/',
            'HttpOnly',
            'SameSite=Strict',
        ];

        if ($this->secureCookie) {
            $parts[] = 'Secure';
        }

        return $response->withAddedHeader('Set-Cookie', implode('; ', $parts));
    }

    private function reject(): ResponseInterface
    {
        $json = json_encode([
            'status'  => 'error',
            'message' => 'CSRF token validation failed.',
        ], JSON_UNESCAPED_SLASHES);

        return new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::createFromString($json),
            403,
            ['Content-Type' => 'application/json'],
        );
    }
}
