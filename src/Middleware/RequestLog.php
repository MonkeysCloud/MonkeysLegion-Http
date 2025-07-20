<?php

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Psr\Log\LoggerInterface;

final class RequestLog implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger) {}

    public function process(ServerRequestInterface $req, RequestHandlerInterface $h): ResponseInterface
    {
        $this->logger->debug('➡️ {method} {uri}', [
            'method' => $req->getMethod(),
            'uri'    => (string) $req->getUri(),
        ]);
        return $h->handle($req);
    }
}