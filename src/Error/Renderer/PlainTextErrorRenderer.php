<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error\Renderer;

class PlainTextErrorRenderer implements ErrorRendererInterface
{
    public function render(\Throwable $exception, bool $debug = false): string
    {
        $output = "ERROR: ";
        $output .= $debug ? $exception->getMessage() : 'An unexpected error occurred.';
        $output .= "\n";

        if ($debug) {
            $output .= "\nException: " . get_class($exception) . "\n";
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
