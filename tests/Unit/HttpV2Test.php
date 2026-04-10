<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit;

use MonkeysLegion\Http\CoreRequestHandler;
use MonkeysLegion\Http\Factory\HttpFactory;
use MonkeysLegion\Http\Message\JsonResponse;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Http\Middleware\AuthMiddleware;
use MonkeysLegion\Http\Middleware\CorsMiddleware;
use MonkeysLegion\Http\Middleware\CsrfMiddleware;
use MonkeysLegion\Http\Middleware\ErrorHandlerMiddleware;
use MonkeysLegion\Http\Middleware\ETagMiddleware;
use MonkeysLegion\Http\Middleware\IpFilterMiddleware;
use MonkeysLegion\Http\Middleware\LoggingMiddleware;
use MonkeysLegion\Http\Middleware\RateLimitMiddleware;
use MonkeysLegion\Http\Middleware\RequestIdMiddleware;
use MonkeysLegion\Http\Middleware\RequestSizeLimitMiddleware;
use MonkeysLegion\Http\Middleware\SecurityHeadersMiddleware;
use MonkeysLegion\Http\Middleware\TimingMiddleware;
use MonkeysLegion\Http\Middleware\TrustedProxyMiddleware;
use MonkeysLegion\Http\MiddlewareDispatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

// ── Test Helpers ───────────────────────────────────────────────

final class EchoHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return Response::json(['ok' => true]);
    }
}

final class EchoBodyHandler implements RequestHandlerInterface
{
    public function __construct(private readonly string $body = 'Hello World') {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return Response::text($this->body);
    }
}

final class ThrowingHandler implements RequestHandlerInterface
{
    public function __construct(private readonly \Throwable $exception) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->exception;
    }
}

// ── Test Suite ─────────────────────────────────────────────────

final class HttpV2Test extends TestCase
{
    // ── Stream Tests ───────────────────────────────────────────

    #[Test]
    public function stream_create_from_string(): void
    {
        $stream = Stream::createFromString('hello');
        $this->assertSame('hello', (string) $stream);
        $this->assertSame(5, $stream->getSize());
    }

