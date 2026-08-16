<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Middleware;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Http\Middleware\TrustedProxyMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class IpCaptureHandler implements RequestHandlerInterface
{
    public ?string $resolvedIp = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $ip = $request->getAttribute('client_ip');
        $this->resolvedIp = \is_scalar($ip) ? (string) $ip : null;
        return Response::json(['ok' => true]);
    }
}

final class TrustedProxyMiddlewareTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    private function request(string $remoteAddr, array $headers = []): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('http://localhost/'),
            Stream::empty(),
            $headers,
            '1.1',
            ['REMOTE_ADDR' => $remoteAddr],
        );
    }

    /**
     * @param list<string> $trusted
     */
    private function resolve(ServerRequest $request, array $trusted = ['127.0.0.1']): string
    {
        $handler = new IpCaptureHandler();
        new TrustedProxyMiddleware(trustedProxies: $trusted)->process($request, $handler);
        return (string) $handler->resolvedIp;
    }

    #[Test]
    public function resolves_x_forwarded_for_from_trusted_proxy(): void
    {
        $request = $this->request('127.0.0.1', ['X-Forwarded-For' => '203.0.113.50, 10.0.0.1']);
        $this->assertSame('203.0.113.50', $this->resolve($request));
    }

    #[Test]
    public function resolves_rfc7239_forwarded_header(): void
    {
        $request = $this->request('127.0.0.1', ['Forwarded' => 'for=203.0.113.50;proto=https']);
        $this->assertSame('203.0.113.50', $this->resolve($request));
    }

    #[Test]
    public function resolves_quoted_ipv6_forwarded_header(): void
    {
        $request = $this->request('127.0.0.1', ['Forwarded' => 'for="[2001:db8::1]:4711"']);
        $this->assertSame('2001:db8::1', $this->resolve($request));
    }

    #[Test]
    public function resolves_x_real_ip_header(): void
    {
        $request = $this->request('127.0.0.1', ['X-Real-IP' => '198.51.100.7']);
        $this->assertSame('198.51.100.7', $this->resolve($request));
    }

    #[Test]
    public function cf_connecting_ip_takes_priority(): void
    {
        $request = $this->request('127.0.0.1', [
            'CF-Connecting-IP' => '192.0.2.1',
            'X-Forwarded-For'  => '203.0.113.50',
        ]);
        $this->assertSame('192.0.2.1', $this->resolve($request));
    }

    #[Test]
    public function ignores_headers_from_untrusted_proxies(): void
    {
        $request = $this->request('10.0.0.5', ['X-Forwarded-For' => '203.0.113.50']);
        $this->assertSame('10.0.0.5', $this->resolve($request));
    }

    #[Test]
    public function falls_back_to_remote_addr_for_invalid_forwarded_ip(): void
    {
        $request = $this->request('127.0.0.1', ['X-Forwarded-For' => 'not-an-ip']);
        $this->assertSame('127.0.0.1', $this->resolve($request));
    }

    #[Test]
    public function trusted_proxies_support_cidr_ranges(): void
    {
        $request = $this->request('10.1.2.3', ['X-Forwarded-For' => '203.0.113.50']);
        $this->assertSame('203.0.113.50', $this->resolve($request, ['10.0.0.0/8']));
    }
}
