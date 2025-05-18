<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    /** very naive – per-process counter */
    private int   $max   = 60;          // 60 req / minute
    private array $hits  = [];          // ip ⇒ [t0, count]

    public function __construct(private ResponseFactoryInterface $factory) {}

    public function process(ServerRequestInterface $request,
                            RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        [$t0, $count] = $this->hits[$ip] ?? [time(), 0];

        if (time() - $t0 > 60) {             // slide window
            $this->hits[$ip] = [time(), 0];
        } elseif ($count >= $this->max) {
            $res = $this->factory->createResponse(429);
            $res->getBody()->write('Rate limit exceeded');
            return $res;
        }

        $this->hits[$ip][1]++;

        return $handler->handle($request);
    }
}