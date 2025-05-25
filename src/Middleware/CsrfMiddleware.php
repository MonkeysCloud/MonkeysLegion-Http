<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * CSRF protection middleware.
 * Generates a token on safe methods and validates it on state-changing requests.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Start session if not started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $method = strtoupper($request->getMethod());

        // On GET/HEAD/OPTIONS ensure token exists
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $handler->handle($request);
        }

        // On POST/PUT/PATCH/DELETE validate token
        $parsed = $request->getParsedBody();
        $token = null;
        if (is_array($parsed) && isset($parsed['_csrf'])) {
            $token = $parsed['_csrf'];
        } else {
            $token = $request->getHeaderLine('X-CSRF-Token');
        }

        $valid = isset($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);

        if (! $valid) {
            $response = $this->responseFactory->createResponse(400, 'Invalid CSRF token');
            $response->getBody()->write('Invalid CSRF token');
            return $response;
        }

        return $handler->handle($request);
    }
}
