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
 * Sliding-window rate-limiter middleware.
 *
 * Fallback behaviour:
 * • If a PSR-16 CacheInterface is provided, counters are stored there.
 * • Otherwise (or on cache failure), falls back to per-process in-memory buckets.
 *
 * Backends with atomic increment (Redis, APCu, etc.) eliminate races. Others
 * get “good enough” protection, with slight drift under concurrency.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private const DEFAULT_LIMIT  = 60;   // requests…
    private const DEFAULT_WINDOW = 60;   // …per 60 seconds

    /** @var array<string, array{int resetTs,int hits}> In-memory fallback buckets */
    private array $local = [];

    /**
     * @param ResponseFactoryInterface $factory    PSR-17 factory to create ResponseInterface
     * @param CacheInterface|null      $cache      PSR-16 cache (e.g. Redis). If null, uses in-process storage.
     * @param int                      $limit      Maximum requests allowed per window.
     * @param int                      $window     Window size in seconds.
     * @param string                   $keyPrefix  Prefix for cache keys.
     */
    public function __construct(
        private readonly ResponseFactoryInterface $factory,
        private readonly ?CacheInterface          $cache      = null,
        private readonly int                      $limit      = self::DEFAULT_LIMIT,
        private readonly int                      $window     = self::DEFAULT_WINDOW,
        private readonly string                   $keyPrefix  = 'ratelimit:'
    ) {}

    /**
     * PSR-15 entry point. Applies rate-limiting based on client IP.
     *
     * @param ServerRequestInterface  $request  The incoming HTTP request.
     * @param RequestHandlerInterface $handler  The next handler in the chain.
     * @return ResponseInterface                 The response, possibly with rate-limit headers.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $ip  = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $now = time();

        // 1) Try shared PSR-16 cache
        if ($this->cache !== null) {
            $key = $this->keyPrefix . $ip;
            try {
                $hits = $this->increment($key);

                // Determine reset timestamp from TTL
                $ttl     = $this->cache->get($key . '_ttl', null);
                $resetTs = $ttl !== null ? $now + $ttl : $now + $this->window;

                if ($hits > $this->limit) {
                    return $this->reject($resetTs, 'shared');
                }

                $response = $handler->handle($request);
                return $this->addHeaders($response, $hits, $resetTs, 'shared');

            } catch (\Throwable) {
                // On any cache error, fall through to in-memory
            }
        }

        // 2) In-process sliding window fallback
        [$resetTs, $hits] = $this->local[$ip] ?? [$now + $this->window, 0];
        if ($now >= $resetTs) {
            // Window expired → reset
            $resetTs = $now + $this->window;
            $hits    = 0;
        }
        $hits++;
        $this->local[$ip] = [$resetTs, $hits];

        if ($hits > $this->limit) {
            return $this->reject($resetTs, 'local');
        }

        $response = $handler->handle($request);
        return $this->addHeaders($response, $hits, $resetTs, 'local');
    }

    /**
     * Atomically increment the counter in cache if supported, otherwise fallback.
     *
     * @param string $key Cache key for this IP.
     * @return int       New hit count after increment.
     * @throws InvalidArgumentException
     */
    private function increment(string $key): int
    {
        if (method_exists($this->cache, 'increment')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $hits = $this->cache->increment($key);
            if ($hits === 1) {
                // First time: set initial TTL
                $this->cache->set($key, 1, $this->window);
                $this->cache->set($key . '_ttl', $this->window, $this->window);
            }
            return $hits;
        }

        // Generic PSR-16 get/set fallback
        $current = (int)$this->cache->get($key, 0) + 1;
        $this->cache->set($key, $current, $this->window);
        $this->cache->set($key . '_ttl', $this->window, $this->window);
        return $current;
    }

    /**
     * Build a 429 Too Many Requests response with appropriate headers.
     *
     * @param int    $resetTs  UNIX timestamp when window resets.
     * @param string $storage  'shared' or 'local' to indicate storage used.
     * @return ResponseInterface
     */
    private function reject(int $resetTs, string $storage): ResponseInterface
    {
        $resp = $this->factory->createResponse(429, 'Too Many Requests');
        $resp->getBody()->write('Rate limit exceeded');
        $resp = $this->addHeaders($resp, $this->limit + 1, $resetTs, $storage)
            ->withHeader('Retry-After', (string)max(0, $resetTs - time()));
        return $resp;
    }

    /**
     * Inject standard rate-limit headers into a response.
     *
     * @param ResponseInterface $resp
     * @param int               $hits      Number of hits so far.
     * @param int               $resetTs   UNIX timestamp of next reset.
     * @param string            $storage   Which storage was used.
     * @return ResponseInterface
     */
    private function addHeaders(
        ResponseInterface $resp,
        int               $hits,
        int               $resetTs,
        string            $storage
    ): ResponseInterface {
        return $resp
            ->withHeader('X-RateLimit-Limit',     (string)$this->limit)
            ->withHeader('X-RateLimit-Remaining', (string)max(0, $this->limit - $hits))
            ->withHeader('X-RateLimit-Reset',     (string)$resetTs)
            ->withHeader('X-RateLimit-Storage',   $storage);
    }
}