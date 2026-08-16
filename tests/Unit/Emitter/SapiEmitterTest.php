<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Emitter;

use MonkeysLegion\Http\Emitter\SapiEmitter;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SapiEmitterTest extends TestCase
{
    #[Test]
    public function emit_outputs_the_body(): void
    {
        $emitter = new SapiEmitter();

        \ob_start();
        $emitter->emit(Response::text('Hello World'));
        $output = (string) \ob_get_clean();

        $this->assertSame('Hello World', $output);
    }

    #[Test]
    public function emit_skips_body_for_204(): void
    {
        $response = Response::noContent()->withBody(Stream::createFromString('must not appear'));
        $emitter  = new SapiEmitter();

        \ob_start();
        $emitter->emit($response);
        $output = (string) \ob_get_clean();

        $this->assertSame('', $output);
    }

    #[Test]
    public function emit_skips_body_for_304(): void
    {
        $response = Response::text('stale')->withStatus(304)->withBody(Stream::createFromString('cached'));
        $emitter  = new SapiEmitter();

        \ob_start();
        $emitter->emit($response);
        $output = (string) \ob_get_clean();

        $this->assertSame('', $output);
    }

    #[Test]
    public function emit_streams_in_chunks(): void
    {
        $body     = \str_repeat('x', 100);
        $emitter  = new SapiEmitter(chunkSize: 16);

        \ob_start();
        $emitter->emit(Response::text($body));
        $output = (string) \ob_get_clean();

        $this->assertSame($body, $output);
    }
}
