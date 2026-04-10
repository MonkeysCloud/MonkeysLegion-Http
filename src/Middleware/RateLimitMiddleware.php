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
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Per-route override: $request->withAttribute('rate_limit', ['limit' => 10, 'window' => 60])
        $routeLimit  = $this->limit;
        $routeWindow = $this->window;
        $override    = $request->getAttribute('rate_limit');
        if (is_array($override)) {
            $routeLimit  = (int) ($override['limit']  ?? $routeLimit);
            $routeWindow = (int) ($override['window'] ?? $routeWindow);
        }

        [$key, $storage] = $this->resolveKey($request);
        $now = time();

        // Shared cache path
        if ($this->cache !== null) {
            try {
                [$hits, $reset] = $this->cacheFlow($key, $now, $routeWindow);

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
            return [$this->keyPrefix . 'uid:' . $uid, 'uid'];
        }

        $ip = $request->getAttribute('client_ip')
            ?? $request->getServerParams()['REMOTE_ADDR']
            ?? '0.0.0.0';

        return [$this->keyPrefix . 'ip:' . $ip, 'ip'];
    }

    // ── Cache Path ─────────────────────────────────────────────

    /**
     * @return array{int, int} [hits, resetTs]
     */
    private function cacheFlow(string $key, int $now, int $window): array
    {
        $ttlKey = $key . ':ttl';

        if (method_exists($this->cache, 'increment')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $hits = $this->cache->increment($key);
            if ($hits === 1) {
                $this->cache->set($key, 1, $window);
                $this->cache->set($ttlKey, $now + $window, $window);
            }
        } else {
            $hits = ((int) $this->cache->get($key, 0)) + 1;
            $this->cache->set($key, $hits, $window);
        }

        $reset = (int) $this->cache->get($ttlKey, $now + $window);
        return [$hits, $reset];
    }

    // ── Local Fallback ─────────────────────────────────────────

    /**
     * @return array{int, int} [hits, resetTs]
     */
    private function localFlow(string $key, int $now, int $window): array
    {
        [$reset, $hits] = $this->local[$key] ?? [$now + $window, 0];

        if ($now >= $reset) {
            $reset = $now + $window;
            $hits  = 0;
        }

        $hits++;
        $this->local[$key] = [$reset, $hits];

        return [$hits, $reset];
    }

    // ── Helpers ────────────────────────────────────────────────

    private function reject(int $reset, int $limit, string $storage): ResponseInterface
    {
        $json = json_encode([
            'status'  => 'error',
            'message' => 'Too many requests. Please try again later.',
        ], JSON_UNESCAPED_SLASHES);

        $response = new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::createFromString($json),
            429,
            ['Content-Type' => 'application/json'],
        );

        return $this->addHeaders($response, $limit + 1, $reset, $limit, $storage)
            ->withHeader('Retry-After', (string) max(0, $reset - time()));
    }

    private function addHeaders(
        ResponseInterface $response,
        int $hits,
        int $reset,
        int $limit,
        string $storage,
    ): ResponseInterface {
        return $response
            ->withHeader('X-RateLimit-Limit',     (string) $limit)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $limit - $hits))
            ->withHeader('X-RateLimit-Reset',     (string) $reset)
            ->withHeader('X-RateLimit-Storage',   $storage);
    }
}