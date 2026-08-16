<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Message;

use InvalidArgumentException;
use MonkeysLegion\Http\Message\Stream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StreamTest extends TestCase
{
    #[Test]
    public function rejects_non_resource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @phpstan-ignore-next-line intentional invalid argument */
        new Stream('not a resource');
    }

    #[Test]
    public function create_from_string_reads_back_content(): void
    {
        $stream = Stream::createFromString('hello world');

        $this->assertSame('hello world', (string) $stream);
        $this->assertSame(11, $stream->getSize());
    }

    #[Test]
    public function create_from_empty_string_has_zero_size(): void
    {
        $stream = Stream::createFromString();

        $this->assertSame('', (string) $stream);
        $this->assertSame(0, $stream->getSize());
    }

    #[Test]
    public function create_from_file_reads_contents(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'stream-');
        \file_put_contents($file, 'file contents');

        try {
            $stream = Stream::createFromFile($file);
            $this->assertSame('file contents', (string) $stream);
        } finally {
            @\unlink($file);
        }
    }

    #[Test]
    public function create_from_file_throws_when_missing(): void
    {
        $this->expectException(RuntimeException::class);
        Stream::createFromFile('/nonexistent/definitely-missing-file-' . \uniqid());
    }

    #[Test]
    public function empty_creates_writable_stream(): void
    {
        $stream = Stream::empty();

        $this->assertSame(0, $stream->getSize());
        $this->assertTrue($stream->isWritable());
        $this->assertSame(5, $stream->write('abcde'));
        $this->assertSame('abcde', (string) $stream);
    }

    #[Test]
    public function to_string_rewinds_and_returns_contents(): void
    {
        $stream = Stream::createFromString('abc');
        $stream->seek(2); // move pointer mid-stream

        $this->assertSame('abc', (string) $stream);
    }

    #[Test]
    public function to_string_returns_empty_on_error(): void
    {
        $stream = Stream::createFromString('abc');
        $stream->close();

        $this->assertSame('', (string) $stream);
    }

    #[Test]
    public function close_detaches_resource(): void
    {
        $stream = Stream::createFromString('abc');
        $stream->close();

        $this->assertNull($stream->getSize());
        $this->assertTrue($stream->eof());
        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertFalse($stream->isSeekable());
        $this->assertSame([], $stream->getMetadata());
        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    #[Test]
    public function detach_returns_resource_and_detaches(): void
    {
        $stream = Stream::createFromString('abc');
        $resource = $stream->detach();

        $this->assertIsResource($resource);
        $this->assertNull($stream->getSize());
        $this->assertNull($stream->detach()); // already detached
        $this->expectException(RuntimeException::class);
        $stream->tell();
    }

    #[Test]
    public function tell_reports_current_position(): void
    {
        $stream = Stream::createFromString('hello world');
        $stream->seek(5);

        $this->assertSame(5, $stream->tell());
    }

    #[Test]
    public function seek_and_rewind_move_the_pointer(): void
    {
        $stream = Stream::createFromString('hello world');
        $stream->seek(6);
        $this->assertSame('world', $stream->read(5));

        $stream->rewind();
        $this->assertSame(0, $stream->tell());
        $this->assertSame('hello', $stream->read(5));
    }

    #[Test]
    public function seek_supports_whence_relative_and_end(): void
    {
        $stream = Stream::createFromString('hello world');
        $stream->seek(-5, \SEEK_END);
        $this->assertSame('world', $stream->read(5)); // now at position 10

        $stream->seek(-3, \SEEK_CUR); // back to position 7
        $this->assertSame('rld', $stream->read(3));
    }

    #[Test]
    public function seek_throws_on_unseekable_stream(): void
    {
        $handle = \fopen('php://output', 'w');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open php://output.');
        }
        $stream = new Stream($handle);
        $this->expectException(RuntimeException::class);
        $stream->seek(0);
    }

    #[Test]
    public function eof_is_false_until_end(): void
    {
        $stream = Stream::createFromString('abc');
        $this->assertFalse($stream->eof());
        $stream->read(3);
        $this->assertFalse($stream->eof()); // pointer at end, but EOF flag only set by a read past it
        $stream->read(1);
        $this->assertTrue($stream->eof());
    }

    #[Test]
    public function writable_modes_are_detected(): void
    {
        $rPlusHandle = \fopen('php://temp', 'r+');
        if ($rPlusHandle === false) {
            throw new \RuntimeException('Unable to open php://temp.');
        }
        $rPlus = new Stream($rPlusHandle);
        $this->assertTrue($rPlus->isWritable());
        $this->assertTrue($rPlus->isReadable());
        $rPlus->close();

        // php://temp/memory always report "w+b", so use a real file for a
        // write-only mode ('w' without '+')
        $file = \tempnam(\sys_get_temp_dir(), 'stream-w-');
        try {
            $wHandle = \fopen($file, 'w');
            if ($wHandle === false) {
                throw new \RuntimeException('Unable to open temp file.');
            }
            $wOnly = new Stream($wHandle);
            $this->assertTrue($wOnly->isWritable());
            $this->assertFalse($wOnly->isReadable());
            $wOnly->close();

            $rHandle = \fopen($file, 'r');
            if ($rHandle === false) {
                throw new \RuntimeException('Unable to open temp file.');
            }
            $rOnly = new Stream($rHandle);
            $this->assertTrue($rOnly->isReadable());
            $this->assertFalse($rOnly->isWritable());
            $rOnly->close();
        } finally {
            @\unlink($file);
        }
    }

    #[Test]
    public function write_returns_bytes_written_and_moves_pointer(): void
    {
        $stream = Stream::createFromString('');
        $this->assertSame(3, $stream->write('abc'));
        $this->assertSame(3, $stream->getSize());
        $this->assertSame(3, $stream->tell());
    }

    #[Test]
    public function write_throws_on_read_only_stream(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'stream-ro-');
        try {
            $handle = \fopen($file, 'r');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open temp file.');
            }
            $stream = new Stream($handle);
            $this->expectException(RuntimeException::class);
            $stream->write('nope');
        } finally {
            @\unlink($file);
        }
    }

    #[Test]
    public function read_returns_requested_length(): void
    {
        $stream = Stream::createFromString('hello world');
        $this->assertSame('hello', $stream->read(5));
        $this->assertSame(' world', $stream->read(6));
        $this->assertSame('', $stream->read(1)); // EOF
    }

    #[Test]
    public function read_with_zero_length_returns_empty_string(): void
    {
        $stream = Stream::createFromString('hello');
        $this->assertSame('', $stream->read(0));
        $this->assertSame(0, $stream->tell());
    }

    #[Test]
    public function read_with_negative_length_throws(): void
    {
        $stream = Stream::createFromString('hello');
        $this->expectException(InvalidArgumentException::class);
        $stream->read(-1);
    }

    #[Test]
    public function read_throws_on_write_only_stream(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'stream-wo-');
        \file_put_contents($file, 'abc');
        try {
            $handle = \fopen($file, 'w');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open temp file.');
            }
            $stream = new Stream($handle);
            $this->expectException(RuntimeException::class);
            $stream->read(1);
        } finally {
            @\unlink($file);
        }
    }

    #[Test]
    public function get_contents_reads_from_current_position(): void
    {
        $stream = Stream::createFromString('hello world');
        $stream->seek(6);
        $this->assertSame('world', $stream->getContents());
    }

    #[Test]
    public function get_metadata_returns_full_array_or_single_key(): void
    {
        $stream = Stream::createFromString('abc');
        $meta = $stream->getMetadata();

        $this->assertIsArray($meta);
        $this->assertSame('php://temp', $stream->getMetadata('uri'));
        $this->assertNull($stream->getMetadata('nonexistent-key'));
    }

    #[Test]
    public function metadata_is_empty_after_detach(): void
    {
        $stream = Stream::createFromString('abc');
        $stream->detach();

        $this->assertSame([], $stream->getMetadata());
        $this->assertNull($stream->getMetadata('uri'));
    }

    #[Test]
    public function operations_throw_after_detach(): void
    {
        $stream = Stream::createFromString('abc');
        $stream->detach();

        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }
}
