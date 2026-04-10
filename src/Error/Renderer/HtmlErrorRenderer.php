<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error\Renderer;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * HTML error renderer with styled debug page.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class HtmlErrorRenderer implements ErrorRendererInterface
{
    public function __construct(
        private Loader $loader,
        private Renderer $renderer,
        private ?ServerRequest $request = null,
        private ?SessionManager $session = null
    ) {
        try {
            if (!$this->request) {
                $this->request = request();
            }
        } catch (\Throwable) {
            $this->request = null;
        }
        $this->loader->addNamespace('errors', __DIR__ . '/../views');
    }

    public function render(\Throwable $e, bool $debug = false): string
    {
        $errorType = $e::class;
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

    private function getTraceData(\Throwable $e): array
    {
        $frames = $e->getTrace();

        if (empty($frames)) {
            return [];
        }

        $trace = [];
        foreach ($frames as $index => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;
            $function = $frame['function'] ?? '';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';

            $trace[] = [
                'index' => $index,
                'call' => $class . $type . $function,
                'location' => $file . ':' . $line
            ];
        }

        return $trace;
    }

    private function getFileContextLines(string $file, int $errorLine): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $start = max(0, $errorLine - 6);
        $end = min(count($lines), $errorLine + 5);

        $context = [];
        for ($i = $start; $i < $end; $i++) {
            $context[] = [
                'number' => $i + 1,
                'content' => $lines[$i],
                'isError' => ($i + 1) === $errorLine
            ];
        }

        return $context;
    }
}
