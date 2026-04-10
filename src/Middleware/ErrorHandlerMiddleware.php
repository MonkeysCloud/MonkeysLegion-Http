<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use MonkeysLegion\Http\Error\Renderer\ErrorRendererInterface;
use MonkeysLegion\Http\Error\Renderer\JsonErrorRenderer;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Catches exceptions thrown by downstream middleware/handlers
 * and returns a proper PSR-7 error response.
 *
 * In debug mode, the full exception details are rendered.
 * In production mode, a generic error message is returned.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private ErrorRendererInterface $renderer;

    /**
     * @param bool                       $debug    Show exception details in response.
     * @param ErrorRendererInterface|null $renderer Custom error renderer.
     * @param LoggerInterface|null       $logger   PSR-3 logger for error logging.
     */
    public function __construct(
        private readonly bool $debug = false,
        ?ErrorRendererInterface $renderer = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->renderer = $renderer ?? new JsonErrorRenderer();
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $this->logException($e, $request);

            $statusCode = $this->resolveStatusCode($e);
            $body = $this->renderer->render($e, $this->debug);

            return new Response(
                Stream::createFromString($body),
                $statusCode,
                [
                    'Content-Type' => $this->renderer->getContentType() . '; charset=UTF-8',
                ],
            );
        }
    }

    private function resolveStatusCode(\Throwable $e): int
    {
        $code = $e->getCode();
        if (is_int($code) && $code >= 400 && $code < 600) {
            return $code;
        }
        return 500;
    }

    private function logException(\Throwable $e, ServerRequestInterface $request): void
    {
        $context = [
            'exception' => $e::class,
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'method'    => $request->getMethod(),
            'uri'       => (string) $request->getUri(),
            'request_id' => $request->getAttribute('request_id'),
        ];

        if ($this->logger !== null) {
            $this->logger->error($e->getMessage(), $context);
        } else {
            error_log(sprintf(
                'Exception [%s]: %s in %s:%d',
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            ));
        }
    }
}
