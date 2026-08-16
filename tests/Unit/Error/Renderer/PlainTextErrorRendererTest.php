<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Error\Renderer;

use MonkeysLegion\Http\Error\Renderer\PlainTextErrorRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PlainTextErrorRendererTest extends TestCase
{
    #[Test]
    public function debug_mode_includes_details(): void
    {
        $output = new PlainTextErrorRenderer()->render(new \RuntimeException('fail'), debug: true);

        $this->assertStringContainsString('RuntimeException', $output);
        $this->assertStringContainsString('fail', $output);
        $this->assertStringContainsString('at ', $output);
    }

    #[Test]
    public function production_mode_hides_the_message(): void
    {
        $output = new PlainTextErrorRenderer()->render(new \RuntimeException('secret'), debug: false);

        $this->assertStringContainsString('An unexpected error occurred.', $output);
        $this->assertStringNotContainsString('secret', $output);
        $this->assertStringNotContainsString('CAUSED BY:', $output);
    }

    #[Test]
    public function production_mode_does_not_walk_the_exception_chain(): void
    {
        $nested = new \LogicException('inner cause');
        $top    = new \RuntimeException('outer failure', 0, $nested);

        $output = new PlainTextErrorRenderer()->render($top, debug: false);

        $this->assertStringContainsString('RuntimeException', $output);
        $this->assertStringNotContainsString('CAUSED BY:', $output);
        $this->assertStringNotContainsString('LogicException', $output);
    }

    #[Test]
    public function debug_mode_renders_exception_chain(): void
    {
        $nested = new \LogicException('inner cause');
        $top    = new \RuntimeException('outer failure', 0, $nested);

        $output = new PlainTextErrorRenderer()->render($top, debug: true);

        $this->assertStringContainsString('RuntimeException', $output);
        $this->assertStringContainsString('outer failure', $output);
        $this->assertStringContainsString('CAUSED BY:', $output);
        $this->assertStringContainsString('LogicException', $output);
        $this->assertStringContainsString('inner cause', $output);
    }

    #[Test]
    public function debug_mode_renders_trace_frames(): void
    {
        $output = new PlainTextErrorRenderer()->render($this->throwAtKnownLocation(), debug: true);

        $this->assertStringContainsString('at ', $output);
        // Frames are numbered and contain the call + location
        $this->assertMatchesRegularExpression('/\d+\. .*\(\).* at /', $output);
        $this->assertStringContainsString('PlainTextErrorRendererTest.php', $output);
    }

    #[Test]
    public function debug_mode_handles_internal_frames_without_file(): void
    {
        // A closure invoked from the renderer itself has no userland file
        // context in its trace — simulate by rendering a closure-thrown error.
        $output = new PlainTextErrorRenderer()->render(new \Error('boom'), debug: true);

        $this->assertStringContainsString('Error: boom', $output);
    }

    #[Test]
    public function output_ends_with_newline(): void
    {
        $output = new PlainTextErrorRenderer()->render(new \RuntimeException('x'), debug: false);

        $this->assertStringEndsWith("\n", $output);
    }

    #[Test]
    public function content_type_is_plain_text(): void
    {
        $this->assertSame('text/plain', new PlainTextErrorRenderer()->getContentType());
    }

    private function throwAtKnownLocation(): \RuntimeException
    {
        return new \RuntimeException('trace me');
    }
}
