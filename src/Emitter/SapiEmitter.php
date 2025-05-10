<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Emitter;

use Psr\Http\Message\ResponseInterface;

/**
 * SapiEmitter is responsible for emitting HTTP responses to the PHP SAPI.
 *
 * This class handles the sending of headers and body content to the client.
 * It is designed to work with PHP's built-in web server and other SAPI environments.
 */
class SapiEmitter
{
    /**
     * @param int $chunkSize Number of bytes to read per iteration when streaming body
     */
    public function __construct(private int $chunkSize = 8192) {}

    /**
     * Emit the HTTP response to PHP’s SAPI.
     */
    public function emit(ResponseInterface $response): void
    {
        // 1) Send status line
        $protocol = $response->getProtocolVersion();
        $status   = $response->getStatusCode();
        $reason   = $response->getReasonPhrase();
        header(sprintf('HTTP/%s %d %s', $protocol, $status, $reason), true, $status);

        // 2) Send headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        // 3) Send body
        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        while (! $body->eof()) {
            echo $body->read($this->chunkSize);
            flush();
        }
    }
}