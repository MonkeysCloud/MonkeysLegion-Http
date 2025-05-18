<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request,
                            RequestHandlerInterface $handler): ResponseInterface
    {
        $start = microtime(true);
        $response = $handler->handle($request);
        $t = number_format((microtime(true) - $start)*1000, 2);
        error_log(sprintf('[%s] %s %s – %d (%sms)',
            date('c'),
            $request->getMethod(),
            (string) $request->getUri(),
            $response->getStatusCode(),
            $t
        ));
        return $response;
    }
}