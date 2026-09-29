<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use MonkeysLegion\Http\Middleware\CspMiddleware;
use MonkeysLegion\Http\Support\NonceGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;

final class CspMiddlewareTest extends TestCase
{
    private function makeRequest(): \Psr\Http\Message\ServerRequestInterface
    {
        return new \MonkeysLegion\Http\Message\ServerRequest('GET', '/test');
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
    public function sets_content_security_policy_header(): void
    {
        $mw = new CspMiddleware();

        $response = $mw->process($this->makeRequest(), $this->makeHandler());

        self::assertTrue($response->hasHeader('Content-Security-Policy'));
    }

    #[Test]
    public function uses_report_only_header_when_enabled(): void
    {
        $mw = new CspMiddleware(reportOnly: true);

        $response = $mw->process($this->makeRequest(), $this->makeHandler());

        self::assertTrue($response->hasHeader('Content-Security-Policy-Report-Only'));
        self::assertFalse($response->hasHeader('Content-Security-Policy'));
    }

    #[Test]
    public function generates_unique_nonce_per_request(): void
    {
        $mw = new CspMiddleware();

        $resp1 = $mw->process($this->makeRequest(), $this->makeHandler());
        $resp2 = $mw->process($this->makeRequest(), $this->makeHandler());

        $header1 = $resp1->getHeaderLine('Content-Security-Policy');
        $header2 = $resp2->getHeaderLine('Content-Security-Policy');

        // Extract nonces
        preg_match("/'nonce-([a-f0-9]+)'/", $header1, $m1);
        preg_match("/'nonce-([a-f0-9]+)'/", $header2, $m2);

        self::assertNotEmpty($m1, 'First response should contain a nonce');
        self::assertNotEmpty($m2, 'Second response should contain a nonce');
        self::assertNotEquals($m1[1], $m2[1], 'Nonces should be unique per request');
    }

    #[Test]
    public function stores_nonce_on_request_attribute(): void
    {
        $capturedRequest = null;
        $handler = new readonly class($capturedRequest) implements \Psr\Http\Server\RequestHandlerInterface {
            public function __construct(private mixed &$captured) {}

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->captured = $request;
                return new Response(Stream::createFromString('ok'));
            }
        };

        $mw = new CspMiddleware();
        $mw->process($this->makeRequest(), $handler);

        self::assertNotNull($handler->captured ?? null);
        // @phpstan-ignore-next-line
        $nonce = $handler->captured->getAttribute('csp_nonce');
        self::assertNotNull($nonce);
        self::assertSame(64, strlen($nonce), 'Nonce should be 64 hex chars (32 bytes)');
    }

    #[Test]
    public function custom_directives_are_used(): void
    {
        $mw = new CspMiddleware(directives: ['default-src' => "'self' example.com"]);

        $response = $mw->process($this->makeRequest(), $this->makeHandler());

        $header = $response->getHeaderLine('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self' example.com", $header);
    }

    #[Test]
    public function nonce_placeholder_is_replaced_in_header(): void
    {
        $mw = new CspMiddleware(directives: ['script-src' => "'self' {nonce}"]);

        $response = $mw->process($this->makeRequest(), $this->makeHandler());

        $header = $response->getHeaderLine('Content-Security-Policy');
        self::assertStringNotContainsString('{nonce}', $header);
        self::assertStringContainsString("'nonce-", $header);
    }

    #[Test]
    public function nonce_generator_produces_hex_string(): void
    {
        $gen = new NonceGenerator();
        $nonce = $gen->generate();

        self::assertMatchesRegularExpression('/^[a-f0-9]+$/', $nonce);
        self::assertSame(64, strlen($nonce));
    }
}
