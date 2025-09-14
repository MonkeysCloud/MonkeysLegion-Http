<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error\Renderer;

class JsonErrorRenderer implements ErrorRendererInterface
{
    public function render(\Throwable $exception, bool $debug = false): string
    {
        $data = [
            'error' => true,
            'message' => $debug ? $exception->getMessage() : 'An unexpected error occurred.',
            'timestamp' => date('c')
        ];

        if ($debug) {
            $data['debug'] = [
                'type' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace()
            ];
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function getContentType(): string
    {
        return 'application/json';
    }
}
