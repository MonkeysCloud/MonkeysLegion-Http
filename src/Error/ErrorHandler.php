<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error;

use MonkeysLegion\Core\Contracts\FrameworkLoggerInterface;
use Throwable;
use ErrorException;
use MonkeysLegion\Http\Error\Renderer\{ErrorRendererInterface, HtmlErrorRenderer, JsonErrorRenderer};

/**
 * Robust error handler that handles all edge cases gracefully
 */
class ErrorHandler
{
    private ErrorRendererInterface $renderer;
    private ?FrameworkLoggerInterface $logger = null;
    private bool $debug;

    /** @var array<string, float> Track exception handling to prevent infinite loops */
    private static array $handlingStack = [];

    /** @var int Maximum recursion depth before giving up */
    private const MAX_RECURSION_DEPTH = 3;

    /** @var int Memory reserved for error handling (2MB) */
    private const RESERVED_MEMORY_SIZE = 2097152;

    /** @var string|null Reserved memory for fatal error handling */
    private static ?string $reservedMemory = null;

    public function __construct(
        bool $debug = false
    ) {
        $this->renderer = new JsonErrorRenderer();
        $this->debug = $debug;

        // Reserve memory for fatal error handling
        if (self::$reservedMemory === null) {
            self::$reservedMemory = str_repeat('x', self::RESERVED_MEMORY_SIZE);
        }
    }

    public function useRenderer(ErrorRendererInterface $renderer): void
    {
        $this->renderer = $renderer;
    }

    public function useLogger(FrameworkLoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    public function register(): void
    {
        // Set unlimited memory for error handling (within reason)
        ini_set('memory_limit', '256M');

        // Register all error handlers
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError'], E_ALL);
        register_shutdown_function([$this, 'handleShutdown']);

        // Don't let PHP display errors
        ini_set('display_errors', '0');
    }

    public function unregister(): void
    {
        restore_exception_handler();
        restore_error_handler();
        self::$handlingStack = [];
    }

    public function handleException(Throwable $exception): void
    {
        $exceptionId = $this->getExceptionId($exception);

        // Check if we're already handling this specific exception
        if ($this->isAlreadyHandling($exceptionId)) {
            $this->emergencyResponse("Recursive exception detected", $exception);
            return;
        }

        // Check recursion depth
        if (count(self::$handlingStack) >= self::MAX_RECURSION_DEPTH) {
            $this->emergencyResponse("Maximum recursion depth exceeded", $exception);
            return;
        }

        // Mark this exception as being handled
        self::$handlingStack[$exceptionId] = microtime(true);

        try {
            $this->doHandleException($exception);
        } catch (Throwable $handlingException) {
            $this->handleNestedExceptionFailure($exception, $handlingException);
        } finally {
            // Always clean up, even if something went wrong
            unset(self::$handlingStack[$exceptionId]);
        }
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        // Respect error_reporting() setting
        if (!(error_reporting() & $severity)) {
            return false;
        }

        // Convert error to exception and handle it
        $exception = new ErrorException($message, 0, $severity, $file, $line);
        $this->handleException($exception);

        return true; // Don't let PHP handle it
    }

    public function handleShutdown(): void
    {
        // Free reserved memory for fatal error handling
        self::$reservedMemory = null;

        $lastError = error_get_last();
        if ($lastError === null) {
            return;
        }

        // Only handle fatal errors
        $fatalErrorTypes = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_CORE_WARNING,
            E_COMPILE_ERROR,
            E_COMPILE_WARNING,
            E_RECOVERABLE_ERROR
        ];

        if (!in_array($lastError['type'], $fatalErrorTypes, true)) {
            return;
        }

        $exception = new ErrorException(
            $lastError['message'],
            0,
            $lastError['type'],
            $lastError['file'] ?? 'unknown',
            $lastError['line'] ?? 0
        );

        $this->handleException($exception);
    }

    private function doHandleException(Throwable $exception): void
    {
        // Clean any existing output buffers
        $this->cleanOutputBuffers();

        // Log the exception (do this first, before any rendering that might fail)
        $this->logException($exception);

        // Send appropriate HTTP status and headers
        $this->sendErrorHeaders($exception);

        // Render and output the error
        $this->renderAndOutputError($exception);

        // Exit if we're not in CLI and not in debug mode
        $this->exitIfAppropriate();
    }

