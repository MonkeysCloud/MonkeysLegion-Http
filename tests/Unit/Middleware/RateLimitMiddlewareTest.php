<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Middleware;

use DateInterval;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Http\Middleware\RateLimitMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Minimal in-memory PSR-16 cache (without an atomic increment()).
 */
class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expiresAt: int}> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->store[$key] ?? null;
        if ($entry === null || ($entry['expiresAt'] !== 0 && $entry['expiresAt'] < \time())) {
            unset($this->store[$key]);
            return $default;
        }
        return $entry['value'];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $expiresAt = 0;
        if ($ttl instanceof DateInterval) {
            $expiresAt = new \DateTimeImmutable()->add($ttl)->getTimestamp();
        } elseif (\is_int($ttl) && $ttl > 0) {
            $expiresAt = \time() + $ttl;
        }
        $this->store[$key] = ['value' => $value, 'expiresAt' => $expiresAt];
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        return true;
    }

    /**
     * @param iterable<string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, null) !== null;
    }
}

/**
 * PSR-16 cache with a non-standard atomic increment() — exercises the
 * middleware's increment fast-path.
 */
final class IncrementArrayCache extends ArrayCache
{
    public function increment(string $key): int
    {
        $stored  = $this->get($key, 0);
        $current = (int) (\is_scalar($stored) ? $stored : 0);
        $next    = $current + 1;
        $this->set($key, $next);
        return $next;
    }
}

/**
 * PSR-16 cache that always fails — exercises the in-process fallback.
 */
final class FailingCache implements CacheInterface
{
    private function fail(): never
    {
        throw new \RuntimeException('cache unavailable');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->fail();
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->fail();
    }

    public function delete(string $key): bool
    {
        $this->fail();
    }

    public function clear(): bool
    {
        $this->fail();
    }

    /**
     * @param iterable<string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $this->fail();
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $this->fail();
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $this->fail();
    }

    public function has(string $key): bool
    {
        $this->fail();
    }
}

final class OkHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return Response::json(['ok' => true]);
    }
}

final class RateLimitMiddlewareTest extends TestCase
{
    #[Test]
    public function shared_cache_tracks_requests_across_instances(): void
    {
        $cache = new ArrayCache();
        $one   = new RateLimitMiddleware(cache: $cache, limit: 2, window: 60);
        $two   = new RateLimitMiddleware(cache: $cache, limit: 2, window: 60);
        $request = $this->request();

        $first = $one->process($request, new OkHandler());
        $this->assertSame(200, $first->getStatusCode());

        $response = $two->process($request, new OkHandler());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('0', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    #[Test]
    public function shared_cache_rejects_once_limit_is_exhausted(): void
    {
        $cache = new ArrayCache();
        $mw    = new RateLimitMiddleware(cache: $cache, limit: 1, window: 60);

        $allowed = $mw->process($this->request(), new OkHandler());
        $this->assertSame(200, $allowed->getStatusCode());

        $response = $mw->process($this->request(), new OkHandler());

        $this->assertSame(429, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Retry-After'));
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertSame('error', $body['status'] ?? null);
    }

    #[Test]
    public function increment_capable_cache_uses_atomic_path(): void
    {
        $cache = new IncrementArrayCache();
        $one   = new RateLimitMiddleware(cache: $cache, limit: 2, window: 60);
        $two   = new RateLimitMiddleware(cache: $cache, limit: 2, window: 60);

        $this->assertSame(200, $one->process($this->request(), new OkHandler())->getStatusCode());
        $this->assertSame(200, $two->process($this->request(), new OkHandler())->getStatusCode());
        $response = $two->process($this->request(), new OkHandler());

        $this->assertSame(429, $response->getStatusCode());
    }

    #[Test]
    public function shared_cache_persists_reset_timestamp(): void
    {
        $cache = new ArrayCache();
        $mw    = new RateLimitMiddleware(cache: $cache, limit: 5, window: 60);

        $first  = $mw->process($this->request(), new OkHandler());
        $second = $mw->process($this->request(), new OkHandler());

        $resetFirst  = (int) $first->getHeaderLine('X-RateLimit-Reset');
        $resetSecond = (int) $second->getHeaderLine('X-RateLimit-Reset');
        // The window must not slide forward on every request.
        $this->assertSame($resetFirst, $resetSecond);
        $this->assertGreaterThan(\time(), $resetSecond);
    }

    #[Test]
    public function failing_cache_falls_back_to_local_storage(): void
    {
        $mw = new RateLimitMiddleware(cache: new FailingCache(), limit: 1, window: 60);

        $response = $mw->process($this->request(), new OkHandler());

        $this->assertSame('local', $response->getHeaderLine('X-RateLimit-Storage'));
    }

    #[Test]
    public function per_route_override_changes_the_limit(): void
    {
        $mw      = new RateLimitMiddleware(limit: 100, window: 60);
        $request = $this->request()->withAttribute('rate_limit', ['limit' => 1, 'window' => 60]);

        $allowed = $mw->process($request, new OkHandler());
        $this->assertSame(200, $allowed->getStatusCode());
        $this->assertSame('1', $allowed->getHeaderLine('X-RateLimit-Limit'));

        $response = $mw->process($request, new OkHandler());

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('1', $response->getHeaderLine('X-RateLimit-Limit'));
    }

    #[Test]
    public function per_route_override_accepts_numeric_strings(): void
    {
        $mw      = new RateLimitMiddleware(limit: 100, window: 60);
        $request = $this->request()->withAttribute('rate_limit', ['limit' => '1', 'window' => '60']);

        $allowed = $mw->process($request, new OkHandler());
        $this->assertSame(200, $allowed->getStatusCode());
        $this->assertSame('1', $allowed->getHeaderLine('X-RateLimit-Limit'));

        $response = $mw->process($request, new OkHandler());
        $this->assertSame(429, $response->getStatusCode());
    }

    #[Test]
    public function per_route_override_ignores_non_numeric_values(): void
    {
        $mw      = new RateLimitMiddleware(limit: 100, window: 60);
        $request = $this->request()->withAttribute('rate_limit', ['limit' => 'lots', 'window' => 'soon']);

        $response = $mw->process($request, new OkHandler());

        $this->assertSame('100', $response->getHeaderLine('X-RateLimit-Limit'));
    }

    #[Test]
    public function adds_rate_limit_headers_on_success(): void
    {
        $mw = new RateLimitMiddleware(limit: 5, window: 60);

        $response = $mw->process($this->request(), new OkHandler());

        $this->assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('4', $response->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Reset'));
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
