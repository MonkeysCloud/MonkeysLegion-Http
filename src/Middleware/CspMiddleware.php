<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use MonkeysLegion\Http\Support\NonceGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Content-Security-Policy middleware with per-request nonce support.
 *
 * Generates a unique nonce per request for script-src and style-src.
 * Supports report-only mode for safe rollout.
 * Placeholder {nonce} in directives is replaced with the generated nonce.
 * Nonce is stored as request attribute 'csp_nonce' for template access.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CspMiddleware implements MiddlewareInterface
{
    private const string SELF = "'self'";
    private const string NONE = "'none'";
    private const string NONCE_PLACEHOLDER = "{nonce}";

    /** @var array<string, string> */
    private readonly array $directives;

    /**
     * @param array<string, string> $directives CSP directives.
     *   Use {nonce} placeholder in script-src/style-src for per-request nonce.
     * @param bool                  $reportOnly Use Report-Only header instead of enforcing.
     * @param NonceGenerator|null   $nonceGenerator Inject for testing.
     */
    public function __construct(
        array $directives = [],
        private readonly bool $reportOnly = false,
        private readonly ?NonceGenerator $nonceGenerator = null,
    ) {
        $this->directives = $directives !== [] ? $directives : self::defaultDirectives();
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $nonce = ($this->nonceGenerator ?? new NonceGenerator())->generate();

        // Store nonce on the request for template engine access.
        $request = $request->withAttribute('csp_nonce', $nonce);

        $response = $handler->handle($request);

        $headerValue = $this->buildHeaderValue($nonce);
        $headerName = $this->reportOnly
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        return $response->withHeader($headerName, $headerValue);
    }

    /**
     * Build the CSP header value from directives, replacing {nonce} placeholders.
     */
    private function buildHeaderValue(string $nonce): string
    {
        $parts = [];

        foreach ($this->directives as $directive => $value) {
            $parts[] = $directive . ' ' . str_replace(self::NONCE_PLACEHOLDER, $nonce, $value);
        }

        return implode('; ', $parts);
    }

    /**
     * @return array<string, string>
     */
    private static function defaultDirectives(): array
    {
        $nonceSrc = self::SELF . ' ' . self::NONCE_PLACEHOLDER;

        return [
            'default-src'         => self::SELF,
            'script-src'          => $nonceSrc . " 'strict-dynamic'",
            'style-src'           => $nonceSrc,
            'img-src'             => self::SELF . ' data:',
            'connect-src'         => self::SELF,
            'font-src'            => self::SELF,
            'object-src'          => self::NONE,
            'base-uri'            => self::SELF,
            'frame-ancestors'     => self::NONE,
            'form-action'         => self::SELF,
        ];
    }
}
