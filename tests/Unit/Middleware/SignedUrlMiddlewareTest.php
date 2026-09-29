<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Middleware\SignedUrlMiddleware;
use MonkeysLegion\Router\SignedUrlGenerator;
use MonkeysLegion\Router\UrlGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SignedUrlMiddlewareTest extends TestCase
{
    private SignedUrlGenerator $generator;
    private SignedUrlMiddleware $middleware;

    protected function setUp(): void
    {
        // UrlGenerator with a simple base URL
        $urlGenerator = new UrlGenerator('http://localhost');
        $this->generator = new SignedUrlGenerator($urlGenerator, str_repeat('secret_key_for_testing_', 1));
        $this->middleware = new SignedUrlMiddleware(
            $this->generator,
            ['/verify-email'],
        );
    }

    private function makeHandler(): \Psr\Http\Server\RequestHandlerInterface
    {
        return new readonly class implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(Stream::createFromString('ok'));
            }
        };
    }

    #[Test]
    public function unprotected_path_passes_through(): void
    {
        $request = new ServerRequest('GET', 'http://localhost/dashboard');

        $response = $this->middleware->process($request, $this->makeHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function protected_path_without_signature_returns_403(): void
    {
        $request = new ServerRequest('GET', 'http://localhost/verify-email');

        $response = $this->middleware->process($request, $this->makeHandler());

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function expired_signed_url_returns_410(): void
    {
        $url = 'http://localhost/verify-email?expires=' . (time() - 3600) . '&signature=abc';

        $request = new ServerRequest('GET', $url);

        $response = $this->middleware->process($request, $this->makeHandler());

        self::assertSame(410, $response->getStatusCode());
    }
}
