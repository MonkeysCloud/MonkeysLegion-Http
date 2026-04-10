<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * PSR-7 Stream implementation wrapping a native PHP resource.
 *
 * All read/write/seek operations guard against detached or closed
 * streams, throwing RuntimeException when the resource is gone.
 *
 * PHP 8.4 features used:
 *  • readonly promoted constructor parameter
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    /**
     * @param resource $resource A valid PHP stream resource.
     *
     * @throws InvalidArgumentException If $resource is not a resource.
     */
    public function __construct($resource)
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Stream requires a valid PHP resource.');
        }
        $this->resource = $resource;
    }

    // ── Factories ──────────────────────────────────────────────

    /**
     * Create an in-memory stream from a string.
     */
    public static function createFromString(string $content = ''): self
    {
        $handle = fopen('php://temp', 'r+');
        if ($content !== '') {
            fwrite($handle, $content);
            rewind($handle);
        }
        return new self($handle);
    }

    /**
     * Create a read-only stream from a file path.
     *
     * @throws RuntimeException If the file cannot be opened.
     */
    public static function createFromFile(string $path, string $mode = 'rb'): self
    {
        $handle = @fopen($path, $mode);
        if ($handle === false) {
            throw new RuntimeException(sprintf('Cannot open file "%s" with mode "%s".', $path, $mode));
        }
        return new self($handle);
    }

    /**
     * Create an empty writable stream (php://temp).
     */
    public static function empty(): self
    {
        return new self(fopen('php://temp', 'r+'));
    }

    // ── StreamInterface ────────────────────────────────────────

    /** {@inheritDoc} */
    public function __toString(): string
    {
        try {
            $this->rewind();
            return $this->getContents();
        } catch (\Throwable) {
            return '';
        }
    }

    /** {@inheritDoc} */
    public function close(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
        $this->resource = null;
    }

    /** {@inheritDoc} */
    public function detach()
    {
        $res = $this->resource;
        $this->resource = null;
        return $res;
    }

    /** {@inheritDoc} */
    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }
        $stats = fstat($this->resource);
        return $stats['size'] ?? null;
    }

    /** {@inheritDoc} */
    public function tell(): int
    {
        $this->guardDetached();
        $pos = ftell($this->resource);
        if ($pos === false) {
            throw new RuntimeException('Unable to determine stream position.');
        }
        return $pos;
    }

    /** {@inheritDoc} */
    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    /** {@inheritDoc} */
    public function isSeekable(): bool
    {
        if ($this->resource === null) {
            return false;
        }
        $meta = stream_get_meta_data($this->resource);
        return $meta['seekable'] ?? false;
    }

    /** {@inheritDoc} */
    public function seek($offset, $whence = SEEK_SET): void
    {
        $this->guardDetached();
        if (!$this->isSeekable() || fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Unable to seek in stream.');
        }
    }

    /** {@inheritDoc} */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /** {@inheritDoc} */
    public function isWritable(): bool
    {
        if ($this->resource === null) {
            return false;
        }
        $mode = stream_get_meta_data($this->resource)['mode'] ?? '';
        return str_contains($mode, 'w') || str_contains($mode, '+') || str_contains($mode, 'a') || str_contains($mode, 'x') || str_contains($mode, 'c');
    }

    /** {@inheritDoc} */
    public function write($string): int
    {
        $this->guardDetached();
        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable.');
        }
        $bytes = fwrite($this->resource, $string);
        if ($bytes === false) {
            throw new RuntimeException('Unable to write to stream.');
        }
        return $bytes;
    }

    /** {@inheritDoc} */
    public function isReadable(): bool
    {
        if ($this->resource === null) {
            return false;
        }
        $mode = stream_get_meta_data($this->resource)['mode'] ?? '';
        return str_contains($mode, 'r') || str_contains($mode, '+');
    }

    /** {@inheritDoc} */
    public function read($length): string
    {
        $this->guardDetached();
        if ($length < 0) {
            throw new InvalidArgumentException('Read length cannot be negative.');
        }
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }
        $data = fread($this->resource, $length);
        if ($data === false) {
            throw new RuntimeException('Unable to read from stream.');
        }
        return $data;
    }

    /** {@inheritDoc} */
    public function getContents(): string
    {
        $this->guardDetached();
        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents.');
        }
        return $contents;
    }

    /** {@inheritDoc} */
    public function getMetadata($key = null): mixed
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }
        $meta = stream_get_meta_data($this->resource);
        return $key === null ? $meta : ($meta[$key] ?? null);
    }

    // ── Internal ───────────────────────────────────────────────

    /**
     * @throws RuntimeException If the stream resource has been detached.
     */
    private function guardDetached(): void
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream has been detached.');
        }
    }
}