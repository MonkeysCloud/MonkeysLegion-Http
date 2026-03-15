<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error\Renderer;

use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Loader;
use MonkeysLegion\Template\Renderer;

class HtmlErrorRenderer implements ErrorRendererInterface
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
        $css = file_get_contents(__DIR__ . '/../css/error.php');

        return $this->renderer->render('errors::exception', [
            'e' => $e,
            'debug' => $debug,
            'timestamp' => date('Y-m-d H:i:s'),
            'css' => $css,
            'trace' => $debug ? $this->getTraceData($e) : [],
            'context' => $debug ? $this->getFileContextLines($e->getFile(), $e->getLine()) : [],
            'request' => $this->request,
            'session' => $this->session
        ]);
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
