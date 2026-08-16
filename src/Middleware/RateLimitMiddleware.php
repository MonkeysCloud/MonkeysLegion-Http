<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Sliding-window rate limiter with PSR-16 cache support.
 *
 * • Authenticated requests → bucket keyed by request attribute 'uid'.
 * • Anonymous requests    → bucket keyed by client IP.
 * • Falls back to in-process memory if PSR-16 cache is absent or fails.
 * • Per-route rate limiting via 'rate_limit' request attribute.
 *
 * v2 improvements:
 *  • JSON error body on 429
 *  • Respects TrustedProxy's `client_ip` attribute
 *  • Per-route override via request attribute
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private const int DEFAULT_LIMIT  = 200;
    private const int DEFAULT_WINDOW = 60;

    /** @var array<string, array{reset: int, hits: int}> */
    private array $local = [];

    /**
     * @param CacheInterface|null $cache     PSR-16 cache for shared state.
     * @param int                 $limit     Requests per window.
     * @param int                 $window    Window duration in seconds.
     * @param string              $keyPrefix Cache key prefix.
     */
    public function __construct(
        private readonly ?CacheInterface $cache     = null,
        private readonly int             $limit     = self::DEFAULT_LIMIT,
        private readonly int             $window    = self::DEFAULT_WINDOW,
        private readonly string          $keyPrefix = 'ratelimit:',
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Per-route override: $request->withAttribute('rate_limit', ['limit' => 10, 'window' => 60])
        $routeLimit  = $this->limit;
        $routeWindow = $this->window;
        $override    = $request->getAttribute('rate_limit');
        if (\is_array($override)) {
            $routeLimit  = isset($override['limit']) && \is_numeric($override['limit'])
                ? (int) $override['limit']
                : $routeLimit;
            $routeWindow = isset($override['window']) && \is_numeric($override['window'])
                ? (int) $override['window']
                : $routeWindow;
        }

        [$key, $storage] = $this->resolveKey($request);
        $now = \time();

        // Shared cache path
        if ($this->cache !== null) {
            try {
                [$hits, $reset] = $this->cacheFlow($this->cache, $key, $now, $routeWindow);

                if ($hits > $routeLimit) {
                    return $this->reject($reset, $routeLimit, $storage);
                }

                return $this->addHeaders($handler->handle($request), $hits, $reset, $routeLimit, $storage);
            } catch (\Throwable) {
                // fall through to local
            }
        }

        // In-process fallback
        [$hits, $reset] = $this->localFlow($key, $now, $routeWindow);

        if ($hits > $routeLimit) {
            return $this->reject($reset, $routeLimit, 'local');
        }

        return $this->addHeaders($handler->handle($request), $hits, $reset, $routeLimit, 'local');
    }

    // ── Key Resolution ─────────────────────────────────────────

    /**
     * @return array{string, string} [cacheKey, 'uid'|'ip']
     */
    private function resolveKey(ServerRequestInterface $request): array
    {
        if ($uid = $request->getAttribute('uid')) {
            return [$this->keyPrefix . 'uid:' . (\is_scalar($uid) ? (string) $uid : ''), 'uid'];
        }

        $ip = $request->getAttribute('client_ip')
            ?? $request->getServerParams()['REMOTE_ADDR']
            ?? '0.0.0.0';

        return [$this->keyPrefix . 'ip:' . (\is_scalar($ip) ? (string) $ip : ''), 'ip'];
    }

    // ── Cache Path ─────────────────────────────────────────────

    /**
     * @return array{int, int} [hits, resetTs]
     */
    private function cacheFlow(CacheInterface $cache, string $key, int $now, int $window): array
    {
        $ttlKey = $key . ':ttl';

        if (\method_exists($cache, 'increment')) {
            // Non-standard atomic increment support for PSR-16 caches that provide it.
            $hits = $cache->increment($key);
            if ($hits === 1) {
                $cache->set($key, 1, $window);
                $cache->set($ttlKey, $now + $window, $window);
            }
        } else {
            $stored  = $cache->get($key, 0);
            $current = (int) (\is_scalar($stored) ? $stored : 0);
            $hits    = $current + 1;
            $cache->set($key, $hits, $window);

            // Persist the bucket's reset timestamp on creation so the window
            // does not slide forward on every request (which would make
            // Retry-After always report the full window).
            if ($current === 0) {
                $cache->set($ttlKey, $now + $window, $window);
            }
        }

        $storedReset = $cache->get($ttlKey, $now + $window);
        $reset       = (int) (\is_scalar($storedReset) ? $storedReset : $now + $window);
        return [$hits, $reset];
    }

    // ── Local Fallback ─────────────────────────────────────────

    /**
     * @return array{int, int} [hits, resetTs]
     */
    private function localFlow(string $key, int $now, int $window): array
    {
        $entry = $this->local[$key] ?? null;

        if ($entry === null) {
            $reset = $now + $window;
            $hits  = 0;
        } else {
            $reset = $entry['reset'];
            $hits  = $entry['hits'];
        }

        if ($now >= $reset) {
            $reset = $now + $window;
            $hits  = 0;
        }

        $hits++;
        $this->local[$key] = ['reset' => $reset, 'hits' => $hits];

        return [$hits, $reset];
    }

    // ── Helpers ────────────────────────────────────────────────

    private function reject(int $reset, int $limit, string $storage): ResponseInterface
    {
        $json = \json_encode([
            'status'  => 'error',
            'message' => 'Too many requests. Please try again later.',
        ], \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);

        $response = new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::createFromString($json),
            429,
            ['Content-Type' => 'application/json'],
        );

        return $this->addHeaders($response, $limit + 1, $reset, $limit, $storage)
            ->withHeader('Retry-After', (string) \max(0, $reset - \time()));
    }

    private function addHeaders(
        ResponseInterface $response,
        int $hits,
        int $reset,
        int $limit,
        string $storage,
    ): ResponseInterface {
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $limit)
            ->withHeader('X-RateLimit-Remaining', (string) \max(0, $limit - $hits))
            ->withHeader('X-RateLimit-Reset', (string) $reset)
            ->withHeader('X-RateLimit-Storage', $storage);
    }
}
