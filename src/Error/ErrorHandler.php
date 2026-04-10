<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Error;

use ErrorException;
use MonkeysLegion\Http\Error\Renderer\ErrorRendererInterface;
use MonkeysLegion\Http\Error\Renderer\JsonErrorRenderer;
use Psr\Log\LoggerInterface;
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
     * Set the error renderer.
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
            echo $this->renderer->render($exception, $this->debug);
        } catch (Throwable) {
            $this->emergencyResponse('Error rendering failed', $exception);
        }

        if (PHP_SAPI !== 'cli' && !$this->debug) {
            exit(1);
        }
    }

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
        return [
            'class'   => $exception::class,
            'message' => $exception->getMessage(),
            'code'    => $exception->getCode(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
            'trace'   => $this->debug ? $exception->getTraceAsString() : '[hidden]',
        ];
    }
}
