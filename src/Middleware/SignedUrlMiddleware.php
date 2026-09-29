<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Router\SignedUrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonKeysLegion Framework — HTTP Package
 *
 * Verifies signed URLs for protected routes.
 *
 * Returns 410 Gone for expired URLs, 403 Forbidden for invalid signatures.
 * Routes not matching the protected patterns pass through untouched.
 *
 * Usage:
 *   $mw = new SignedUrlMiddleware($generator, ['/verify-email', '/reset-password']);
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class SignedUrlMiddleware implements MiddlewareInterface
{
    /**
     * @param SignedUrlGenerator $generator     Signed URL validator.
     * @param list<string>       $protectedPatterns URI prefixes requiring a valid signature.
     */
    public function __construct(
        private readonly SignedUrlGenerator $generator,
        private readonly array $protectedPatterns = [],
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $path = $request->getUri()->getPath();

        // Skip if path doesn't match any protected pattern.
        if (!$this->isProtected($path)) {
            return $handler->handle($request);
        }

        // Reconstruct the full URL with query string for validation.
        $uri  = $request->getUri();
        $url  = (string) $uri;

        // Check expiration separately to return correct status code.
        $queryParams = [];
        parse_str($uri->getQuery(), $queryParams);

        // Check if expired.
        if (isset($queryParams['expires']) && (int) $queryParams['expires'] < time()) {
            return $this->jsonError(410, 'The signed URL has expired.');
        }

        // Validate signature.
        if (!$this->generator->validate($url)) {
            return $this->jsonError(403, 'Invalid signature.');
        }

        return $handler->handle($request);
    }

    /**
     * Check if the path matches any protected pattern.
     */
    private function isProtected(string $path): bool
    {
        if ($this->protectedPatterns === []) {
            return false;
        }

        foreach ($this->protectedPatterns as $pattern) {
            if (str_starts_with($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function jsonError(int $status, string $message): ResponseInterface
    {
        $body = json_encode([
            'status'  => 'error',
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES);

        return new Response(
            Stream::createFromString($body),
            $status,
            ['Content-Type' => 'application/json'],
        );
    }
}
