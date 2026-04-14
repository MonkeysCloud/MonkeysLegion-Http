<?php
declare(strict_types=1);

namespace MonkeysLegion\Core\Error;

use ErrorException;
use MonkeysLegion\Core\Error\Renderer\BasicHtmlErrorRenderer;
use MonkeysLegion\Core\Error\Renderer\ErrorRendererInterface;
use MonkeysLegion\Http\Error\Renderer\JsonErrorRenderer;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Global error handler with recursive-exception protection,
 * reserved memory for OOM scenarios, and PSR-3 logging.
 *
 * v2 improvements:
 *  • Uses PSR-3 LoggerInterface instead of custom MonkeysLoggerInterface
 *  • final class
 *  • Reserved 2 MB memory for fatal error handling
 *  • Infinite-loop guard via handling stack
 *  • Nested failure-safe rendering fallback (HTML/JSON/plain text)
 *  • Custom renderers via Core ErrorRendererInterface
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ErrorHandler
{
    private ErrorRendererInterface $renderer;
    private ?LoggerInterface $logger = null;

    /** @var array<string, float> Track exception handling to prevent infinite loops. */
    private static array $handlingStack = [];

    private const int MAX_RECURSION_DEPTH = 3;
    private const int RESERVED_MEMORY_SIZE = 2_097_152; // 2 MB

    /** Reserved memory freed on fatal errors so the handler can run. */
    private static ?string $reservedMemory = null;

    public function __construct(
        private readonly bool $debug = false,
    ) {
        $this->renderer = new JsonErrorRenderer();

        if (self::$reservedMemory === null) {
            self::$reservedMemory = str_repeat('x', self::RESERVED_MEMORY_SIZE);
        }
    }

    /**
     * Set a custom error renderer implementing Core ErrorRendererInterface.
     */
    public function useRenderer(ErrorRendererInterface $renderer): void
    {
        $this->renderer = $renderer;
    }

    /**
     * Set a PSR-3 logger.
     */
    public function useLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Register this handler as global exception/error/shutdown handler.
     */
    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError'], E_ALL);
        register_shutdown_function([$this, 'handleShutdown']);
        ini_set('display_errors', '0');
    }

    /**
     * Restore the previous exception/error handlers.
     */
    public function unregister(): void
    {
        restore_exception_handler();
        restore_error_handler();
        self::$handlingStack = [];
    }

    // ── Handlers ───────────────────────────────────────────────

    public function handleException(Throwable $exception): void
    {
        $exceptionId = $this->getExceptionId($exception);

        if (isset(self::$handlingStack[$exceptionId])) {
            $this->emergencyResponse('Recursive exception detected', $exception);
            return;
        }

        if (count(self::$handlingStack) >= self::MAX_RECURSION_DEPTH) {
            $this->emergencyResponse('Maximum recursion depth exceeded', $exception);
            return;
        }

        self::$handlingStack[$exceptionId] = microtime(true);

        try {
            $this->doHandleException($exception);
        } catch (Throwable $nested) {
            $this->handleNestedFailure($exception, $nested);
        } finally {
            unset(self::$handlingStack[$exceptionId]);
        }
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $this->handleException(new ErrorException($message, 0, $severity, $file, $line));
        return true;
    }

    public function handleShutdown(): void
    {
        self::$reservedMemory = null;

        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR];
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        $this->handleException(new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'] ?? 'unknown',
            $error['line'] ?? 0,
        ));
    }

    // ── Internal ───────────────────────────────────────────────

    private function doHandleException(Throwable $exception): void
    {
        $this->cleanOutputBuffers();
        $this->logException($exception);
        $this->sendErrorHeaders();

        try {
            $output = $this->renderer->render($exception, $this->debug);
            echo $output;
        } catch (Throwable $renderException) {
            try {
                [$fallbackContentType, $fallbackOutput] = $this->renderWithFallbackRenderer($exception, $renderException);

                if (!headers_sent()) {
                    header("Content-Type: {$fallbackContentType}; charset=UTF-8", true);
                }

                echo $fallbackOutput;
            } catch (Throwable) {
                $this->emergencyResponse('Error rendering failed', $exception, $renderException);
            }
        }
        if (PHP_SAPI !== 'cli' && !$this->debug) {
            exit(1);
        }
    }

    /**
     * Handles failures thrown while handling another throwable.
     */
    private function handleNestedFailure(Throwable $original, Throwable $nested): void
    {
        try {
            $this->logger?->critical('Nested exception during error handling', [
                'original' => $this->exceptionToArray($original),
                'nested'   => $this->exceptionToArray($nested),
            ]);
        } catch (Throwable) {
            error_log('Critical: Multiple exception failures - ' . $original->getMessage());
        }

        $this->emergencyResponse('Nested exception during error handling', $original, $nested);
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

        return ['text/html', $this->renderFallbackHtml($exception, $renderException)];
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

    private function renderFallbackHtml(Throwable $exception, Throwable $renderException): string
    {
        $output = (new BasicHtmlErrorRenderer())->render($exception, $this->debug);

        if (!$this->debug) {
            return $output;
        }

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

        if (preg_match('/<body[^>]*>/i', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPos = $matches[0][1] + strlen($matches[0][0]);
            return substr($output, 0, $insertPos) . $debugInfo . substr($output, $insertPos);
        }

        return $debugInfo . $output;
    }

    private function renderFallbackPlainText(Throwable $exception, Throwable $renderException): string
    {
        $output = "Internal Server Error\n";
        $output .= $this->debug ? ($exception->getMessage() . "\n") : "An unexpected error occurred.\n";

        if ($this->debug) {
            $output .= "Exception: " . $exception::class . "\n";
            $output .= "At: " . $exception->getFile() . ':' . $exception->getLine() . "\n";
            $output .= "Nested renderer exception: " . $renderException->getMessage() . "\n";
            $output .= "Nested at: " . $renderException->getFile() . ':' . $renderException->getLine() . "\n";
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
                'type' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
                'nested_renderer_exception' => [
                    'type' => $renderException::class,
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

    private function emergencyResponse(string $reason, Throwable $exception, ?Throwable $nested = null): void
    {
        try {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
            }
        } catch (Throwable) {
            // headers failed — continue
        }

        echo "Internal Server Error\n";
        echo "Reason: {$reason}\n\n";

        if ($this->debug) {
            echo "Exception: {$exception->getMessage()}\n";
            echo "File: {$exception->getFile()}:{$exception->getLine()}\n\n";

            if ($nested !== null) {
                echo "Nested: {$nested->getMessage()}\n";
                echo "File: {$nested->getFile()}:{$nested->getLine()}\n";
            }
        }

        error_log("Emergency: {$reason} - {$exception->getMessage()}");

        if (PHP_SAPI !== 'cli' && !$this->debug) {
            exit(1);
        }
    }

    private function cleanOutputBuffers(): void
    {
        try {
            while (ob_get_level() > 0) {
                if (!ob_end_clean()) {
                    break;
                }
            }
        } catch (Throwable) {
            // ignore
        }
    }

    private function sendErrorHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        try {
            http_response_code(500);
            header(sprintf('Content-Type: %s; charset=UTF-8', $this->renderer->getContentType()));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        } catch (Throwable) {
            // ignore
        }
    }

    private function logException(Throwable $exception): void
    {
        if ($this->logger === null) {
            error_log(sprintf(
                'Exception: %s in %s:%d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));
            return;
        }

        try {
            $this->logger->error($exception->getMessage(), [
                'exception' => $this->exceptionToArray($exception),
                'timestamp' => date('c'),
            ]);
        } catch (Throwable) {
            error_log(sprintf(
                'Exception: %s in %s:%d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));
        }
    }

    private function getExceptionId(Throwable $exception): string
    {
        return md5(
            $exception::class
            . $exception->getMessage()
            . $exception->getFile()
            . $exception->getLine()
            . $exception->getTraceAsString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionToArray(Throwable $exception): array
    {
        $data = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code'    => $exception->getCode(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
            'trace'   => $this->debug ? $exception->getTraceAsString() : '[hidden]',
        ];

        if ($exception->getPrevious()) {
            $data['previous'] = $this->exceptionToArray($exception->getPrevious());
        }

        return $data;
    }
}
