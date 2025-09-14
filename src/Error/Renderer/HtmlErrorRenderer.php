<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error\Renderer;

class HtmlErrorRenderer implements ErrorRendererInterface
{
    public function render(\Throwable $e, bool $debug = false): string
    {
        $errorType = get_class($e);
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $line = $e->getLine();
        $trace = $debug ? $this->formatStackTrace($e) : '';
        $context = $debug ? $this->getFileContext($e->getFile(), $e->getLine()) : '';

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
                  <div class=\"stack-trace\">
                      {$trace}
                  </div>
              </div>"
            : "<div class=\"error-details\">
                  <div class=\"error-location\">
                      An internal server error occurred. Please try again later or contact support if the problem persists.
                  </div>
               </div>";

        ob_start();
        require __DIR__ . '/../css/error.php';
        $css = ob_get_clean();

        return "<!DOCTYPE html>
                <html lang=\"en\">
                <head>
                    <meta charset=\"UTF-8\">
                    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                    <title>Error - {$errorType}</title>
                    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">
                    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>
                    <link href=\"https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap\" rel=\"stylesheet\">
                    <style>
                        {$css}
                    </style>
                </head>
                <body>
                    <div class=\"error-container\">
                        <div class=\"error-header\">
                            <div class=\"error-header-content\">
                                <div class=\"error-icon\">⚡</div>
                                <div class=\"error-title-container\">
                                    <h1 class=\"error-title\">{$message}</h1>
                                    <div class=\"error-type\">{$errorType}</div>
                                </div>
                            </div>
                        </div>
                        <div class=\"error-content\">
                            {$details}
                        </div>
                    </div>
                </body>
                </html>
            ";
    }

    public function getContentType(): string
    {
        return 'text/html';
    }

    private function formatStackTrace(\Throwable $e): string
    {
        $trace = '';
        $frames = $e->getTrace();

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
            $trace .= "<div class=\"stack-file\">#{$index} {$location}</div>";
            $trace .= "<div class=\"stack-function\">{$call}()</div>";
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

        $context = '<div class="section">';
        $context .= '<div class="section-title">Code Context</div>';
        $context .= '<div class="code-context">';

        for ($i = $start; $i < $end; $i++) {
            $lineNumber = $i + 1;
            $codeLine = htmlspecialchars($lines[$i], ENT_QUOTES, 'UTF-8');
            $isErrorLine = $lineNumber === $errorLine;
            $class = $isErrorLine ? 'code-line highlight' : 'code-line';

            $context .= "<div class=\"{$class}\">";
            $context .= "<span class=\"line-number\">{$lineNumber}</span>";
            $context .= "<span class=\"line-content\">{$codeLine}</span>";
            $context .= "</div>";
        }

        $context .= '</div>';
        $context .= '</div>';

        return $context;
    }
}
