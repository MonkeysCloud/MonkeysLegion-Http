<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Error\Renderer;

interface ErrorRendererInterface
{
    public function render(\Throwable $exception, bool $debug = false): string;
    public function getContentType(): string;
}
