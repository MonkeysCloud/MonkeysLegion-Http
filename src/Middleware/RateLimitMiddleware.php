<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Sliding-window rate limiter.
 *
 * • Authenticated requests → bucket keyed by request attribute 'uid'.
 * • Anonymous requests    → bucket keyed by client IP.
 *
 * Falls back to in-process memory if PSR-16 cache is absent or fails.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private const DEFAULT_LIMIT  = 200;   // req / window
    private const DEFAULT_WINDOW = 60;    // seconds

    /** @var array<string, array{reset:int,hits:int}> */
    private array $local = [];

    public function __construct(
        private readonly ResponseFactoryInterface $factory,
        private readonly ?CacheInterface          $cache      = null,
        private readonly int                      $limit      = self::DEFAULT_LIMIT,
        private readonly int                      $window     = self::DEFAULT_WINDOW,
        private readonly string                   $keyPrefix  = 'ratelimit:'
    ) {}

    /* ------------------------------------------------------------------ */

    public function process(
        ServerRequestInterface  $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {

        [$key, $storage]   = $this->resolveKey($request);
        $now               = time();

        /* -------- Try shared cache (fast-path) -------- */
        if ($this->cache !== null) {
            try {
                [$hits, $reset] = $this->cacheFlow($key, $now);

                if ($hits > $this->limit) {
                    return $this->reject($reset, $storage);
                }

                return $this->addHeaders($handler->handle($request), $hits, $reset, $storage);

            } catch (\Throwable) {
                // fall back to local memory
            }
        }

        /* -------- In-process fallback -------- */
        [$hits, $reset] = $this->localFlow($key, $now);

        if ($hits > $this->limit) {
            return $this->reject($reset, 'local');
        }

        return $this->addHeaders($handler->handle($request), $hits, $reset, 'local');
    }

    /* ------------------------------------------------------------------ */
    /*  Key resolution                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{string,string}  [cacheKey, 'uid'|'ip']
     */
    private function resolveKey(ServerRequestInterface $request): array
    {
        if ($uid = $request->getAttribute('uid')) {
            return [$this->keyPrefix . 'uid:' . $uid, 'uid'];
        }

        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        return [$this->keyPrefix . 'ip:' . $ip, 'ip'];
    }

    /* ------------------------------------------------------------------ */
    /*  Shared-cache path                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{int,int}  [hits,resetTs]
     * @throws InvalidArgumentException
     */
    private function cacheFlow(string $key, int $now): array
    {
        $ttlKey = $key . ':ttl';

        // If cache supports atomic increment, use it
        if (method_exists($this->cache, 'increment')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $hits = $this->cache->increment($key);
            if ($hits === 1) {                          // new bucket
                $this->cache->set($key, 1, $this->window);
                $this->cache->set($ttlKey, $now + $this->window, $this->window);
            }
        } else {
            $hits = ((int) $this->cache->get($key, 0)) + 1;
            $this->cache->set($key, $hits, $this->window);
        }

        $reset = (int) $this->cache->get($ttlKey, $now + $this->window);
        return [$hits, $reset];
    }

    /* ------------------------------------------------------------------ */
    /*  In-memory fallback                                                */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{int,int}  [hits,resetTs]
     */
    private function localFlow(string $key, int $now): array
    {
        [$reset, $hits] = $this->local[$key] ?? [$now + $this->window, 0];

        if ($now >= $reset) {                   // new window
            $reset = $now + $this->window;
            $hits  = 0;
        }

        $hits++;
        $this->local[$key] = [$reset, $hits];

        return [$hits, $reset];
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                           */
    /* ------------------------------------------------------------------ */

    private function reject(int $reset, string $storage): ResponseInterface
    {
        $resp = $this->factory->createResponse(429, 'Too Many Requests');
        $resp->getBody()->write('Rate limit exceeded');

        return $this->addHeaders($resp, $this->limit + 1, $reset, $storage)
            ->withHeader('Retry-After', (string) max(0, $reset - time()));
    }

    private function addHeaders(
        ResponseInterface $resp,
        int               $hits,
        int               $reset,
        string            $storage
    ): ResponseInterface {
        return $resp
            ->withHeader('X-RateLimit-Limit',     (string) $this->limit)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $this->limit - $hits))
            ->withHeader('X-RateLimit-Reset',     (string) $reset)
            ->withHeader('X-RateLimit-Storage',   $storage);
    }
}