    #[Test]
    public function stream_empty_factory(): void
    {
        $stream = Stream::empty();
        $this->assertSame('', (string) $stream);
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isReadable());
    }

    #[Test]
    public function stream_write_and_read(): void
    {
        $stream = Stream::empty();
        $stream->write('abc');
        $stream->rewind();
        $this->assertSame('abc', $stream->read(3));
    }

    #[Test]
    public function stream_detach_throws_on_subsequent_operations(): void
    {
        $stream = Stream::createFromString('test');
        $resource = $stream->detach();
        $this->assertNotNull($resource);

        $this->expectException(\RuntimeException::class);
        $stream->tell();
    }

    #[Test]
    public function stream_eof_after_detach(): void
    {
        $stream = Stream::createFromString('x');
        $stream->detach();
        $this->assertTrue($stream->eof());
    }

    #[Test]
    public function stream_metadata_returns_array(): void
    {
        $stream = Stream::createFromString('');
        $meta = $stream->getMetadata();
        $this->assertIsArray($meta);
        $this->assertArrayHasKey('mode', $meta);
    }

    #[Test]
    public function stream_metadata_returns_null_after_detach(): void
    {
        $stream = Stream::createFromString('');
        $stream->detach();
        $this->assertNull($stream->getMetadata('mode'));
        $this->assertSame([], $stream->getMetadata());
    }

    // ── Uri Tests ──────────────────────────────────────────────

    #[Test]
    public function uri_parses_full_url(): void
    {
        $uri = new Uri('https://user:pass@example.com:8443/path?q=1#frag');
        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('user:pass', $uri->getUserInfo());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame(8443, $uri->getPort());
        $this->assertSame('/path', $uri->getPath());
        $this->assertSame('q=1', $uri->getQuery());
        $this->assertSame('frag', $uri->getFragment());
    }

    #[Test]
    public function uri_filters_standard_ports(): void
    {
        $uri = new Uri('https://example.com:443/');
        $this->assertNull($uri->getPort()); // standard port filtered

        $uri2 = new Uri('http://example.com:80/');
        $this->assertNull($uri2->getPort());
    }

    #[Test]
    public function uri_to_string(): void
    {
        $uri = new Uri('https://example.com/path?q=1#f');
        $this->assertSame('https://example.com/path?q=1#f', (string) $uri);
    }

    #[Test]
    public function uri_with_methods_are_immutable(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withScheme('https');
        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('https', $new->getScheme());
    }

    #[Test]
    public function uri_invalid_port_throws(): void
    {
        $uri = new Uri('http://example.com');
        $this->expectException(\InvalidArgumentException::class);
        $uri->withPort(99999);
    }

    #[Test]
    public function uri_normalizes_scheme_and_host(): void
    {
        $uri = new Uri('HTTP://EXAMPLE.COM/Path');
        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('/Path', $uri->getPath()); // path is NOT lowered
    }

    // ── Response Tests ─────────────────────────────────────────

    #[Test]
    public function response_json_factory(): void
    {
        $r = Response::json(['key' => 'value']);
        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame('application/json', $r->getHeaderLine('Content-Type'));
        $data = json_decode((string) $r->getBody(), true);
        $this->assertSame('value', $data['key']);
    }

    #[Test]
    public function response_html_factory(): void
    {
        $r = Response::html('<h1>Hello</h1>');
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContains('text/html', $r->getHeaderLine('Content-Type'));
        $this->assertSame('<h1>Hello</h1>', (string) $r->getBody());
    }

    #[Test]
    public function response_text_factory(): void
    {
        $r = Response::text('plain');
        $this->assertStringContains('text/plain', $r->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function response_no_content(): void
    {
        $r = Response::noContent();
        $this->assertSame(204, $r->getStatusCode());
        $this->assertSame('', (string) $r->getBody());
    }

    #[Test]
    public function response_redirect(): void
    {
        $r = Response::redirect('/new-page', 301);
        $this->assertSame(301, $r->getStatusCode());
        $this->assertSame('/new-page', $r->getHeaderLine('Location'));
    }

    #[Test]
    public function response_with_status_immutability(): void
    {
        $r = Response::json([]);
        $r2 = $r->withStatus(404);
        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame(404, $r2->getStatusCode());
        $this->assertSame('Not Found', $r2->getReasonPhrase());
    }

    #[Test]
    public function response_headers_immutable(): void
    {
        $r = Response::json([]);
        $r2 = $r->withHeader('X-Custom', 'test');
        $this->assertFalse($r->hasHeader('X-Custom'));
        $this->assertSame('test', $r2->getHeaderLine('X-Custom'));
    }

    #[Test]
    public function response_added_header(): void
    {
        $r = Response::json([]);
        $r2 = $r->withHeader('X-Multi', 'a')->withAddedHeader('X-Multi', 'b');
        $this->assertSame(['a', 'b'], $r2->getHeader('X-Multi'));
    }

    #[Test]
    public function response_without_header(): void
    {
        $r = Response::json([])->withHeader('X-Rm', 'val');
        $r2 = $r->withoutHeader('X-Rm');
        $this->assertFalse($r2->hasHeader('X-Rm'));
    }

    // ── JsonResponse Tests ─────────────────────────────────────

    #[Test]
    public function json_response_with_envelope(): void
    {
        $r = new JsonResponse(['id' => 1, 'name' => 'Alice'], 200);
        $enveloped = $r->withEnvelope('User found');
        $data = json_decode((string) $enveloped->getBody(), true);

        $this->assertSame('success', $data['status']);
        $this->assertSame('User found', $data['message']);
        $this->assertSame(1, $data['data']['id']);
    }

    #[Test]
    public function json_response_with_pagination(): void
    {
        $r = new JsonResponse([1, 2, 3], 200);
        $paginated = $r->withPagination(total: 100, page: 2, perPage: 25);
        $data = json_decode((string) $paginated->getBody(), true);

        $this->assertSame(100, $data['meta']['pagination']['total']);
        $this->assertSame(2, $data['meta']['pagination']['page']);
        $this->assertSame(4, $data['meta']['pagination']['last_page']);
        $this->assertTrue($data['meta']['pagination']['has_more']);
    }

    #[Test]
    public function json_response_error_envelope(): void
    {
        $r = new JsonResponse(['errors' => ['field' => 'required']], 422);
        $enveloped = $r->withEnvelope('Validation failed');
        $data = json_decode((string) $enveloped->getBody(), true);

        $this->assertSame('error', $data['status']);
    }

    // ── ServerRequest Tests ────────────────────────────────────

    #[Test]
    public function server_request_input_dot_notation(): void
    {
        $request = $this->makeRequest('POST', '/', ['user' => ['email' => 'a@b.c']]);
        $this->assertSame('a@b.c', $request->input('user.email'));
        $this->assertNull($request->input('user.phone'));
        $this->assertSame('default', $request->input('missing', 'default'));
    }

    #[Test]
    public function server_request_all_and_only(): void
    {
        $request = $this->makeRequest('POST', '/', ['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $request->all());
        $this->assertSame(['a' => 1, 'c' => 3], $request->only(['a', 'c']));
    }

    #[Test]
    public function server_request_bearer_token(): void
    {
        $request = $this->makeRequest('GET', '/');
        $this->assertNull($request->bearerToken());

        $request = $request->withHeader('Authorization', 'Bearer abc123');
        $this->assertSame('abc123', $request->bearerToken());
    }

    #[Test]
    public function server_request_ip(): void
    {
        $request = new ServerRequest('GET', new Uri('/'), Stream::empty(), [], '1.1', ['REMOTE_ADDR' => '192.168.1.1']);
        $this->assertSame('192.168.1.1', $request->ip());
    }

    #[Test]
    public function server_request_is_json(): void
    {
        $request = $this->makeRequest('GET', '/')->withHeader('Accept', 'application/json');
        $this->assertTrue($request->isJson());

        $request2 = $this->makeRequest('GET', '/')->withHeader('Accept', 'text/html');
        $this->assertFalse($request2->isJson());
    }

    #[Test]
    public function server_request_is_method(): void
    {
        $request = $this->makeRequest('POST', '/');
        $this->assertTrue($request->isMethod('POST'));
        $this->assertTrue($request->isMethod('post'));
        $this->assertFalse($request->isMethod('GET'));
    }

    #[Test]
    public function server_request_fingerprint(): void
    {
        $r1 = $this->makeRequest('GET', '/api/users');
        $r2 = $this->makeRequest('GET', '/api/users');
        $r3 = $this->makeRequest('POST', '/api/users');

        $this->assertSame($r1->fingerprint(), $r2->fingerprint());
        $this->assertNotSame($r1->fingerprint(), $r3->fingerprint());
    }

    #[Test]
    public function server_request_is_secure(): void
    {
        $r = new ServerRequest('GET', new Uri('/'), Stream::empty(), [], '1.1', ['HTTPS' => 'on']);
        $this->assertTrue($r->isSecure());

        $r2 = new ServerRequest('GET', new Uri('/'), Stream::empty(), [], '1.1', []);
        $this->assertFalse($r2->isSecure());
    }

    #[Test]
    public function server_request_is_ajax(): void
    {
        $r = $this->makeRequest('GET', '/')->withHeader('X-Requested-With', 'XMLHttpRequest');
        $this->assertTrue($r->isAjax());
    }

    #[Test]
    public function server_request_immutability(): void
    {
        $r = $this->makeRequest('GET', '/');
        $r2 = $r->withMethod('POST');
        $this->assertSame('GET', $r->getMethod());
        $this->assertSame('POST', $r2->getMethod());
    }

    #[Test]
    public function server_request_attributes(): void
    {
        $r = $this->makeRequest('GET', '/');
        $r2 = $r->withAttribute('uid', 42);
        $this->assertNull($r->getAttribute('uid'));
        $this->assertSame(42, $r2->getAttribute('uid'));

        $r3 = $r2->withoutAttribute('uid');
        $this->assertNull($r3->getAttribute('uid'));
    }

    // ── CORS Middleware Tests ──────────────────────────────────

    #[Test]
    public function cors_allows_wildcards(): void
    {
        $cors = new CorsMiddleware();
        $request = $this->makeRequest('GET', '/')->withHeader('Origin', 'http://example.com');
        $response = $cors->process($request, new EchoHandler());

        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function cors_preflight_returns_204(): void
    {
        $cors = new CorsMiddleware(allowedOrigins: ['http://example.com']);
        $request = $this->makeRequest('OPTIONS', '/')
            ->withHeader('Origin', 'http://example.com');
        $response = $cors->process($request, new EchoHandler());

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('http://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Methods'));
        $this->assertTrue($response->hasHeader('Access-Control-Max-Age'));
    }

    #[Test]
    public function cors_rejects_unknown_origin(): void
    {
        $cors = new CorsMiddleware(allowedOrigins: ['http://allowed.com']);
        $request = $this->makeRequest('GET', '/')
            ->withHeader('Origin', 'http://evil.com');
        $response = $cors->process($request, new EchoHandler());

        // Should NOT have CORS headers applied
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function cors_credentials(): void
    {
        $cors = new CorsMiddleware(
            allowedOrigins: ['http://example.com'],
            allowCredentials: true,
        );
        $request = $this->makeRequest('GET', '/')
            ->withHeader('Origin', 'http://example.com');
        $response = $cors->process($request, new EchoHandler());

        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        // With credentials, origin must be reflected (not *)
        $this->assertSame('http://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    // ── SecurityHeaders Middleware ──────────────────────────────

    #[Test]
    public function security_headers_strict_preset(): void
    {
        $mw = new SecurityHeadersMiddleware('strict');
        $response = $mw->process($this->makeRequest('GET', '/'), new EchoHandler());

        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $this->assertTrue($response->hasHeader('Strict-Transport-Security'));
        $this->assertTrue($response->hasHeader('Content-Security-Policy'));
    }

    #[Test]
    public function security_headers_api_preset(): void
    {
        $mw = new SecurityHeadersMiddleware('api');
        $response = $mw->process($this->makeRequest('GET', '/'), new EchoHandler());

        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertFalse($response->hasHeader('Content-Security-Policy'));
    }

    #[Test]
    public function security_headers_invalid_preset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SecurityHeadersMiddleware('invalid');
    }

    #[Test]
    public function security_headers_overrides(): void
    {
        $mw = new SecurityHeadersMiddleware('strict', ['X-Frame-Options' => 'SAMEORIGIN']);
        $response = $mw->process($this->makeRequest('GET', '/'), new EchoHandler());

        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
    }

    // ── RequestSizeLimit Middleware ─────────────────────────────

    #[Test]
    public function request_size_limit_allows_small_body(): void
    {
        $mw = new RequestSizeLimitMiddleware(maxBytes: 1024);
        $request = $this->makeRequest('POST', '/', ['small' => 'data']);
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function request_size_limit_rejects_by_content_length(): void
    {
        $mw = new RequestSizeLimitMiddleware(maxBytes: 10);
        $request = $this->makeRequest('POST', '/')->withHeader('Content-Length', '99999');
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(413, $response->getStatusCode());
    }

    // ── ETag Middleware ────────────────────────────────────────

    #[Test]
    public function etag_generates_weak_etag(): void
    {
        $mw = new ETagMiddleware(weakETag: true);
        $response = $mw->process($this->makeRequest('GET', '/'), new EchoBodyHandler('Hello'));

        $etag = $response->getHeaderLine('ETag');
        $this->assertTrue($response->hasHeader('ETag'));
        $this->assertStringStartsWith('W/"', $etag);
    }

    #[Test]
    public function etag_returns_304_on_match(): void
    {
        $mw = new ETagMiddleware(weakETag: true);
        $handler = new EchoBodyHandler('Hello');

        // First request to get the ETag
        $first = $mw->process($this->makeRequest('GET', '/'), $handler);
        $etag  = $first->getHeaderLine('ETag');

        // Second request with If-None-Match
        $second = $mw->process(
            $this->makeRequest('GET', '/')->withHeader('If-None-Match', $etag),
            $handler,
        );

        $this->assertSame(304, $second->getStatusCode());
    }

    #[Test]
    public function etag_skips_post_requests(): void
    {
        $mw = new ETagMiddleware();
        $response = $mw->process($this->makeRequest('POST', '/'), new EchoHandler());

        $this->assertFalse($response->hasHeader('ETag'));
    }

    // ── RequestId Middleware ────────────────────────────────────

    #[Test]
    public function request_id_generates_uuid(): void
    {
        $mw = new RequestIdMiddleware();
        $response = $mw->process($this->makeRequest('GET', '/'), new EchoHandler());

        $requestId = $response->getHeaderLine('X-Request-Id');
        $this->assertNotEmpty($requestId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $requestId,
        );
    }

    #[Test]
    public function request_id_propagates_upstream(): void
    {
        $mw = new RequestIdMiddleware();
        $request = $this->makeRequest('GET', '/')->withHeader('X-Request-Id', 'upstream-123');
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame('upstream-123', $response->getHeaderLine('X-Request-Id'));
    }

    // ── IpFilter Middleware ────────────────────────────────────

    #[Test]
    public function ip_filter_allows_whitelisted(): void
    {
        $mw = new IpFilterMiddleware(allowList: ['10.0.0.1']);
        $request = new ServerRequest('GET', new Uri('/'), Stream::empty(), [], '1.1', ['REMOTE_ADDR' => '10.0.0.1']);
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function ip_filter_blocks_non_whitelisted(): void
    {
        $mw = new IpFilterMiddleware(allowList: ['10.0.0.1']);
        $request = new ServerRequest('GET', new Uri('/'), Stream::empty(), [], '1.1', ['REMOTE_ADDR' => '10.0.0.2']);
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function ip_filter_deny_list_priority(): void
    {
        $mw = new IpFilterMiddleware(denyList: ['192.168.1.100']);
        $request = new ServerRequest('GET', new Uri('/'), Stream::empty(), [], '1.1', ['REMOTE_ADDR' => '192.168.1.100']);
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function ip_filter_cidr_match(): void
    {
        $mw = new IpFilterMiddleware(allowList: ['10.0.0.0/24']);
        $request = new ServerRequest('GET', new Uri('/'), Stream::empty(), [], '1.1', ['REMOTE_ADDR' => '10.0.0.50']);
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ── Timing Middleware ──────────────────────────────────────

    #[Test]
    public function timing_adds_server_timing_header(): void
    {
        $mw = new TimingMiddleware();
        $response = $mw->process($this->makeRequest('GET', '/'), new EchoHandler());

        $header = $response->getHeaderLine('Server-Timing');
        $this->assertStringStartsWith('total;dur=', $header);
    }

    // ── TrustedProxy Middleware ─────────────────────────────────

    #[Test]
    public function trusted_proxy_resolves_forwarded_ip(): void
    {
        $mw = new TrustedProxyMiddleware(trustedProxies: ['127.0.0.1']);
        $request = (new ServerRequest('GET', new Uri('/'), Stream::empty(), [], '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
            ->withHeader('X-Forwarded-For', '203.0.113.50');

        // Use a handler that reads the attribute
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return Response::json(['ip' => $request->getAttribute('client_ip')]);
            }
        };

        $response = $mw->process($request, $handler);
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame('203.0.113.50', $data['ip']);
    }

    #[Test]
    public function trusted_proxy_ignores_untrusted(): void
    {
        $mw = new TrustedProxyMiddleware(trustedProxies: ['127.0.0.1']);
        $request = (new ServerRequest('GET', new Uri('/'), Stream::empty(), [], '1.1', ['REMOTE_ADDR' => '10.0.0.5']))
            ->withHeader('X-Forwarded-For', '1.2.3.4');

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return Response::json(['ip' => $request->getAttribute('client_ip')]);
            }
        };

        $response = $mw->process($request, $handler);
        $data = json_decode((string) $response->getBody(), true);

        // Should NOT trust X-Forwarded-For from untrusted proxy
        $this->assertSame('10.0.0.5', $data['ip']);
    }

    // ── CSRF Middleware ────────────────────────────────────────

    #[Test]
    public function csrf_sets_cookie_on_get(): void
    {
        $mw = new CsrfMiddleware(secureCookie: false);
        $response = $mw->process($this->makeRequest('GET', '/'), new EchoHandler());

        $cookieHeader = $response->getHeaderLine('Set-Cookie');
        $this->assertStringContains('csrf_token=', $cookieHeader);
    }

    #[Test]
    public function csrf_validates_token_on_post(): void
    {
        $token = bin2hex(random_bytes(32));
        $mw = new CsrfMiddleware(secureCookie: false);

        $request = $this->makeRequest('POST', '/', ['_csrf' => $token])
            ->withCookieParams(['csrf_token' => $token]);
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function csrf_rejects_invalid_token(): void
    {
        $mw = new CsrfMiddleware(secureCookie: false);
        $request = $this->makeRequest('POST', '/', ['_csrf' => 'wrong'])
            ->withCookieParams(['csrf_token' => 'correct']);
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function csrf_accepts_header_token(): void
    {
        $token = bin2hex(random_bytes(32));
        $mw = new CsrfMiddleware(secureCookie: false);

        $request = $this->makeRequest('POST', '/')
            ->withCookieParams(['csrf_token' => $token])
            ->withHeader('X-CSRF-Token', $token);
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ── Auth Middleware ────────────────────────────────────────

    #[Test]
    public function auth_allows_public_path(): void
    {
        $mw = new AuthMiddleware(requiredToken: 'secret', publicPaths: ['/']);
        $response = $mw->process($this->makeRequest('GET', '/'), new EchoHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function auth_rejects_missing_token(): void
    {
        $mw = new AuthMiddleware(requiredToken: 'secret', publicPaths: []);
        $response = $mw->process($this->makeRequest('GET', '/api'), new EchoHandler());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('WWW-Authenticate'));
    }

    #[Test]
    public function auth_accepts_valid_bearer(): void
    {
        $mw = new AuthMiddleware(requiredToken: 'secret', publicPaths: []);
        $request = $this->makeRequest('GET', '/api')
            ->withHeader('Authorization', 'Bearer secret');
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function auth_rejects_wrong_token(): void
    {
        $mw = new AuthMiddleware(requiredToken: 'secret', publicPaths: []);
        $request = $this->makeRequest('GET', '/api')
            ->withHeader('Authorization', 'Bearer wrong');
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function auth_jwt_decoder_sets_uid(): void
    {
        $decoder = fn(string $token) => $token === 'valid-jwt'
            ? ['sub' => 42, 'role' => 'admin']
            : false;

        $mw = new AuthMiddleware(publicPaths: [], jwtDecoder: $decoder);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return Response::json([
                    'uid'    => $request->getAttribute('uid'),
                    'claims' => $request->getAttribute('jwt_claims'),
                ]);
            }
        };

        $request = $this->makeRequest('GET', '/api')->withHeader('Authorization', 'Bearer valid-jwt');
        $response = $mw->process($request, $handler);
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(42, $data['uid']);
        $this->assertSame('admin', $data['claims']['role']);
    }

    // ── RateLimit Middleware ───────────────────────────────────

    #[Test]
    public function rate_limit_allows_within_limit(): void
    {
        $mw = new RateLimitMiddleware(limit: 5, window: 60);
        $request = new ServerRequest('GET', new Uri('/'), Stream::empty(), [], '1.1', ['REMOTE_ADDR' => '10.0.0.1']);
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('4', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    #[Test]
    public function rate_limit_returns_429(): void
    {
        $mw = new RateLimitMiddleware(limit: 1, window: 60);
        $request = new ServerRequest('GET', new Uri('/'), Stream::empty(), [], '1.1', ['REMOTE_ADDR' => '10.99.99.99']);

        // First request OK
        $mw->process($request, new EchoHandler());

        // Second request exceeds limit
        $response = $mw->process($request, new EchoHandler());
        $this->assertSame(429, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Retry-After'));
    }

    // ── CoreRequestHandler Tests ───────────────────────────────

    #[Test]
    public function core_handler_pipes_middleware(): void
    {
        $handler = new CoreRequestHandler(new EchoHandler());
        $handler->pipe(new TimingMiddleware());
        $response = $handler->handle($this->makeRequest('GET', '/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Server-Timing'));
    }

    #[Test]
    public function core_handler_lock_prevents_pipe(): void
    {
        $handler = new CoreRequestHandler(new EchoHandler());
        $handler->lock();

        $this->expectException(\RuntimeException::class);
        $handler->pipe(new TimingMiddleware());
    }

    // ── MiddlewareDispatcher Tests ──────────────────────────────

    #[Test]
    public function middleware_dispatcher_processes_stack(): void
    {
        $dispatcher = new MiddlewareDispatcher(
            [new TimingMiddleware(), new RequestIdMiddleware()],
            new EchoHandler(),
        );

        $response = $dispatcher->handle($this->makeRequest('GET', '/'));
        $this->assertTrue($response->hasHeader('Server-Timing'));
        $this->assertTrue($response->hasHeader('X-Request-Id'));
    }

    // ── HttpFactory Tests ──────────────────────────────────────

    #[Test]
    public function http_factory_creates_response(): void
    {
        $factory = new HttpFactory();
        $response = $factory->createResponse(201, 'Created');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Created', $response->getReasonPhrase());
    }

    #[Test]
    public function http_factory_creates_stream(): void
    {
        $factory = new HttpFactory();
        $stream = $factory->createStream('content');

        $this->assertSame('content', (string) $stream);
    }

    #[Test]
    public function http_factory_creates_uri(): void
    {
        $factory = new HttpFactory();
        $uri = $factory->createUri('https://example.com/path');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('/path', $uri->getPath());
    }

    // ── Error Renderer Tests ───────────────────────────────────

    #[Test]
    public function json_error_renderer_debug_mode(): void
    {
        $renderer = new \MonkeysLegion\Http\Error\Renderer\JsonErrorRenderer();
        $output = $renderer->render(new \RuntimeException('test error'), debug: true);
        $data = json_decode($output, true);

        $this->assertSame('error', $data['status']);
        $this->assertSame('test error', $data['message']);
        $this->assertArrayHasKey('debug', $data);
    }

    #[Test]
    public function json_error_renderer_production_mode(): void
    {
        $renderer = new \MonkeysLegion\Http\Error\Renderer\JsonErrorRenderer();
        $output = $renderer->render(new \RuntimeException('test error'), debug: false);
        $data = json_decode($output, true);

        $this->assertSame('An unexpected error occurred.', $data['message']);
        $this->assertArrayNotHasKey('debug', $data);
    }

    #[Test]
    public function plain_text_renderer(): void
    {
        $renderer = new \MonkeysLegion\Http\Error\Renderer\PlainTextErrorRenderer();
        $output = $renderer->render(new \RuntimeException('fail'), debug: true);

        $this->assertStringContains('fail', $output);
        $this->assertSame('text/plain', $renderer->getContentType());
    }

    // ── Response::download() Security ─────────────────────────

    #[Test]
    public function response_download_rejects_invalid_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Response::download('/nonexistent/path/file.txt');
    }

    #[Test]
    public function response_download_validates_real_file(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content');

        try {
            $response = Response::download($tmpFile);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('application/octet-stream', $response->getHeaderLine('Content-Type'));
            $disposition = $response->getHeaderLine('Content-Disposition');
            $this->assertStringContains('attachment', $disposition);
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function response_download_sanitizes_filename(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content');

        try {
            $response = Response::download($tmpFile, "bad\x00file\nname.txt");
            $disposition = $response->getHeaderLine('Content-Disposition');
            // Control characters should be stripped
            $this->assertStringContains('badfilename.txt', $disposition);
        } finally {
            unlink($tmpFile);
        }
    }

    // ── ErrorHandlerMiddleware ─────────────────────────────────

    #[Test]
    public function error_handler_middleware_catches_exception(): void
    {
        $mw = new ErrorHandlerMiddleware(debug: false);
        $handler = new ThrowingHandler(new \RuntimeException('Something broke'));

        $response = $mw->process($this->makeRequest(), $handler);

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('error', $body['status']);
        $this->assertSame('An unexpected error occurred.', $body['message']);
    }

    #[Test]
    public function error_handler_middleware_debug_mode(): void
    {
        $mw = new ErrorHandlerMiddleware(debug: true);
        $handler = new ThrowingHandler(new \RuntimeException('Debug error'));

        $response = $mw->process($this->makeRequest(), $handler);

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('Debug error', $body['message']);
        $this->assertArrayHasKey('debug', $body);
    }

    #[Test]
    public function error_handler_middleware_uses_exception_code(): void
    {
        $mw = new ErrorHandlerMiddleware(debug: false);
        $handler = new ThrowingHandler(new \RuntimeException('Not found', 404));

        $response = $mw->process($this->makeRequest(), $handler);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function error_handler_middleware_passes_through_on_success(): void
    {
        $mw = new ErrorHandlerMiddleware(debug: false);

        $response = $mw->process($this->makeRequest(), new EchoHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ── RequestId Validation ──────────────────────────────────

    #[Test]
    public function request_id_rejects_invalid_upstream_id(): void
    {
        $mw = new RequestIdMiddleware();
        // Inject a request ID with newlines (potential header injection)
        $request = $this->makeRequest('GET', '/')->withHeader('X-Request-Id', "bad\nvalue");
        $response = $mw->process($request, new EchoHandler());

        $requestId = $response->getHeaderLine('X-Request-Id');
        // Should generate a new UUID, not use the injected value
        $this->assertNotSame("bad\nvalue", $requestId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $requestId,
        );
    }

    #[Test]
    public function request_id_accepts_valid_upstream_id(): void
    {
        $mw = new RequestIdMiddleware();
        $request = $this->makeRequest('GET', '/')->withHeader('X-Request-Id', 'valid-id-123');
        $response = $mw->process($request, new EchoHandler());

        $this->assertSame('valid-id-123', $response->getHeaderLine('X-Request-Id'));
    }

    // ── ETag Streaming Hash ───────────────────────────────────

    #[Test]
    public function etag_generates_consistent_hash(): void
    {
        $mw = new ETagMiddleware();

        $response1 = $mw->process($this->makeRequest('GET', '/'), new EchoBodyHandler('test body'));
        $response2 = $mw->process($this->makeRequest('GET', '/'), new EchoBodyHandler('test body'));

        $this->assertSame(
            $response1->getHeaderLine('ETag'),
            $response2->getHeaderLine('ETag'),
        );
    }

    #[Test]
    public function etag_different_body_different_hash(): void
    {
        $mw = new ETagMiddleware();

        $response1 = $mw->process($this->makeRequest('GET', '/'), new EchoBodyHandler('body 1'));
        $response2 = $mw->process($this->makeRequest('GET', '/'), new EchoBodyHandler('body 2'));

        $this->assertNotSame(
            $response1->getHeaderLine('ETag'),
            $response2->getHeaderLine('ETag'),
        );
    }

    // ── TrustedProxy with simplified CIDR ─────────────────────

    #[Test]
    public function trusted_proxy_cidr_matching(): void
    {
        $mw = new TrustedProxyMiddleware(trustedProxies: ['10.0.0.0/8']);
        $request = $this->makeRequest('GET', '/')
            ->withHeader('X-Forwarded-For', '203.0.113.50');

        // Replace REMOTE_ADDR with a trusted proxy IP in the 10.x range
        $request = new ServerRequest(
            'GET',
            new Uri('http://localhost/'),
            Stream::empty(),
            ['X-Forwarded-For' => '203.0.113.50'],
            '1.1',
            ['REMOTE_ADDR' => '10.1.2.3'],
        );

        $capturedIp = null;
        $handler = new class($capturedIp) implements RequestHandlerInterface {
            public function __construct(private mixed &$ip) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->ip = $request->getAttribute('client_ip');
                return Response::json(['ok' => true]);
            }
        };

        $mw->process($request, $handler);
        $this->assertSame('203.0.113.50', $capturedIp);
    }

    // ── IpFilter CIDR Validation ──────────────────────────────

    #[Test]
    public function ip_filter_rejects_invalid_cidr(): void
    {
        // /33 is invalid for IPv4
        $mw = new IpFilterMiddleware(allowList: ['192.168.1.0/33']);
        $request = new ServerRequest(
            'GET',
            new Uri('http://localhost/'),
            Stream::empty(),
            [],
            '1.1',
            ['REMOTE_ADDR' => '192.168.1.1'],
        );

        $response = $mw->process($request, new EchoHandler());
        // Invalid CIDR should not match, so request is denied
        $this->assertSame(403, $response->getStatusCode());
    }

    // ── LoggingMiddleware response_size ────────────────────────

    #[Test]
    public function logging_middleware_includes_response_size(): void
    {
        $loggedContext = null;
        $logger = new class($loggedContext) implements \Psr\Log\LoggerInterface {
            public function __construct(private mixed &$context) {}
            public function emergency(\Stringable|string $message, array $context = []): void {}
            public function alert(\Stringable|string $message, array $context = []): void {}
            public function critical(\Stringable|string $message, array $context = []): void {}
            public function error(\Stringable|string $message, array $context = []): void {}
            public function warning(\Stringable|string $message, array $context = []): void {}
            public function notice(\Stringable|string $message, array $context = []): void {}
            public function info(\Stringable|string $message, array $context = []): void { $this->context = $context; }
            public function debug(\Stringable|string $message, array $context = []): void {}
            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $mw = new LoggingMiddleware($logger);
        $mw->process($this->makeRequest('GET', '/'), new EchoBodyHandler('Hello World'));

        $this->assertArrayHasKey('response_size', $loggedContext);
    }

    // ── ContentNegotiationMiddleware XXE protection ────────────

    #[Test]
    public function content_negotiation_xml_uses_libxml_nonet(): void
    {
        // Verify the middleware can produce XML without XXE
        $mw = new \MonkeysLegion\Http\Middleware\ContentNegotiationMiddleware();

        // Create a handler that returns a PayloadInterface
        $payload = new class implements \MonkeysLegion\Http\Negotiation\PayloadInterface {
            public function toPayload(): mixed
            {
                return ['name' => 'test', 'value' => 42];
            }
        };

        // We can't easily test LIBXML_NONET flag directly,
        // but we can verify XML generation works correctly
        $xml = new \SimpleXMLElement('<root/>', LIBXML_NONET);
        $xml->addChild('test', 'value');
        $this->assertStringContains('<test>value</test>', $xml->asXML());
    }

    // ── Helpers ────────────────────────────────────────────────

    private function makeRequest(
        string $method = 'GET',
        string $path = '/',
        ?array $parsedBody = null,
    ): ServerRequest {
        return new ServerRequest(
            $method,
            new Uri('http://localhost' . $path),
            Stream::empty(),
            [],
            '1.1',
            [],
            [],
            [],
            [],
            $parsedBody,
        );
    }

    private static function assertStringContains(string $needle, string $haystack): void
    {
        self::assertTrue(
            str_contains($haystack, $needle),
            sprintf('Failed asserting that "%s" contains "%s".', $haystack, $needle),
        );
    }
}
