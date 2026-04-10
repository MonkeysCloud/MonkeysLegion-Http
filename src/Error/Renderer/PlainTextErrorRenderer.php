<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Error\Renderer;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Plain-text error renderer for CLI and API debugging.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class PlainTextErrorRenderer implements ErrorRendererInterface
{
    public function render(\Throwable $exception, bool $debug = false): string
    {
        $output = 'ERROR: ';
        $output .= $debug ? $exception->getMessage() : 'An unexpected error occurred.';
        $output .= "\n";

        if ($debug) {
            $output .= "\nException: " . $exception::class . "\n";
            $output .= "File: {$exception->getFile()}:{$exception->getLine()}\n";
            $output .= "\nStack Trace:\n" . $exception->getTraceAsString() . "\n";
        }

        return $output;
    }

    public function getContentType(): string
    {
        return 'text/plain';
    }
}
