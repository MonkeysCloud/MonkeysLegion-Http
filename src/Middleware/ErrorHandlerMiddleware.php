<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use GuzzleHttp\Psr7\Response;
use MonkeysLegion\Http\Error\ErrorHandler;
use MonkeysLegion\Http\Error\HtmlErrorRenderer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private ErrorHandler $errorHandler;

    public function __construct()
    {
        $this->registerErrorHandler();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // Let everything run—controllers, other middleware, etc.
            return $handler->handle($request);
        } catch (\Throwable $e) {
            return $this->renderErrorResponse($e);
        }
    }

    private function renderErrorResponse(\Throwable $e): ResponseInterface
    {
        $errHandler = new ErrorHandler();

        if (PHP_SAPI === 'cli') {
            // $errHandler->useRenderer(new CliErrorRenderer());
        } else {
            $errHandler->useRenderer(new HtmlErrorRenderer());
        }

        $content = $errHandler->render($e);

        // Wrap that into a Response
        $response = new Response(
            500,
            ['Content-Type' => $errHandler->getContentType()],
            $content
        );

        return $response;
    }

    private function registerErrorHandler(): void
    {
        $this->errorHandler = new ErrorHandler();

        if (PHP_SAPI === 'cli') {
            // $this->errorHandler->useRenderer(new CliErrorRenderer()); // TODO
        } else {
            $this->errorHandler->useRenderer(new HtmlErrorRenderer());
        }

        $this->errorHandler->register();
    }
}
