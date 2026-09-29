<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;

/**
 * MonKeysLegion Framework — HTTP Package
 *
 * Adds deprecation headers to responses for deprecated API endpoints.
 *
 * Reads the #[Deprecated] attribute from the controller method and adds:
 *   - Deprecation: true (or a date if specified)
 *   - Sunset: <date> (if provided)
 *   - Link: <uri>; rel="deprecation" (if provided)
 *
 * Per RFC 8594 (Sunset Header) and the IETF Deprecation Header draft.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class DeprecationMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        // Check if the route handler has a #[Deprecated] attribute
        $handler_attr = $request->getAttribute('handler');
        if (!is_array($handler_attr) || count($handler_attr) < 2) {
            return $response;
        }

        [$class, $method] = $handler_attr;

        try {
            $refMethod = new ReflectionMethod($class, $method);
        } catch (\ReflectionException) {
            return $response;
        }

        $deprecatedAttrs = $refMethod->getAttributes(
            \MonkeysLegion\Router\Attributes\Deprecated::class
        );

        if (empty($deprecatedAttrs)) {
            return $response;
        }

        /** @var \MonkeysLegion\Router\Attributes\Deprecated $deprecated */
        $deprecated = $deprecatedAttrs[0]->newInstance();

        // Deprecation header (RFC draft)
        $deprecationValue = $deprecated->since !== ''
            ? $deprecated->since
            : 'true';
        $response = $response->withHeader('Deprecation', $deprecationValue);

        // Sunset header (RFC 8594)
        if ($deprecated->sunset !== '') {
            $response = $response->withHeader('Sunset', $deprecated->sunset);
        }

        // Link header with rel="deprecation"
        if ($deprecated->link !== '') {
            $response = $response->withHeader(
                'Link',
                '<' . $deprecated->link . '>; rel="deprecation"'
            );
        }

        return $response;
    }
}
