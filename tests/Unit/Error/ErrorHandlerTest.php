<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Error;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The global ErrorHandler echoes output and flushes all output buffers, which
 * conflicts with PHPUnit's own buffering. These tests run the handler inside a
 * dedicated PHP subprocess and assert on its stdout.
 */
final class ErrorHandlerTest extends TestCase
{
    #[Test]
    public function debug_mode_outputs_exception_details(): void
    {
        $output = $this->runHandler(
            '$h = new \MonkeysLegion\Http\Error\ErrorHandler(debug: true);'
            . '$h->useRenderer(new \MonkeysLegion\Http\Error\Renderer\JsonErrorRenderer());'
            . '$h->handleException(new \RuntimeException(\'boom\'));',
        );

        $data = $this->decodeJson($output);

        $this->assertSame('error', $data['status']);
        $this->assertSame('boom', $data['message']);
        $this->assertArrayHasKey('debug', $data);
    }

    #[Test]
    public function production_mode_hides_exception_details(): void
    {
        $output = $this->runHandler(
            '$h = new \MonkeysLegion\Http\Error\ErrorHandler(debug: false);'
            . '$h->useRenderer(new \MonkeysLegion\Http\Error\Renderer\JsonErrorRenderer());'
            . '$h->handleException(new \RuntimeException(\'secret-detail\'));',
        );

        $data = $this->decodeJson($output);

        $this->assertSame('error', $data['status']);
        $this->assertSame('An unexpected error occurred.', $data['message']);
        $this->assertArrayNotHasKey('debug', $data);
    }

    #[Test]
    public function failing_renderer_falls_back_to_json(): void
    {
        $renderer = 'new class implements \MonkeysLegion\Core\Error\Renderer\ErrorRendererInterface {'
            . ' public function render(\Throwable $e, bool $debug = false): string { throw new \RuntimeException(\'renderer broke\'); }'
            . ' public function getContentType(): string { return \'application/json\'; }'
            . ' }';

        $output = $this->runHandler(
            '$h = new \MonkeysLegion\Http\Error\ErrorHandler(debug: false);'
            . '$h->useRenderer(' . $renderer . ');'
            . '$h->handleException(new \RuntimeException(\'orig\'));',
        );

        $data = $this->decodeJson($output);

        $this->assertTrue($data['error']);
        $this->assertSame('An unexpected error occurred.', $data['message']);
    }

    #[Test]
    public function handle_error_converts_to_exception_output(): void
    {
        $output = $this->runHandler(
            '$h = new \MonkeysLegion\Http\Error\ErrorHandler(debug: true);'
            . '$h->useRenderer(new \MonkeysLegion\Http\Error\Renderer\JsonErrorRenderer());'
            . '$h->handleError(E_WARNING, \'custom warning\', __FILE__, 1);',
        );

        $data = $this->decodeJson($output);

        $this->assertSame('custom warning', $data['message']);
    }

    #[Test]
    public function exceptions_are_sent_to_the_psr3_logger(): void
    {
        $logger = 'new class implements \Psr\Log\LoggerInterface {'
            . ' public function error(\Stringable|string $message, array $context = []): void { fwrite(STDOUT, \'LOGGED: \' . (string) $message); }'
            . ' public function emergency(\Stringable|string $message, array $context = []): void {}'
            . ' public function alert(\Stringable|string $message, array $context = []): void {}'
            . ' public function critical(\Stringable|string $message, array $context = []): void {}'
            . ' public function warning(\Stringable|string $message, array $context = []): void {}'
            . ' public function notice(\Stringable|string $message, array $context = []): void {}'
            . ' public function info(\Stringable|string $message, array $context = []): void {}'
            . ' public function debug(\Stringable|string $message, array $context = []): void {}'
            . ' public function log($level, \Stringable|string $message, array $context = []): void {}'
            . ' }';

        $output = $this->runHandler(
            '$h = new \MonkeysLegion\Http\Error\ErrorHandler(debug: true);'
            . '$h->useRenderer(new \MonkeysLegion\Http\Error\Renderer\JsonErrorRenderer());'
            . '$h->useLogger(' . $logger . ');'
            . '$h->handleException(new \RuntimeException(\'logged-message\'));',
        );

        $this->assertStringContainsString('LOGGED: logged-message', $output);
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Run a snippet of PHP inside a subprocess and return its stdout.
     */
    private function runHandler(string $phpCode): string
    {
        $script = 'require getcwd() . \'/vendor/autoload.php\';' . $phpCode;
        $output = \shell_exec(\PHP_BINARY . ' -d error_log=/dev/null -r ' . \escapeshellarg($script));

        if (!\is_string($output)) {
            throw new \RuntimeException('Failed to execute error handler subprocess.');
        }

        return $output;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body): array
    {
        $data = \json_decode($body, true);
        if (!\is_array($data)) {
            throw new \RuntimeException('Subprocess did not produce JSON output: ' . $body);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
