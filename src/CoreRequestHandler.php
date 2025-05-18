<?php
declare(strict_types=1);

namespace MonkeysLegion\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Core PSR-15 dispatcher.
 *
 *  • Allows you to “pipe” middlewares (à-la Express / Laminas Stratigility).
 *  • After the middleware stack is exhausted, it calls the
 *    final application handler (usually your router).
 */
final class CoreRequestHandler implements RequestHandlerInterface
{
    /** @var MiddlewareInterface[] */
    private array $pipeline = [];

    public function __construct(
        private readonly RequestHandlerInterface  $appHandler,        // final handler (router / controller resolver)
        private readonly ResponseFactoryInterface $responseFactory    // PSR-17 factory to create responses
    ) {}

    /**
     * Push a middleware to the end of the stack.
     */
    public function pipe(MiddlewareInterface $middleware): void
    {
        $this->pipeline[] = $middleware;
    }

    /**
     * Entry-point required by PSR-15.
     *
     * Middleware chain is reduced to nested anonymous RequestHandlers
     * so that `$next->handle()` inside a middleware calls the remainder
     * of the stack.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Build the callable chain *backwards* (last-in-first-out)
        $dispatcher = array_reduce(
            array_reverse($this->pipeline),
            static function (RequestHandlerInterface $next, MiddlewareInterface $mw): RequestHandlerInterface {
                // anonymous RequestHandler decorating the next link
                return new class ($mw, $next) implements RequestHandlerInterface {
                    public function __construct(
                        private MiddlewareInterface   $mw,
                        private RequestHandlerInterface $next
                    ) {}

                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return $this->mw->process($request, $this->next);
                    }
                };
            },
            $this->appHandler             // the innermost / final handler
        );

        try {
            return $dispatcher->handle($request);
        } catch (\Throwable $e) {
            // very plain fallback error handler – replace with your own
            $response = $this->responseFactory->createResponse(500);
            $response->getBody()->write('Internal Server Error');
            // You might log $e here …
            return $response;
        }
    }
}