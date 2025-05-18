<?php
declare(strict_types=1);

namespace MonkeysLegion\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareDispatcher implements RequestHandlerInterface
{
    /** @var MiddlewareInterface[] */
    private array $middlewareQueue;

    private RequestHandlerInterface $finalHandler;

    /**
     * @param MiddlewareInterface[]       $middlewareQueue
     * @param RequestHandlerInterface     $finalHandler
     */
    public function __construct(array $middlewareQueue, RequestHandlerInterface $finalHandler)
    {
        $this->middlewareQueue = $middlewareQueue;
        $this->finalHandler   = $finalHandler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (empty($this->middlewareQueue)) {
            return $this->finalHandler->handle($request);
        }

        // pull off the next middleware
        $middleware = array_shift($this->middlewareQueue);

        // invoke it, passing ourselves (with the remaining queue) as handler
        return $middleware->process($request, $this);
    }
}