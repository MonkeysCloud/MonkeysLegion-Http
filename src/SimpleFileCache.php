<?php

declare(strict_types=1);

namespace MonkeysLegion\Http;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Very simple PSR-16 file-based cache.
 * Stores each key as a separate file in a cache directory.
 */
final class SimpleFileCache implements CacheInterface
{
    private string $dir;

    public function __construct(string $cacheDir)
    {
        $this->dir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
        if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true)) {
            throw new \RuntimeException("Could not create cache dir: {$this->dir}");
        }
    }

    private function filePath(string $key): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $key);
        return $this->dir . DIRECTORY_SEPARATOR . $safe . '.cache';
    }

    public function get($key, $default = null)
    {
        $path = $this->filePath($key);
        if (!is_file($path)) {
            return $default;
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            return $default;
        }

        $entry = @unserialize($data);
        if (!is_array($entry) || count($entry) !== 2) {
            return $default;
        }

        [$expiresAt, $value] = $entry;
        if ($expiresAt !== 0 && time() >= $expiresAt) {
            @unlink($path);
            return $default;
        }

        return $value;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $expiresAt = 0;
        if ($ttl instanceof \DateInterval) {
            $expiresAt = (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        } elseif (is_int($ttl)) {
            $expiresAt = time() + $ttl;
        }

        $entry = [$expiresAt, $value];
        $data  = serialize($entry);
        return file_put_contents($this->filePath($key), $data) !== false;
    }

    public function delete($key): bool
    {
        $path = $this->filePath($key);
        if (is_file($path)) {
            return unlink($path);
        }
        return true;
    }

    public function clear(): bool
    {
        $ok      = true;
        $pattern = $this->dir . DIRECTORY_SEPARATOR . '*.cache';
        foreach (glob($pattern) ?: [] as $file) {
            if (!unlink($file)) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $ok = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function deleteMultiple($keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function has($key): bool
    {
        $path = $this->filePath($key);
        if (!is_file($path)) {
            return false;
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            return false;
        }

        $entry = @unserialize($data);
        if (!is_array($entry) || count($entry) !== 2) {
            return false;
        }

        [$expiresAt] = $entry;
        if ($expiresAt !== 0 && time() >= $expiresAt) {
            @unlink($path);
            return false;
        }

        return true;
    }
}