<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Emitter;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Emits a PSR-7 response to the PHP SAPI (apache/fpm/cli-server).
 *
 * v2 improvements:
 *  • Auto-injects Content-Length header when body size is known
 *  • Guards against headers_sent() — throws instead of silent corruption
 *  • Configurable chunk size for streaming large responses
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class SapiEmitter
{
    /**
     * @param int $chunkSize Bytes per iteration when streaming body.
     */
    public function __construct(
        private readonly int $chunkSize = 8192,
    ) {}

    /**
     * Emit the response to the client.
     *
     * @throws RuntimeException If headers have already been sent.
     */
    public function emit(ResponseInterface $response): void
    {
        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf(
                'Headers already sent in %s on line %d. Cannot emit response.',
                $file,
                $line,
            ));
        }

        // Auto-inject Content-Length if body size is known and header not set
        $body = $response->getBody();
        $size = $body->getSize();
        if ($size !== null && !$response->hasHeader('Content-Length')) {
            $response = $response->withHeader('Content-Length', (string) $size);
        }

        // 1) Status line
        $protocol = $response->getProtocolVersion();
        $status   = $response->getStatusCode();
        $reason   = $response->getReasonPhrase();
        header(sprintf('HTTP/%s %d %s', $protocol, $status, $reason), true, $status);

        // 2) Headers
        foreach ($response->getHeaders() as $name => $values) {
            $replace = true;
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), $replace);
                $replace = false; // only replace first occurrence
            }
        }

        // 3) Body — skip for 204/304
        if (in_array($status, [204, 304], true)) {
            return;
        }

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            echo $body->read($this->chunkSize);

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }
}