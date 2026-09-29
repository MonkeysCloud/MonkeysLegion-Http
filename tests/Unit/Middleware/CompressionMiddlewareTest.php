<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Middleware;

use MonkeysLegion\Http\Middleware\CompressionMiddleware;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Tests for CompressionMiddleware (gzip/brotli).
 */
final class CompressionMiddlewareTest extends TestCase
{
    #[Test]
    public function compresses_large_response_with_gzip(): void
    {
        $content = str_repeat('Hello World! This is a test response that is long enough to compress. ', 30);

        $request = $this->makeRequest('GET', ['Accept-Encoding' => 'gzip']);
        $response = $this->makeResponse($content, 200, ['Content-Type' => 'text/html; charset=utf-8']);
        $handler = $this->makeHandler($response);

        $middleware = new CompressionMiddleware(minSize: 1024);
        $result = $middleware->process($request, $handler);

        self::assertTrue($result->hasHeader('Content-Encoding'));
        self::assertSame('gzip', $result->getHeaderLine('Content-Encoding'));
        self::assertTrue($result->hasHeader('Vary'));
        self::assertStringContainsString('Accept-Encoding', $result->getHeaderLine('Vary'));
    }

    #[Test]
    public function skips_small_responses(): void
    {
        $request = $this->makeRequest('GET', ['Accept-Encoding' => 'gzip']);
        $response = $this->makeResponse('tiny', 200, ['Content-Type' => 'text/html']);
        $handler = $this->makeHandler($response);

        $middleware = new CompressionMiddleware(minSize: 1024);
        $result = $middleware->process($request, $handler);

        self::assertFalse($result->hasHeader('Content-Encoding'));
    }

    #[Test]
    public function skips_compressed_mime_types(): void
    {
        $content = str_repeat('binary image data ', 100);

        $request = $this->makeRequest('GET', ['Accept-Encoding' => 'gzip']);
        $response = $this->makeResponse($content, 200, ['Content-Type' => 'image/jpeg']);
        $handler = $this->makeHandler($response);

        $middleware = new CompressionMiddleware(minSize: 1024);
        $result = $middleware->process($request, $handler);

        self::assertFalse($result->hasHeader('Content-Encoding'));
    }

    #[Test]
    public function skips_when_client_does_not_accept_encoding(): void
    {
        $content = str_repeat('Hello World ', 100);

        $request = $this->makeRequest('GET', []);
        $response = $this->makeResponse($content, 200, ['Content-Type' => 'text/html']);
        $handler = $this->makeHandler($response);

        $middleware = new CompressionMiddleware(minSize: 1024);
        $result = $middleware->process($request, $handler);

        self::assertFalse($result->hasHeader('Content-Encoding'));
    }

    #[Test]
    public function skips_304_responses(): void
    {
        $request = $this->makeRequest('GET', ['Accept-Encoding' => 'gzip']);
        $response = $this->makeResponse('', 304, ['Content-Type' => 'text/html']);
        $handler = $this->makeHandler($response);

        $middleware = new CompressionMiddleware(minSize: 1024);
        $result = $middleware->process($request, $handler);

        self::assertFalse($result->hasHeader('Content-Encoding'));
    }

    #[Test]
    public function skips_already_encoded_responses(): void
    {
        $content = str_repeat('Hello World ', 100);

        $request = $this->makeRequest('GET', ['Accept-Encoding' => 'gzip']);
        $response = $this->makeResponse($content, 200, ['Content-Type' => 'text/html', 'Content-Encoding' => 'gzip']);
        $handler = $this->makeHandler($response);

        $middleware = new CompressionMiddleware(minSize: 1024);
        $result = $middleware->process($request, $handler);

        self::assertSame('gzip', $result->getHeaderLine('Content-Encoding'));
    }

    #[Test]
    public function compresses_text_plain(): void
    {
        $content = str_repeat('plain text content ', 100);

        $request = $this->makeRequest('GET', ['Accept-Encoding' => 'gzip']);
        $response = $this->makeResponse($content, 200, ['Content-Type' => 'text/plain']);
        $handler = $this->makeHandler($response);

        $middleware = new CompressionMiddleware(minSize: 1024);
        $result = $middleware->process($request, $handler);

        self::assertSame('gzip', $result->getHeaderLine('Content-Encoding'));
    }

    #[Test]
    public function compresses_json(): void
    {
        $data = array_map(fn($i) => ['id' => $i, 'name' => "Item {$i}", 'description' => str_repeat('desc', 10)], range(1, 50));
        $content = json_encode($data);

        $request = $this->makeRequest('GET', ['Accept-Encoding' => 'gzip']);
        $response = $this->makeResponse($content, 200, ['Content-Type' => 'application/json']);
        $handler = $this->makeHandler($response);

        $middleware = new CompressionMiddleware(minSize: 1024);
        $result = $middleware->process($request, $handler);

        self::assertSame('gzip', $result->getHeaderLine('Content-Encoding'));
    }

    #[Test]
    public function parses_q_values_in_accept_encoding(): void
    {
        $content = str_repeat('Hello World ', 100);

        $request = $this->makeRequest('GET', ['Accept-Encoding' => 'br;q=1.0, gzip;q=0.8']);
        $response = $this->makeResponse($content, 200, ['Content-Type' => 'text/html']);
        $handler = $this->makeHandler($response);

        $middleware = new CompressionMiddleware(minSize: 1024, enableBrotli: false);
        $result = $middleware->process($request, $handler);

        self::assertSame('gzip', $result->getHeaderLine('Content-Encoding'));
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

        return $request;
    }

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
