<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Error;

use Throwable;
use ErrorException;
use RuntimeException;
use MonkeysLegion\Core\Error\Renderer\{ErrorRendererInterface, JsonErrorRenderer};
use MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface;

/**
 * Robust error handler that handles all edge cases gracefully
 */
class ErrorHandler
{
    private ErrorRendererInterface $renderer;
    private ?MonkeysLoggerInterface $logger = null;
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

    public function useLogger(MonkeysLoggerInterface $logger): void
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
            // If custom renderer fails, fallback to safe built-in renderers before emergency plain text
            try {
                [$fallbackContentType, $fallbackOutput] = $this->renderWithFallbackRenderer($exception, $renderException);

                if (!headers_sent()) {
                    header("Content-Type: {$fallbackContentType}; charset=UTF-8", true);
                }

                echo $fallbackOutput;
            } catch (Throwable $fallbackException) {
                $this->emergencyResponse("Error rendering failed", $exception, $renderException);
            }
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function renderWithFallbackRenderer(Throwable $exception, Throwable $renderException): array
    {
        $preferred = $this->resolvePreferredContentType();

        if ($preferred === 'application/json') {
            return ['application/json', $this->renderFallbackJson($exception, $renderException)];
        }

        if ($preferred === 'text/plain') {
            return ['text/plain', $this->renderFallbackPlainText($exception, $renderException)];
        }

        $htmlRenderer = new Renderer\BasicHtmlErrorRenderer();
        $output = $htmlRenderer->render($exception, $this->debug);

        if ($this->debug) {
            $debugInfo = "\n<!-- Nested render exception -->\n";
            $debugInfo .= "<div style=\"background: #b91c1c; color: white; padding: 1rem 2rem; position: relative; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.2); font-family: 'Inter', sans-serif;\">";
            $debugInfo .= "<div style=\"max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; gap: 0.5rem;\">";
            $debugInfo .= "<div style=\"display: flex; align-items: center; gap: 0.75rem;\">";
            $debugInfo .= "<span style=\"background: rgba(255,255,255,0.2); padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;\">Renderer Failure</span>";
            $debugInfo .= "<span style=\"font-weight: 600; font-size: 0.95rem;\">" . htmlspecialchars($renderException->getMessage(), ENT_QUOTES, 'UTF-8') . "</span>";
            $debugInfo .= "</div>";
            $debugInfo .= "<div style=\"font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; opacity: 0.8;\">";
            $debugInfo .= "Occurred at " . htmlspecialchars($renderException->getFile(), ENT_QUOTES, 'UTF-8') . ":" . $renderException->getLine();
            $debugInfo .= "</div></div></div>";

            // Inject at the start of the body
            if (preg_match('/<body[^>]*>/i', $output, $matches, PREG_OFFSET_CAPTURE)) {
                $insertPos = $matches[0][1] + strlen($matches[0][0]);
                $output = substr($output, 0, $insertPos) . $debugInfo . substr($output, $insertPos);
            } else {
                $output = $debugInfo . $output;
            }
        }

        return ['text/html', $output];
    }

    private function resolvePreferredContentType(): string
    {
        try {
            $contentType = $this->renderer->getContentType();
        } catch (Throwable) {
            $contentType = '';
        }

        if ($contentType !== '') {
            return $contentType;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json')) {
            return 'application/json';
        }
        if (str_contains($accept, 'text/plain')) {
            return 'text/plain';
        }

        return 'text/html';
    }

    private function renderFallbackPlainText(Throwable $exception, Throwable $renderException): string
    {
        $output = (new Renderer\PlainTextErrorRenderer())->render($exception, $this->debug);

        if ($this->debug) {
            $output .= "\nNested Exception (while rendering error page):\n";
            $output .= $renderException->getMessage() . "\n";
            $output .= "File: " . $renderException->getFile() . ":" . $renderException->getLine() . "\n";
        }

        return $output;
    }

    private function renderFallbackJson(Throwable $exception, Throwable $renderException): string
    {
        $payload = [
            'error' => true,
            'message' => $this->debug ? $exception->getMessage() : 'An unexpected error occurred.',
            'timestamp' => date('c'),
        ];

        if ($this->debug) {
            $payload['debug'] = [
                'type' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
                'nested_renderer_exception' => [
                    'type' => get_class($renderException),
                    'message' => $renderException->getMessage(),
                    'file' => $renderException->getFile(),
                    'line' => $renderException->getLine(),
                ],
            ];
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode fallback JSON error response.');
        }

        return $encoded;
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
        $data = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->debug ? $exception->getTraceAsString() : '[hidden]'
        ];

        if ($exception->getPrevious()) {
            $data['previous'] = $this->exceptionToArray($exception->getPrevious());
        }

        return $data;
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
