<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error;

class ErrorHandler
{
    private HtmlErrorRenderer $renderer;

    public function __construct() {}

    public function useRenderer(HtmlErrorRenderer $renderer): void
    {
        $this->renderer = $renderer;
    }

    public function register(): void
    {
        // Catch exceptions and normal errors
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        // Catch fatal / parse errors
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function render(\Throwable $e): string
    {
        return $this->renderer->render($e);
    }

    public function getContentType(): string
    {
        return 'text/html';
    }

    public function handleException(\Exception $e): void
    {
        // avoid duplicate responses
        if (!headers_sent()) {
            http_response_code(500);
        }

        // clear any partially sent output buffer if you want a clean page
        if (ob_get_length()) {
            ob_clean();
        }

        echo $this->render($e);
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        $this->handleException(new \ErrorException(
            $errstr,
            0,
            $errno,
            $errfile,
            $errline
        ));
        // true = don’t let PHP’s internal error handler run
        return true;
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null) {
            // don’t render again if something already printed
            if (headers_sent() || ob_get_length() > 0) {
                return;
            }

            $this->handleException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }
}
