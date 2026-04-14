<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Error\Renderer;

use MonkeysLegion\Core\Error\Renderer\ErrorRendererInterface;
use Throwable;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * JSON error renderer for API responses.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class JsonErrorRenderer implements ErrorRendererInterface
{
    public function render(Throwable $exception, bool $debug = false): string
    {
        $data = [
            'status'    => 'error',
            'message'   => $debug ? $exception->getMessage() : 'An unexpected error occurred.',
            'timestamp' => date('c'),
        ];

        if ($debug) {
            $data['debug'] = [
                'type'  => $exception::class,
                'file'  => $exception->getFile(),
                'line'  => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ];
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function getContentType(): string
    {
        return 'application/json';
    }
}
