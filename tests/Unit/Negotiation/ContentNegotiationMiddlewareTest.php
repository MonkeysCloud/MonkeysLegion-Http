<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Negotiation;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Middleware\ContentNegotiationMiddleware;
use MonkeysLegion\Http\Negotiation\PayloadInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A PSR-7 response that also exposes a serializable payload. This is how a
 * handler can opt in to ContentNegotiationMiddleware serialization while still
 * honoring the PSR-15 ResponseInterface return contract.
 */
final class PayloadResponse extends Response implements PayloadInterface
{
    public function __construct(private readonly mixed $payload)
    {
        parent::__construct(Stream::empty(), 200);
    }

    public function toPayload(): mixed
    {
        return $this->payload;
    }
}

final class PayloadHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new PayloadResponse(['name' => 'Alice', 'age' => 30]);
    }
}

final class PlainResponseHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return Response::json(['raw' => 'response']);
    }
}

final class ContentNegotiationMiddlewareTest extends TestCase
{
    private ContentNegotiationMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new ContentNegotiationMiddleware();
    }

    private function request(string $accept): ServerRequestInterface
    {
        return new \MonkeysLegion\Http\Message\ServerRequest(
            'GET',
            new \MonkeysLegion\Http\Message\Uri('/'),
            Stream::empty(),
            ['Accept' => $accept],
        );
    }

    #[Test]
    public function serializes_payload_to_json(): void
    {
        $response = $this->middleware->process($this->request('application/json'), new PayloadHandler());

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $data = \json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertSame('Alice', $data['name']);
    }

    #[Test]
    public function serializes_payload_to_xml(): void
    {
        $response = $this->middleware->process($this->request('application/xml'), new PayloadHandler());

        $this->assertSame('application/xml', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<name>Alice</name>', $body);
    }

    #[Test]
    public function serializes_payload_to_html(): void
    {
        $response = $this->middleware->process($this->request('text/html'), new PayloadHandler());

        $this->assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Alice', $body);
        $this->assertStringStartsWith('<pre>', $body);
    }

    #[Test]
    public function wildcard_accept_defaults_to_json(): void
    {
        $response = $this->middleware->process($this->request('*/*'), new PayloadHandler());

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function plain_responses_pass_through_with_vary_header(): void
    {
        $response = $this->middleware->process($this->request('text/html'), new PlainResponseHandler());

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Accept', $response->getHeaderLine('Vary'));
        $data = \json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertSame('response', $data['raw']);
    }
}
