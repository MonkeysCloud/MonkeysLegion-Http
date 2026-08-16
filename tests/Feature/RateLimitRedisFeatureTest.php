<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Feature;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Http\Middleware\RateLimitMiddleware;
use MonkeysLegion\Http\Tests\Feature\Support\RedisCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Redis-backed rate limiting integration test.
 *
 * Requires Docker (see docker-compose.integration.yml) and
 * RUN_INTEGRATION_TESTS=1.
 */
final class RateLimitRedisFeatureTest extends TestCase
{
    private Client $redis;

    protected function setUp(): void
    {
        $host = \getenv('INTEGRATION_REDIS_HOST');
        $port = \getenv('INTEGRATION_REDIS_PORT');

        $this->redis = new Client([
            'host' => \is_string($host) && $host !== '' ? $host : '127.0.0.1',
            'port' => \is_string($port) && $port !== '' ? (int) $port : 6380,
            'timeout' => 2,
        ]);

        try {
            $this->redis->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Redis is not reachable. Start it with: docker compose -f docker-compose.integration.yml up -d redis',
            );
        }

        $this->redis->flushdb();
    }

    protected function tearDown(): void
    {
        if (isset($this->redis)) {
            $this->redis->flushdb();
        }
    }

    #[Test]
    public function shared_redis_state_counts_across_middleware_instances(): void
    {
        $cache = new RedisCache($this->redis);
        $one   = new RateLimitMiddleware(cache: $cache, limit: 2, window: 60);
        $two   = new RateLimitMiddleware(cache: $cache, limit: 2, window: 60);
        $request = $this->request();

        $one->process($request, new OkHandler());
        $response = $two->process($request, new OkHandler());

        $this->assertSame('0', $response->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertSame('ip', $response->getHeaderLine('X-RateLimit-Storage'));
    }

    #[Test]
    public function redis_rejects_after_limit_exhausted(): void
    {
        $cache = new RedisCache($this->redis);
        $mw    = new RateLimitMiddleware(cache: $cache, limit: 1, window: 60);

        $first = $mw->process($this->request(), new OkHandler());
        $second = $mw->process($this->request(), new OkHandler());

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(429, $second->getStatusCode());
        $this->assertTrue($second->hasHeader('Retry-After'));
    }

    #[Test]
    public function redis_window_resets_allow_requests_again(): void
    {
        $cache = new RedisCache($this->redis);
        $mw    = new RateLimitMiddleware(cache: $cache, limit: 1, window: 1);

        $mw->process($this->request(), new OkHandler());
        $blocked = $mw->process($this->request(), new OkHandler());
        $this->assertSame(429, $blocked->getStatusCode());

        \sleep(2); // wait for the 1s window to expire

        $again = $mw->process($this->request(), new OkHandler());
        $this->assertSame(200, $again->getStatusCode());
    }

    #[Test]
    public function per_route_override_works_against_shared_redis(): void
    {
        $cache = new RedisCache($this->redis);
        $mw    = new RateLimitMiddleware(cache: $cache, limit: 100, window: 60);

        $request = $this->request()->withAttribute('rate_limit', ['limit' => 1, 'window' => 60]);

        $mw->process($request, new OkHandler());
        $response = $mw->process($request, new OkHandler());

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('1', $response->getHeaderLine('X-RateLimit-Limit'));
    }

    private function request(): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('http://localhost/'),
            Stream::empty(),
            [],
            '1.1',
            ['REMOTE_ADDR' => '10.0.0.1'],
        );
    }
}

final class OkHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return Response::json(['ok' => true]);
    }
}
