<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error;

class ErrorHandler
{
    private HtmlErrorRenderer $renderer;
    private static bool $hasRendered = false;
    private static bool $isHandling = false; // Prevent recursion

    public function __construct() {}

    public function useRenderer(HtmlErrorRenderer $renderer): void
    {
        $this->renderer = $renderer;
    }

    public function register(): void
    {
        // Catch exceptions and normal errors
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError'], E_ALL);

        // Catch fatal / parse errors
        register_shutdown_function([$this, 'handleShutdown']);

        // Set memory limit for error handling (prevents out-of-memory during error handling)
        ini_set('memory_limit', -1);
    }

    public function render(\Throwable $e): string
    {
        return $this->renderer->render($e);
    }

    public function getContentType(): string
    {
        return 'text/html';
    }

    // Accept Throwable instead of Exception to catch Error class too
    public function handleException(\Throwable $e): void
    {
        // Prevent infinite recursion
        if (self::$isHandling || self::$hasRendered) {
            return;
        }

        self::$isHandling = true;
        self::$hasRendered = true;

        try {
            // Clean any output buffers
            while (ob_get_level() > 0) ob_end_clean();

            // Set headers if possible
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: ' . $this->getContentType());
            }

            // Log the error
            error_log((string) $e);

            echo $this->render($e);
        } catch (\Throwable $renderException) {
            // If rendering fails, output basic error
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain');
            }
            echo "Internal Server Error\n";
            echo "Original error: " . $e->getMessage() . "\n";
            echo "Render error: " . $renderException->getMessage() . "\n";
        } finally {
            self::$isHandling = false;
        }

        // Exit to prevent further execution
        if (PHP_SAPI !== 'cli') {
            exit(1);
        }
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) return false; // respect @ suppression

        // Convert PHP errors to exceptions
        $this->handleException(new \ErrorException(
            $errstr,
            0,
            $errno,
            $errfile,
            $errline
        ));

        return true; // prevent PHP internal handler
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        // Only handle fatal errors and parse errors
        $fatalErrors = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_CORE_WARNING,
            E_COMPILE_ERROR,
            E_COMPILE_WARNING,
            E_RECOVERABLE_ERROR
        ];
        if (!in_array($error['type'], $fatalErrors, true)) return;

        // Don't render if we already have
        if (self::$hasRendered) return;

        $this->handleException(new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        ));
    }
}
