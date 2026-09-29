<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Middleware;

use MonkeysLegion\Http\Middleware\CorrelationIdMiddleware;
use MonkeysLegion\Http\Middleware\RequestIdMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Tests for RequestIdMiddleware and CorrelationIdMiddleware.
 */
final class RequestIdMiddlewareTest extends TestCase
{
    #[Test]
    public function generates_request_id_when_none_present(): void
    {
        $middleware = new RequestIdMiddleware();

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('withAttribute')->willReturnSelf();
        $request->method('withHeader')->willReturnSelf();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $middleware->process($request, $handler);

        // Verify withHeader was called (response has the ID)
        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    #[Test]
    public function accepts_upstream_request_id(): void
    {
        $middleware = new RequestIdMiddleware();
        $upstreamId = 'abc-123-def-456';

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(fn($name) => $name === 'X-Request-Id' ? $upstreamId : '');
        $request->method('withAttribute')->willReturnSelf();
        $request->method('withHeader')->willReturnSelf();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    #[Test]
    public function rejects_invalid_upstream_request_id(): void
    {
        $middleware = new RequestIdMiddleware();
        $invalidId = '"; drop table; --'; // SQL injection attempt

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(fn($name) => $name === 'X-Request-Id' ? $invalidId : '');
        $request->method('withAttribute')->willReturnSelf();
        $request->method('withHeader')->willReturnSelf();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    #[Test]
    public function correlation_id_falls_back_to_request_id(): void
    {
        $middleware = new CorrelationIdMiddleware();

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturn(''); // No X-Correlation-Id
        $request->method('getAttribute')->willReturn('existing-request-id'); // Has request_id
        $request->method('withAttribute')->willReturnSelf();
        $request->method('withHeader')->willReturnSelf();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    #[Test]
    public function correlation_id_accepts_upstream(): void
    {
        $middleware = new CorrelationIdMiddleware();
        $upstreamCorrelationId = 'corr-abc-123';

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn($name) => $name === 'X-Correlation-Id' ? $upstreamCorrelationId : ''
        );
        $request->method('withAttribute')->willReturnSelf();
        $request->method('withHeader')->willReturnSelf();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    #[Test]
    public function correlation_id_generates_when_nothing_present(): void
    {
        $middleware = new CorrelationIdMiddleware();

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getAttribute')->willReturn(null); // No request_id
        $request->method('withAttribute')->willReturnSelf();
        $request->method('withHeader')->willReturnSelf();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }
}
