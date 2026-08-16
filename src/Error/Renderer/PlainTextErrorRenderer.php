<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error\Renderer;

use MonkeysLegion\Core\Error\Renderer\ErrorRendererInterface;
use Throwable;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Plain-text error renderer for CLI and API debugging.
 *
 * @deprecated 2.x Use \MonkeysLegion\Core\Error\Renderer\PlainTextErrorRenderer instead.
 *             This legacy HTTP namespace alias will be removed in a future major release.
 *
 * This legacy alias is intentionally self-contained (it does not extend the Core
 * renderer) so the HTTP package has no hard runtime dependency on the Core
 * package's CLI console traits.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class PlainTextErrorRenderer implements ErrorRendererInterface
{
    public function render(Throwable $exception, bool $debug = false): string
    {
        $output   = '';
        $current  = $exception;
        $index    = 0;

        while ($current !== null) {
            if ($index > 0) {
                $output .= "\nCAUSED BY:\n";
            }

            $output .= \sprintf(
                "%s: %s\n",
                $current::class,
                $debug ? $current->getMessage() : 'An unexpected error occurred.',
            );

            if ($debug) {
                $output .= \sprintf(
                    "  at %s:%d\n\n",
                    $current->getFile(),
                    $current->getLine(),
                );
                $output .= $this->formatTrace($current);
            }

            $current = $current->getPrevious();
            $index++;

            if (!$debug) {
                break;
            }
        }

        return $output . "\n";
    }

    public function getContentType(): string
    {
        return 'text/plain';
    }

    private function formatTrace(Throwable $exception): string
    {
        $output = '';
        foreach ($exception->getTrace() as $i => $frame) {
            $call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
            $file = $frame['file'] ?? '[internal]';
            $line = isset($frame['line']) ? ':' . $frame['line'] : '';
            $output .= \sprintf("  %d. %s() at %s%s\n", $i, $call, $file, $line);
        }
        return $output;
    }
}
