<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Feature\Support;

use DateInterval;
use Predis\Client;
use Psr\SimpleCache\CacheInterface;

/**
 * Minimal PSR-16 cache adapter over Predis for integration tests.
 *
 * Redis stores everything as strings, which matches how the rate limiter
 * treats scalar values — the middleware casts back to int on read.
 */
final class RedisCache implements CacheInterface
{
    public function __construct(private readonly Client $redis)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($key);
        return $value === null ? $default : $value;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $seconds = $ttl instanceof DateInterval
            ? (int) new \DateTimeImmutable()->add($ttl)->getTimestamp() - \time()
            : (\is_int($ttl) ? $ttl : 0);
        $string = \is_scalar($value) ? (string) $value : \json_encode($value, \JSON_THROW_ON_ERROR);

        if ($seconds > 0) {
            $this->redis->setex($key, $seconds, $string);
        } else {
            $this->redis->set($key, $string);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        return (int) $this->redis->del([$key]) > 0;
    }

    public function clear(): bool
    {
        $this->redis->flushdb();
        return true;
    }

    /**
     * @param iterable<string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
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
        $this->redis->del(\is_array($keys) ? $keys : \iterator_to_array($keys));
        return true;
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($key);
    }

    /**
     * Non-standard atomic increment — exercises the middleware's fast path.
     */
    public function increment(string $key): int
    {
        return (int) $this->redis->incrby($key, 1);
    }
}