    private function renderAndOutputError(Throwable $exception): void
    {
        try {
            $output = $this->renderer->render($exception, $this->debug);
            echo $output;
        } catch (Throwable $renderException) {
            // If custom rendering fails, use emergency fallback
            $this->emergencyResponse("Error rendering failed", $exception, $renderException);
        }
    }

    private function handleNestedExceptionFailure(Throwable $original, Throwable $nested): void
    {
        // Log both exceptions if possible
        try {
            $this->logger->critical('Nested exception during error handling', [
                'original' => $this->exceptionToArray($original),
                'nested' => $this->exceptionToArray($nested)
            ]);
        } catch (Throwable $logException) {
            // Even logging failed - use error_log as last resort
            error_log("Critical: Multiple exception failures - " . $original->getMessage());
        }

        $this->emergencyResponse("Nested exception during error handling", $original, $nested);
    }

    private function emergencyResponse(string $reason, Throwable $exception, ?Throwable $nested = null): void
    {
        // Last resort error handling - use only built-in PHP functions

        try {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
            }
        } catch (Throwable $headerException) {
            // Even headers failed - just continue
        }

        echo "Internal Server Error\n";
        echo "Reason: {$reason}\n\n";

        if ($this->debug) {
            echo "Original Exception:\n";
            echo $exception->getMessage() . "\n";
            echo "File: " . $exception->getFile() . ":" . $exception->getLine() . "\n\n";

            if ($nested) {
                echo "Nested Exception:\n";
                echo $nested->getMessage() . "\n";
                echo "File: " . $nested->getFile() . ":" . $nested->getLine() . "\n";
            }
        }

        // Always log to error_log as last resort
        error_log("Emergency response: {$reason} - " . $exception->getMessage());

        if ($this->shouldExit()) {
            exit(1);
        }
    }

    private function cleanOutputBuffers(): void
    {
        // Clean all output buffers, but handle failures gracefully
        try {
            while (ob_get_level() > 0) {
                if (!ob_end_clean()) {
                    break; // If we can't clean a buffer, stop trying
                }
            }
        } catch (Throwable $cleanException) {
            // Buffer cleaning failed - continue anyway
        }
    }

    private function sendErrorHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        try {
            http_response_code(500);

            $contentType = $this->renderer->getContentType();
            header("Content-Type: {$contentType}; charset=UTF-8");

            // Prevent caching of error pages
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        } catch (Throwable $headerException) {
            // Header setting failed - continue anyway
        }
    }

    private function logException(Throwable $exception): void
    {
        try {
            $context = [
                'exception' => $this->exceptionToArray($exception),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'ip' => $this->getClientIp(),
                'timestamp' => date('c'),
            ];

            $this->logger->error($exception->getMessage(), $context);
        } catch (Throwable $logException) {
            // Logging failed - use error_log as fallback
            error_log("Exception: " . $exception->getMessage() . " in " .
                $exception->getFile() . ":" . $exception->getLine());
        }
    }

    private function getExceptionId(Throwable $exception): string
    {
        // Create unique ID based on exception details to detect exact duplicates
        return md5(
            get_class($exception) .
                $exception->getMessage() .
                $exception->getFile() .
                $exception->getLine() .
                $exception->getTraceAsString()
        );
    }

    private function isAlreadyHandling(string $exceptionId): bool
    {
        return isset(self::$handlingStack[$exceptionId]);
    }

    private function exceptionToArray(Throwable $exception): array
    {
        return [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->debug ? $exception->getTraceAsString() : '[hidden]'
        ];
    }

    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_CLIENT_IP',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return 'unknown';
    }

    private function exitIfAppropriate(): void
    {
        if ($this->shouldExit()) {
            exit(1);
        }
    }

    private function shouldExit(): bool
    {
        // Don't exit in CLI mode
        if (PHP_SAPI === 'cli') {
            return false;
        }

        // Don't exit in debug mode (allows for better debugging)
        if ($this->debug) {
            return false;
        }

        // Exit in production web requests
        return true;
    }
}
