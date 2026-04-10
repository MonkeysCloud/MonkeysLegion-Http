<?php
declare(strict_types=1);

namespace MonkeysLegion\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Lightweight middleware dispatcher using an indexed cursor.
 *
 * Instead of array_shift (O(n) re-index on each call), uses a cursor
 * index for O(1) dispatch. The dispatcher instance is single-use.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class MiddlewareDispatcher implements RequestHandlerInterface
{
    private int $cursor = 0;

    /**
     * @param list<MiddlewareInterface>   $middlewareStack
     * @param RequestHandlerInterface     $finalHandler
     */
    public function __construct(
        private readonly array                   $middlewareStack,
        private readonly RequestHandlerInterface $finalHandler,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->middlewareStack[$this->cursor])) {
            return $this->finalHandler->handle($request);
        }

        $middleware = $this->middlewareStack[$this->cursor];
        $this->cursor++;

        return $middleware->process($request, $this);
    }
}