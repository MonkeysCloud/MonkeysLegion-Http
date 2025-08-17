<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error;

class HtmlErrorRenderer
{
    public function render(\Throwable $e): string
    {
        $errorType = get_class($e);
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $line = $e->getLine();
        $trace = $this->formatStackTrace($e);
        $context = $this->getFileContext($e->getFile(), $e->getLine());

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
        :root {
            --primary: #0f172a;
            --secondary: #1e293b;
            --accent: #f59e0b;
            --surface: #ffffff;
            --surface-secondary: #f8fafc;
            --surface-tertiary: #f1f5f9;
            --error: #dc2626;
            --error-subtle: #fef2f2;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --border-subtle: #f1f5f9;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            font-feature-settings: 'cv02', 'cv03', 'cv04', 'cv11';
        }

        .error-container {
            background: var(--surface);
            border-radius: 16px;
            box-shadow: var(--shadow-xl);
            max-width: 1200px;
            width: 100%;
            overflow: hidden;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .error-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 3rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .error-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(245, 158, 11, 0.1) 0%, transparent 50%);
        }

        .error-header-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .error-icon {
            width: 64px;
            height: 64px;
            background: var(--accent);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }

        .error-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            line-height: 1.2;
            flex: 1;
        }

        .error-type {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--accent);
            background: rgba(245, 158, 11, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            display: inline-block;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .error-content {
            padding: 2rem;
        }

        .error-details {
            background: var(--error-subtle);
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .error-details::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--error);
            border-radius: 2px 0 0 2px;
        }

        .error-location {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 400;
        }

        .error-location strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 20px;
            background: var(--accent);
            border-radius: 2px;
        }

        .code-context {
            background: var(--primary);
            border-radius: 12px;
            padding: 1.5rem;
            overflow-x: auto;
            border: 1px solid var(--secondary);
        }

        .code-line {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
            font-size: 0.875rem;
            line-height: 1.7;
            color: #94a3b8;
            display: flex;
            padding: 0.25rem 0;
        }

        .line-number {
            color: #64748b;
            width: 4ch;
            text-align: right;
            margin-right: 1.5rem;
            user-select: none;
            flex-shrink: 0;
        }

        .line-content {
            flex: 1;
            overflow-x: auto;
        }

        .code-line.highlight {
            background: rgba(220, 38, 38, 0.1);
            border-left: 3px solid var(--error);
            padding-left: 0.75rem;
            margin-left: -0.75rem;
            color: #ffffff;
            border-radius: 0 6px 6px 0;
        }

        .code-line.highlight .line-number {
            color: var(--error);
            font-weight: 600;
        }

        .stack-trace {
            background: var(--surface-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            max-height: 500px;
            overflow-y: auto;
        }

        .stack-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-subtle);
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
            font-size: 0.8125rem;
            color: var(--text-secondary);
            transition: background-color 0.15s ease;
        }

        .stack-item:last-child {
            border-bottom: none;
        }

        .stack-item:hover {
            background: var(--surface-tertiary);
        }

        .stack-file {
            color: var(--text-primary);
            font-weight: 500;
        }

        .stack-function {
            color: var(--accent);
            font-weight: 600;
            margin-top: 0.25rem;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
            font-style: italic;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .error-container {
                border-radius: 12px;
            }
            
            .error-header {
                padding: 2rem 1.5rem;
            }
            
            .error-content {
                padding: 1.5rem;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .code-context {
                padding: 1rem;
            }
            
            .stack-item {
                padding: 0.75rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .error-header {
                padding: 1.5rem 1rem;
            }
            
            .error-content {
                padding: 1rem;
            }
            
            .error-title {
                font-size: 1.25rem;
            }
            
            .line-number {
                width: 3ch;
                margin-right: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class=\"error-container\">
        <div class=\"error-header\">
            <div class=\"error-header-content\">
                <div class=\"error-icon\">⚡</div>
                <div>
                    <h1 class=\"error-title\">{$message}</h1>
                    <div class=\"error-type\">{$errorType}</div>
                </div>
            </div>
        </div>
        
        <div class=\"error-content\">
            <div class=\"error-details\">
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
            </div>
        </div>
    </div>
</body>
</html>";
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
