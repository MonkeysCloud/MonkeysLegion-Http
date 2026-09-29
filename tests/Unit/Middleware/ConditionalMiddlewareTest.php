<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Middleware;

use MonkeysLegion\Http\Middleware\ConditionalMiddleware;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Tests for ConditionalMiddleware (Last-Modified / If-Modified-Since).
 */
final class ConditionalMiddlewareTest extends TestCase
{
    #[Test]
    public function sets_last_modified_from_attribute(): void
    {
        $lastModified = new \DateTimeImmutable('2026-01-15 12:00:00', new \DateTimeZone('GMT'));

        $request = $this->makeRequest('GET', [])
            ->withAttribute('last_modified', $lastModified);

        $response = $this->makeResponse('content', 200, ['Content-Type' => 'text/html']);
        $handler = $this->makeHandler($response);

        $middleware = new ConditionalMiddleware();
        $result = $middleware->process($request, $handler);

        self::assertTrue($result->hasHeader('Last-Modified'));
        self::assertStringContainsString('15 Jan 2026 12:00:00', $result->getHeaderLine('Last-Modified'));
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function sets_last_modified_from_timestamp_attribute(): void
    {
        $timestamp = time() - 3600;

        $request = $this->makeRequest('GET', [])
            ->withAttribute('last_modified', $timestamp);

        $response = $this->makeResponse('content', 200, ['Content-Type' => 'text/html']);
        $handler = $this->makeHandler($response);

        $middleware = new ConditionalMiddleware();
        $result = $middleware->process($request, $handler);

        self::assertTrue($result->hasHeader('Last-Modified'));
    }

    #[Test]
    public function returns_304_when_not_modified(): void
    {
        $lastModified = new \DateTimeImmutable('2026-01-15 12:00:00', new \DateTimeZone('GMT'));

        $request = $this->makeRequest('GET', ['If-Modified-Since' => 'Thu, 15 Jan 2026 12:00:00 GMT'])
            ->withAttribute('last_modified', $lastModified);

        $response = $this->makeResponse('content', 200, ['Content-Type' => 'text/html']);
        $handler = $this->makeHandler($response);

        $middleware = new ConditionalMiddleware();
        $result = $middleware->process($request, $handler);

        self::assertSame(304, $result->getStatusCode());
    }

    #[Test]
    public function returns_304_when_newer_if_modified_since(): void
    {
        $lastModified = new \DateTimeImmutable('2026-01-15 12:00:00', new \DateTimeZone('GMT'));

        $request = $this->makeRequest('GET', ['If-Modified-Since' => 'Thu, 22 Jan 2026 12:00:00 GMT'])
            ->withAttribute('last_modified', $lastModified);

        $response = $this->makeResponse('content', 200, ['Content-Type' => 'text/html']);
        $handler = $this->makeHandler($response);

        $middleware = new ConditionalMiddleware();
        $result = $middleware->process($request, $handler);

        self::assertSame(304, $result->getStatusCode());
    }

    #[Test]
    public function returns_200_when_modified_after_if_modified_since(): void
    {
        $lastModified = new \DateTimeImmutable('2026-01-22 12:00:00', new \DateTimeZone('GMT'));

        $request = $this->makeRequest('GET', ['If-Modified-Since' => 'Thu, 15 Jan 2026 12:00:00 GMT'])
            ->withAttribute('last_modified', $lastModified);

        $response = $this->makeResponse('content', 200, ['Content-Type' => 'text/html']);
        $handler = $this->makeHandler($response);

        $middleware = new ConditionalMiddleware();
        $result = $middleware->process($request, $handler);

        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function skips_post_requests(): void
    {
        $lastModified = new \DateTimeImmutable('2026-01-15 12:00:00', new \DateTimeZone('GMT'));

        $request = $this->makeRequest('POST', ['If-Modified-Since' => 'Thu, 15 Jan 2026 12:00:00 GMT'])
            ->withAttribute('last_modified', $lastModified);

        $response = $this->makeResponse('content', 200, ['Content-Type' => 'text/html']);
        $handler = $this->makeHandler($response);

        $middleware = new ConditionalMiddleware();
        $result = $middleware->process($request, $handler);

        self::assertSame(200, $result->getStatusCode());
        self::assertFalse($result->hasHeader('Last-Modified'));
    }

    #[Test]
    public function skips_non_2xx_responses(): void
    {
        $lastModified = new \DateTimeImmutable('2026-01-15 12:00:00', new \DateTimeZone('GMT'));

        $request = $this->makeRequest('GET', ['If-Modified-Since' => 'Thu, 15 Jan 2026 12:00:00 GMT'])
            ->withAttribute('last_modified', $lastModified);

        $response = $this->makeResponse('error', 500, ['Content-Type' => 'text/html']);
        $handler = $this->makeHandler($response);

        $middleware = new ConditionalMiddleware();
        $result = $middleware->process($request, $handler);
        self::assertSame(500, $result->getStatusCode());
    }

    #[Test]
    public function preserves_existing_last_modified_header(): void
    {
        $existingLastModified = 'Sat, 10 Jan 2026 08:00:00 GMT';

        $request = $this->makeRequest('GET', []);
        $response = $this->makeResponse('content', 200, [
            'Content-Type' => 'text/html',
            'Last-Modified' => $existingLastModified,
        ]);
        $handler = $this->makeHandler($response);

        $middleware = new ConditionalMiddleware();
        $result = $middleware->process($request, $handler);

        self::assertSame($existingLastModified, $result->getHeaderLine('Last-Modified'));
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function makeRequest(string $method, array $headers): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);

        $headerMap = [];
        foreach ($headers as $name => $value) {
            $headerMap[strtolower($name)] = [$value];
        }

        $request->method('hasHeader')->willReturnCallback(fn($n) => isset($headerMap[strtolower($n)]));
        $request->method('getHeaderLine')->willReturnCallback(fn($n) => $headerMap[strtolower($n)][0] ?? '');
        $request->method('getHeader')->willReturnCallback(fn($n) => $headerMap[strtolower($n)] ?? []);
        $request->method('withAttribute')->willReturnSelf();
        $request->method('getAttribute')->willReturnCallback(fn($n, $d = null) => $this->requestAttrs[$n] ?? $d);

        // Store attributes for withAttribute chaining
        $this->requestAttrs = [];
        $request->method('withAttribute')->willReturnCallback(function($n, $v) use ($request) {
            $this->requestAttrs[$n] = $v;
            return $request;
        });

        return $request;
    }

    private array $requestAttrs = [];

    private function makeResponse(string $body, int $status, array $headers): ResponseInterface
    {
        $stream = Stream::createFromString($body);
        return new Response($stream, $status, $headers);
    }

    private function makeHandler(ResponseInterface $response): RequestHandlerInterface
    {
        return new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }
}
