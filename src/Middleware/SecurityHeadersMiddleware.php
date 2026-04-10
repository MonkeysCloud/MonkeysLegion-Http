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
 * Injects security-related HTTP headers into every response.
 *
 * Three presets available:
 *  • strict  — Maximum security (production APIs)
 *  • relaxed — Development-friendly (allows iframes, inline scripts)
 *  • api     — API-optimized (no CSP, HSTS enabled)
 *
 * All headers are configurable; pass overrides to the constructor.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /** @var array<string, string> */
    private readonly array $headers;

    /**
     * @param string               $preset    One of 'strict', 'relaxed', 'api'.
     * @param array<string, string> $overrides Override specific headers.
     */
    public function __construct(
        string $preset = 'strict',
        array $overrides = [],
    ) {
        $this->headers = array_merge(self::preset($preset), $overrides);
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    // ── Presets ─────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    private static function preset(string $name): array
    {
        return match ($name) {
            'strict' => [
                'X-Content-Type-Options'  => 'nosniff',
                'X-Frame-Options'         => 'DENY',
                'X-XSS-Protection'        => '0',
                'Strict-Transport-Security' => 'max-age=63072000; includeSubDomains; preload',
                'Content-Security-Policy' => "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
                'Referrer-Policy'         => 'strict-origin-when-cross-origin',
                'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=(), payment=()',
            ],
            'relaxed' => [
                'X-Content-Type-Options'  => 'nosniff',
                'X-Frame-Options'         => 'SAMEORIGIN',
                'X-XSS-Protection'        => '0',
                'Referrer-Policy'         => 'no-referrer-when-downgrade',
            ],
            'api' => [
                'X-Content-Type-Options'    => 'nosniff',
                'X-Frame-Options'           => 'DENY',
                'X-XSS-Protection'          => '0',
                'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
                'Referrer-Policy'           => 'strict-origin-when-cross-origin',
                'Permissions-Policy'        => 'camera=(), microphone=(), geolocation=()',
            ],
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown security preset "%s". Use "strict", "relaxed", or "api".',
                $name,
            )),
        };
    }
}
