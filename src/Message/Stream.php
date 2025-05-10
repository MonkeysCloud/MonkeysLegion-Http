<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * A simple PSR‑7 Stream implementation wrapping a PHP resource.
 *
 * Provides methods to read, write, and manage stream metadata.
 */
class Stream implements StreamInterface
{
    /**
     * @param resource $resource  A valid PHP stream resource (e.g. fopen handle)
     * @throws InvalidArgumentException if $resource is not a resource
     */
    public function __construct(private $resource)
    {
        if (!is_resource($this->resource)) {
            throw new InvalidArgumentException('Stream resource must be a valid resource');
        }
    }

    /**
     * Create an in-memory temporary stream initialized with optional content.
     *
     * @param string $content  Initial data to write into the stream
     * @return self
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
     * Return the entire stream contents as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        try {
            $this->rewind();
            return $this->getContents();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Close the stream and detach underlying resource.
     */
    public function close(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
        $this->detach();
    }

    /**
     * Detach the underlying resource and return it.
     *
     * @return resource|null
     */
    public function detach()
    {
        $res = $this->resource;
        $this->resource = null;
        return $res;
    }

    /**
     * Get the size in bytes of the stream, if known.
     *
     * @return int|null
     */
    public function getSize(): ?int
    {
        $stats = fstat($this->resource);
        return $stats['size'] ?? null;
    }

    /**
     * Get the current position of the file read/write pointer.
     *
     * @return int
     * @throws RuntimeException on failure
     */
    public function tell(): int
    {
        $pos = ftell($this->resource);
        if ($pos === false) {
            throw new RuntimeException('Unable to determine stream position');
        }
        return $pos;
    }

    /**
     * Returns true if the stream is at the end.
     *
     * @return bool
     */
    public function eof(): bool
    {
        return feof($this->resource);
    }

    /**
     * Returns whether the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable(): bool
    {
        $meta = $this->getMetadata();
        return $meta['seekable'] ?? false;
    }

    /**
     * Seek to a position in the stream.
     *
     * @param int $offset  Stream offset to seek to
     * @param int $whence  SEEK_SET, SEEK_CUR, or SEEK_END
     * @throws RuntimeException on failure
     */
    public function seek($offset, $whence = SEEK_SET): void
    {
        if (!$this->isSeekable() || fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Unable to seek in stream');
        }
    }

    /**
     * Rewind to the start of the stream.
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * Returns whether the stream is writable.
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        $mode = $this->getMetadata('mode');
        return str_contains((string)$mode, 'w') || str_contains((string)$mode, '+');
    }

    /**
     * Write data to the stream.
     *
     * @param string $string  Data to write
     * @return int            Number of bytes written
     * @throws RuntimeException on failure
     */
    public function write($string): int
    {
        $bytes = fwrite($this->resource, $string);
        if ($bytes === false) {
            throw new RuntimeException('Unable to write to stream');
        }
        return $bytes;
    }

    /**
     * Returns whether the stream is readable.
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        $mode = $this->getMetadata('mode');
        return str_contains((string)$mode, 'r') || str_contains((string)$mode, '+');
    }

    /**
     * Read data from the stream.
     *
     * @param int $length  Maximum number of bytes to read
     * @return string
     * @throws InvalidArgumentException if $length is negative
     * @throws RuntimeException on failure
     */
    public function read($length): string
    {
        if ($length < 0) {
            throw new InvalidArgumentException('Length parameter cannot be negative');
        }
        $data = fread($this->resource, $length);
        if ($data === false) {
            throw new RuntimeException('Unable to read from stream');
        }
        return $data;
    }

    /**
     * Returns the remaining contents in a string.
     *
     * @return string
     * @throws RuntimeException on failure
     */
    public function getContents(): string
    {
        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents');
        }
        return $contents;
    }

    /**
     * Retrieve stream metadata as an associative array or specific key.
     *
     * @param string|null $key  Metadata key or null for all
     * @return mixed
     */
    public function getMetadata($key = null): mixed
    {
        $meta = stream_get_meta_data($this->resource);
        if ($key === null) {
            return $meta;
        }
        return $meta[$key] ?? null;
    }
}