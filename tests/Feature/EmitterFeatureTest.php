<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Real HTTP round-trip through PHP's built-in server.
 *
 * Starts `php -S` with a router fixture, sends actual HTTP requests over
 * TCP, and asserts on the raw response — status line, headers, and body.
 * This exercises SapiEmitter exactly as production would (header() against
 * a real SAPI, chunked streaming, Content-Length auto-injection).
 */
final class EmitterFeatureTest extends TestCase
{
    /** @var resource|false|null */
    private $server = null;
    /** @var resource|null */
    private $serverStdin;
    /** @var list<string> */
    private array $tempFiles = [];
    private int $port = 0;

    protected function setUp(): void
    {
        $this->skipUnlessIntegrationEnabled();

        $router = \dirname(__DIR__) . '/Feature/fixtures/router.php';
        $this->port = $this->findFreePort();

        // Use an array command so proc_open exec()s php directly — a string
        // command would go through `sh -c`, and proc_terminate() would then
        // kill only the shell wrapper, orphaning the real `php -S` server.
        $cmd = [
            \PHP_BINARY,
            '-d',
            'variables_order=EGPCS',
            '-S',
            '127.0.0.1:' . $this->port,
            $router,
        ];

        // Redirect the child's stdout/stderr to files instead of pipes: the
        // long-running `php -S` would otherwise keep the inherited stdout pipe
        // open and hang any parent process that pipes PHPUnit's output.
        $stdoutFile = \tempnam(\sys_get_temp_dir(), 'emitter-out-');
        $stderrFile = \tempnam(\sys_get_temp_dir(), 'emitter-err-');
        $this->tempFiles = [$stdoutFile, $stderrFile];
        $stdout = \fopen($stdoutFile, 'wb');
        $stderr = \fopen($stderrFile, 'wb');
        if ($stdout === false || $stderr === false) {
            throw new \RuntimeException('Unable to open server output files.');
        }

        $this->server = \proc_open(
            $cmd,
            [0 => ['pipe', 'r'], 1 => $stdout, 2 => $stderr],
            $pipes,
        );
        if ($this->server === false) {
            throw new \RuntimeException('Unable to start PHP built-in server.');
        }
        $this->serverStdin = $pipes[0] ?? null;
        \fclose($stdout);
        \fclose($stderr);

        $this->waitUntilReady();
    }

    protected function tearDown(): void
    {
        if (\is_resource($this->serverStdin)) {
            \fclose($this->serverStdin);
        }
        if (\is_resource($this->server)) {
            \proc_terminate($this->server);
            // Give the server a moment to exit, then escalate to SIGKILL if
            // it is still running — proc_close() would otherwise block.
            $deadline = \microtime(true) + 3;
            while (\microtime(true) < $deadline) {
                $status = \proc_get_status($this->server);
                if (!$status['running']) {
                    break;
                }
                \usleep(50_000);
            }
            $status = \proc_get_status($this->server);
            if ($status['running']) {
                \posix_kill($status['pid'], \SIGKILL);
            }
            \proc_close($this->server);
        }
        foreach ($this->tempFiles as $file) {
            @\unlink($file);
        }
    }

    #[Test]
    public function real_http_round_trip_emits_status_body_and_content_length(): void
    {
        [$status, $headers, $body] = $this->get('/');

        $this->assertSame('HTTP/1.1 200 OK', $status);
        $this->assertSame('Hello World', $body);
        $this->assertSame('11', $headers['content-length'] ?? null);
        $this->assertSame('text/plain; charset=UTF-8', $headers['content-type'] ?? null);
    }

    #[Test]
    public function real_http_round_trip_json_response(): void
    {
        [$status, $headers, $body] = $this->get('/json');

        $this->assertSame('HTTP/1.1 200 OK', $status);
        $this->assertSame('application/json', $headers['content-type'] ?? null);
        $this->assertSame('{"ok":true}', $body);
    }

    #[Test]
    public function real_http_204_has_no_body(): void
    {
        [$status, , $body] = $this->get('/nocontent');

        $this->assertSame('HTTP/1.1 204 No Content', $status);
        $this->assertSame('', $body);
    }

    #[Test]
    public function real_http_304_has_no_body(): void
    {
        [$status, , $body] = $this->get('/notmodified');

        $this->assertSame('HTTP/1.1 304 Not Modified', $status);
        $this->assertSame('', $body);
    }

    #[Test]
    public function real_http_emits_custom_and_multi_value_headers(): void
    {
        [, $headers] = $this->get('/custom');

        $this->assertSame('yes', $headers['x-custom'] ?? null);
        // php -S joins repeated headers with ", " — both values must be present
        $this->assertSame('a, b', $headers['x-multi'] ?? null);
    }

    #[Test]
    public function real_http_streams_large_bodies(): void
    {
        [, , $body] = $this->get('/large');

        $this->assertSame(100_000, \strlen($body));
        $this->assertSame(\str_repeat('x', 100_000), $body);
    }

    /**
     * @return array{string, array<string, string>, string} [statusLine, headers, body]
     */
    private function get(string $path): array
    {
        $context = \stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        $body = @\file_get_contents('http://127.0.0.1:' . $this->port . $path, false, $context);
        if ($body === false) {
            throw new \RuntimeException('HTTP request failed.');
        }

        $status = '';
        /** @var array<string, list<string>> $raw */
        $raw = [];
        /** @var list<string> $responseHeaders */
        $responseHeaders = $http_response_header;
        foreach ($responseHeaders as $line) {
            if (\str_starts_with($line, 'HTTP/')) {
                $status = $line;
                continue;
            }
            $pos = \strpos($line, ':');
            if ($pos !== false) {
                $name = \strtolower(\trim(\substr($line, 0, $pos)));
                $raw[$name][] = \trim(\substr($line, $pos + 1));
            }
        }
        // Join repeated headers with ", " as most servers would serialize them.
        $headers = \array_map(static fn (array $values): string => \implode(', ', $values), $raw);

        return [$status, $headers, $body];
    }

    private function findFreePort(): int
    {
        $socket = \stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new \RuntimeException('Unable to allocate a free port.');
        }
        $name = \stream_socket_get_name($socket, false);
        \fclose($socket);
        $port = (int) \substr((string) $name, \strrpos((string) $name, ':') + 1);
        return $port;
    }

    private function waitUntilReady(): void
    {
        $deadline = \microtime(true) + 10;
        while (\microtime(true) < $deadline) {
            $fp = @\fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.5);
            if ($fp !== false) {
                \fclose($fp);
                return;
            }
            \usleep(50_000);
        }
        throw new \RuntimeException('PHP built-in server did not start in time.');
    }

    private function skipUnlessIntegrationEnabled(): void
    {
        if (\getenv('RUN_INTEGRATION_TESTS') === '1') {
            return;
        }

        $this->markTestSkipped('Set RUN_INTEGRATION_TESTS=1 to run HTTP integration tests.');
    }
}
