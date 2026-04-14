<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Error\Renderer;

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
            $data['debug'] = $this->exceptionToArray($exception);
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function exceptionToArray(\Throwable $e): array
    {
        $data = [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTrace()
        ];

        if ($e->getPrevious()) {
            $data['previous'] = $this->exceptionToArray($e->getPrevious());
        }

        return $data;
    }

    public function getContentType(): string
    {
        return 'application/json';
    }
}
