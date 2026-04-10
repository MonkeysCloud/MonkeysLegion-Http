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
 * Core PSR-15 request handler with middleware pipeline.
 *
 * Allows piping middleware à-la Express / Laminas Stratigility.
 * After the pipeline is exhausted, delegates to the final app handler
 * (usually the router / controller resolver).
 *
 * v2 optimization: pre-builds the handler chain on pipe() instead
 * of rebuilding via array_reduce on every handle() call.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CoreRequestHandler implements RequestHandlerInterface
{
    /** @var list<MiddlewareInterface> */
    private array $pipeline = [];

    /** Pre-built handler chain (rebuilt when pipeline changes). */
    private ?RequestHandlerInterface $chain = null;

    /** Once locked, no more middleware can be piped. */
    private bool $locked = false;

    public function __construct(
        private readonly RequestHandlerInterface $appHandler,
    ) {}

    /**
     * Push a middleware to the end of the stack.
     *
     * @throws \RuntimeException If the pipeline has been locked.
     */
    public function pipe(MiddlewareInterface $middleware): void
    {
        if ($this->locked) {
            throw new \RuntimeException('Cannot pipe middleware after the pipeline has been locked.');
        }

        $this->pipeline[] = $middleware;
        $this->chain      = null; // invalidate cache
    }

    /**
     * Lock the pipeline — no more middleware can be added.
     * Useful after boot to prevent accidental mutation.
     */
    public function lock(): void
    {
        $this->locked = true;
    }

    /**
     * PSR-15 entry-point.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->chain === null) {
            $this->chain = $this->buildChain();
        }

        return $this->chain->handle($request);
    }

    /**
     * Build the middleware chain backwards (last-added = outermost).
     */
    private function buildChain(): RequestHandlerInterface
    {
        $handler = $this->appHandler;

        foreach (array_reverse($this->pipeline) as $mw) {
            $handler = new class($mw, $handler) implements RequestHandlerInterface {
                public function __construct(
                    private readonly MiddlewareInterface    $middleware,
                    private readonly RequestHandlerInterface $next,
                ) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            };
        }

        return $handler;
    }
}
