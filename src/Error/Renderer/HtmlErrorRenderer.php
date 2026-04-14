<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error\Renderer;

use MonkeysLegion\Core\Error\Renderer\ErrorRendererInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Styled HTML error renderer with support for nested exception chains.
 *
 * v2 improvements:
 *  • final class
 *  • Debug-aware detailed output (trace + code context)
 *  • Production-safe generic message
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class HtmlErrorRenderer implements ErrorRendererInterface
{
    public function render(\Throwable $exception, bool $debug = false): string
    {
        $exceptions = [];
        $current = $exception;
        while ($current !== null) {
            $exceptions[] = $current;
            $current = $current->getPrevious();
        }

        $exceptionId = $_GET['exception_index'] ?? 0;
        $exceptionId = (int) $exceptionId;
        if (!isset($exceptions[$exceptionId])) {
            $exceptionId = 0;
        }

        $activeException = $exceptions[$exceptionId];
        $errorType = get_class($activeException);
        $message = htmlspecialchars($activeException->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($activeException->getFile(), ENT_QUOTES, 'UTF-8');
        $line = $activeException->getLine();
        $trace = $debug ? $this->formatStackTrace($activeException) : '';
        $context = $debug ? $this->getFileContext($activeException->getFile(), $activeException->getLine()) : '';

        $chainHtml = '';
        if ($debug && count($exceptions) > 1) {
            $chainHtml = '<div class="exception-chain"><div class="chain-label">Exception Chain:</div><div class="chain-items">';
            foreach ($exceptions as $index => $exc) {
                $isActive = $index === $exceptionId ? 'active' : '';
                $shortName = (new \ReflectionClass($exc))->getShortName();
                $chainHtml .= "<button class=\"chain-item {$isActive}\" onclick=\"switchException({$index})\">";
                $chainHtml .= "<span class=\"chain-index\">#" . (count($exceptions) - $index) . "</span>";
                $chainHtml .= "<span class=\"chain-type\">{$shortName}</span></button>";
            }
            $chainHtml .= '</div></div>';
        }

        $details = $debug
            ? "<div class=\"error-details\">
                   <div class=\"error-location\">
                       <strong>File:</strong> {$file}<br>
                       <strong>Line:</strong> {$line}
                   </div>
               </div>
               {$context}
               <div class=\"section\">
                   <div class=\"section-title\">Stack Trace</div>
                   <div class=\"stack-trace\" id=\"stackTrace\">{$trace}</div>
               </div>"
            : "<div class=\"error-details\"><div class=\"error-location\">
                   An internal server error occurred. Please try again later or contact support if the problem persists.
               </div></div>";

        ob_start();
        require __DIR__ . '/../css/error.php';
        $css = ob_get_clean();

        $scripts = $debug
            ? "<script>
                    function switchException(index) {
                        const url = new URL(window.location.href);
                        url.searchParams.set('exception_index', index);
                        window.location.href = url.toString();
                    }
               </script>"
            : '';

        return "<!DOCTYPE html>
                <html lang=\"en\">
                <head>
                    <meta charset=\"UTF-8\">
                    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                    <title>Error - {$errorType}</title>
                    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">
                    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>
                    <link href=\"https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap\" rel=\"stylesheet\">
                    <style>{$css}</style>
                </head>
                <body>
                    <div class=\"error-container\">
                        <div class=\"error-header\">
                            <div class=\"error-header-content\">
                                <div class=\"error-icon\">⚡</div>
                                <div class=\"error-title-container\">
                                    <h1 class=\"error-title\">{$message}</h1>
                                    <div class=\"error-type\">{$errorType}</div>
                                    {$chainHtml}
                                </div>
                            </div>
                        </div>
                        <div class=\"error-content\">{$details}</div>
                    </div>
                    {$scripts}
                </body>
                </html>";
    }

    public function getContentType(): string
    {
        return 'text/html';
    }

    private function formatStackTrace(\Throwable $exception): string
    {
        $trace = '';
        $frames = $exception->getTrace();
        if (empty($frames)) {
            return '<div class="empty-state">No stack trace available</div>';
        }

        foreach ($frames as $index => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;
            $function = $frame['function'] ?? '';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $location = htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . ':' . $line;
            $call = htmlspecialchars($class . $type . $function, ENT_QUOTES, 'UTF-8');

            $trace .= "<div class=\"stack-item\">";
            $trace .= "<div class=\"stack-number\">#{$index}</div>";
            $trace .= "<div class=\"stack-info\"><div class=\"stack-function\">{$call}()</div><div class=\"stack-file\">{$location}</div></div>";
            $trace .= "</div>";
        }

        return $trace;
    }

    private function getFileContext(string $file, int $errorLine): string
    {
        if (!is_file($file) || !is_readable($file)) {
            return '';
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return '';
        }

        $start = max(0, $errorLine - 6);
        $end = min(count($lines), $errorLine + 5);

        $context = '<div class="section"><div class="section-title">Code Context</div><div class="code-context">';
        for ($i = $start; $i < $end; $i++) {
            $lineNumber = $i + 1;
            $codeLine = htmlspecialchars($lines[$i], ENT_QUOTES, 'UTF-8');
            $isErrorLine = $lineNumber === $errorLine;
            $class = $isErrorLine ? 'code-line highlight' : 'code-line';
            $context .= "<div class=\"{$class}\"><span class=\"line-number\">{$lineNumber}</span><span class=\"line-content\">{$codeLine}</span></div>";
        }
        $context .= '</div></div>';

        return $context;
    }
}
