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
        $timestamp = date('Y-m-d H:i:s');
        $details = $debug
            ? "<div class=\"error-meta\">
                        <div class=\"meta-item\">
                            <div class=\"meta-label\">Timestamp</div>
                            <div class=\"meta-value\">{$timestamp}</div>
                        </div>
                        <div class=\"meta-item\">
                            <div class=\"meta-label\">Error Code</div>
                            <div class=\"meta-value\">{$e->getCode()}</div>
                        </div>
                    </div>
                    <div class=\"error-details\">
                        <div class=\"detail-header\">
                            <svg class=\"detail-icon\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">
                                <path d=\"M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z\"></path>
                            </svg>
                            <span>Error Location</span>
                        </div>
                        <div class=\"error-location\">
                            <div class=\"location-item\">
                                <span class=\"location-label\">File:</span>
                                <span class=\"location-path\">{$file}</span>
                            </div>
                        <div class=\"location-item\">
                            <span class=\"location-label\">Line:</span>
                            <span class=\"location-line\">{$line}</span>
                        </div>
                    </div>
                </div>
                {$context}
                <div class=\"section\">
                    <div class=\"section-header\">
                        <div class=\"section-title\">
                        <svg class=\"section-icon\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">
                            <path d=\"M4 6h16M4 12h16M4 18h16\"></path>
                        </svg>
                        Stack Trace
                        </div>
                        <button class=\"toggle-btn\" onclick=\"toggleStackTrace()\">
                            <span class=\"toggle-text\">Collapse</span>
                            <svg class=\"toggle-icon\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">
                                <path d=\"M19 9l-7 7-7-7\"></path>
                            </svg>
                        </button>
                    </div>
                    <div class=\"stack-trace\" id=\"stackTrace\">
                        {$trace}
                    </div>
                </div>"
            : "<div class=\"error-message-container\">
                    <div class=\"error-illustration\">
                        <svg viewBox=\"0 0 200 200\" xmlns=\"http://www.w3.org/2000/svg\">
                            <circle cx=\"100\" cy=\"100\" r=\"80\" fill=\"#fee2e2\" opacity=\"0.3\"/>
                            <circle cx=\"100\" cy=\"100\" r=\"60\" fill=\"#fecaca\" opacity=\"0.4\"/>
                            <path d=\"M100 60 L100 110\" stroke=\"#b91c1c\" stroke-width=\"8\" stroke-linecap=\"round\"/>
                            <circle cx=\"100\" cy=\"130\" r=\"6\" fill=\"#b91c1c\"/>
                        </svg>
                    </div>
                    <h2 class=\"error-message-title\">Oops! Something went wrong</h2>
                    <p class=\"error-message-text\">An internal server error occurred. Our team has been notified and we're working to fix the issue.</p>
                    <div class=\"error-actions\">
                        <button class=\"action-btn primary\" onclick=\"window.location.reload()\">
                            <svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">
                                <path d=\"M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15\"></path>
                            </svg>
                            Try Again
                        </button>
                        <button class=\"action-btn secondary\" onclick=\"window.history.back()\">
                            <svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">
                                <path d=\"M10 19l-7-7m0 0l7-7m-7 7h18\"></path>
                            </svg>
                            Go Back
                        </button>
                    </div>
                    <div class=\"error-support\">
                        <p>If this problem persists, please <a href=\"mailto:support@monkeyslegion.com\">contact support</a></p>
                    </div>
                </div>";

        ob_start();
        require __DIR__ . '/../css/error.php';
        $css = ob_get_clean();
        $scripts = $debug ? "<script>
                                function toggleStackTrace() {
                                    const trace = document.getElementById('stackTrace');
                                    const btn = document.querySelector('.toggle-btn');
                                    const text = btn.querySelector('.toggle-text');
                                    const icon = btn.querySelector('.toggle-icon');
                                    if (trace.style.display === 'none') {
                                        trace.style.display = 'block';
                                        text.textContent = 'Collapse';
                                        icon.style.transform = 'rotate(0deg)';
                                    } else {
                                        trace.style.display = 'none';
                                        text.textContent = 'Expand';
                                        icon.style.transform = 'rotate(-90deg)';
                                    }
                                }
                                // Copy code functionality
                                document.addEventListener('click', function(e) {
                                    if (e.target.classList.contains('copy-btn')) {
                                        const code = e.target.closest('.code-line').querySelector('.line-content').textContent;
                                        navigator.clipboard.writeText(code).then(() => {
                                            e.target.textContent = '✓';
                                            setTimeout(() => e.target.textContent = '📋', 2000);
                                        });
                                    }
                                });
                            </script>" : "";

        return "<!DOCTYPE html>
                <html lang=\"en\">
                    <head>
                    <meta charset=\"UTF-8\">
                    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                    <title>Error - {$errorType}</title>
                    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">
                    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>
                    <link href=\"https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap\" rel=\"stylesheet\">
                    <link href=\"https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap\" rel=\"stylesheet\">
                    <style>
                      {$css}
                    </style>
                    </head>
                <body>
                    <div class=\"error-container\">
                        <div class=\"error-header\">
                            <div class=\"error-header-content\">
                                <div class=\"error-icon-wrapper\">
                                    <div class=\"error-icon\">⚡</div>
                                    <div class=\"icon-glow\"></div>
                                </div>
                                <div class=\"error-title-container\">
                                    <h1 class=\"error-title\">{$message}</h1>
                                    <div class=\"error-type\">{$errorType}</div>
                                </div>
                            </div>
                        </div>
                        <div class=\"error-content\">
                            {$details}
                        </div>
                        <div class=\"error-footer\">
                            <div class=\"footer-content\">
                                <div class=\"footer-logo\">
                                    <span class=\"logo-text\">MonkeysLegion</span>
                                    <span class=\"logo-version\">Framework</span>
                                </div>
                                <div class=\"footer-links\">
                                    <a href=\"https://monkeyslegion.com/docs\" target=\"_blank\">Documentation</a>
                                    <a href=\"https://github.com/MonkeysCloud/MonkeysLegion-Skeleton\" target=\"_blank\">GitHub</a>
                                    <a href=\"mailto:support@monkeyslegion.com\">Support</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    {$scripts}
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
            $trace .= "<div class=\"stack-number\">#" . $index . "</div>";
            $trace .= "<div class=\"stack-info\">";
            $trace .= "<div class=\"stack-function\">{$call}()</div>";
            $trace .= "<div class=\"stack-file\">{$location}</div>";
            $trace .= "</div>";
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
        $context .= '<div class="section-header">';
        $context .= '<div class="section-title">';
        $context .= '<svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $context .= '<path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>';
        $context .= '</svg>';
        $context .= 'Code Context';
        $context .= '</div>';
        $context .= '</div>';
        $context .= '<div class="code-context">';

        for ($i = $start; $i < $end; $i++) {
            $lineNumber = $i + 1;
            $codeLine = htmlspecialchars($lines[$i], ENT_QUOTES, 'UTF-8');
            $isErrorLine = $lineNumber === $errorLine;
            $class = $isErrorLine ? 'code-line highlight' : 'code-line';

            $context .= "<div class=\"{$class}\">";
            $context .= "<span class=\"line-number\">{$lineNumber}</span>";
            $context .= "<span class=\"line-content\">{$codeLine}</span>";
            if ($isErrorLine) {
                $context .= "<span class=\"error-marker\">← Error occurred here</span>";
            }
            $context .= "</div>";
        }

        $context .= '</div>';
        $context .= '</div>';

        return $context;
    }
}
