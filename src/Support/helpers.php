<?php
declare(strict_types=1);

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Global helper functions for common response patterns.
 * All functions are guarded with function_exists() to prevent redeclaration.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */

use MonkeysLegion\Http\Message\JsonResponse;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use Psr\Http\Message\ResponseInterface;

if (!function_exists('response')) {
    /**
     * Create a plain-text response.
     */
    function response(string $data = '', int $status = 200, array $headers = []): ResponseInterface
    {
        return new Response(Stream::createFromString($data), $status, $headers);
    }
}

if (!function_exists('json')) {
    /**
     * Create a JSON response.
     *
     * @throws JsonException
     */
    function json(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }
}

if (!function_exists('jsonSuccess')) {
    /**
     * Create a JSON success envelope response.
     *
     * @throws JsonException
     */
    function jsonSuccess(mixed $data, ?string $message = null, int $status = 200): ResponseInterface
    {
        return (new JsonResponse($data, $status))->withEnvelope($message);
    }
}

if (!function_exists('jsonError')) {
    /**
     * Create a JSON error response.
     */
    function jsonError(string $message, int $status = 400): ResponseInterface
    {
        $payload = json_encode([
            'status'  => 'error',
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES);

        return new Response(
            Stream::createFromString($payload),
            $status,
            ['Content-Type' => 'application/json'],
        );
    }
}

if (!function_exists('ok')) {
    function ok(string $data = 'OK'): ResponseInterface
    {
        return response($data, 200);
    }
}

if (!function_exists('created')) {
    function created(string $data = 'Created'): ResponseInterface
    {
        return response($data, 201);
    }
}

if (!function_exists('accepted')) {
    function accepted(string $data = 'Accepted'): ResponseInterface
    {
        return response($data, 202);
    }
}

if (!function_exists('noContent')) {
    function noContent(): ResponseInterface
    {
        return Response::noContent();
    }
}

if (!function_exists('badRequest')) {
    function badRequest(string $data = 'Bad Request'): ResponseInterface
    {
        return response($data, 400);
    }
}

if (!function_exists('unauthorized')) {
    function unauthorized(string $data = 'Unauthorized'): ResponseInterface
    {
        return response($data, 401);
    }
}

if (!function_exists('forbidden')) {
    function forbidden(string $data = 'Forbidden'): ResponseInterface
    {
        return response($data, 403);
    }
}

if (!function_exists('notFound')) {
    function notFound(string $data = 'Not Found'): ResponseInterface
    {
        return response($data, 404);
    }
}

if (!function_exists('methodNotAllowed')) {
    function methodNotAllowed(string $data = 'Method Not Allowed'): ResponseInterface
    {
        return response($data, 405);
    }
}

if (!function_exists('conflict')) {
    function conflict(string $data = 'Conflict'): ResponseInterface
    {
        return response($data, 409);
    }
}

if (!function_exists('unprocessableEntity')) {
    function unprocessableEntity(string $data = 'Unprocessable Entity'): ResponseInterface
    {
        return response($data, 422);
    }
}

if (!function_exists('internalServerError')) {
    function internalServerError(string $data = 'Internal Server Error'): ResponseInterface
    {
        return response($data, 500);
    }
}

if (!function_exists('notImplemented')) {
    function notImplemented(string $data = 'Not Implemented'): ResponseInterface
    {
        return response($data, 501);
    }
}

if (!function_exists('serviceUnavailable')) {
    function serviceUnavailable(string $data = 'Service Unavailable'): ResponseInterface
    {
        return response($data, 503);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): ResponseInterface
    {
        return Response::redirect($url, $status);
    }
}

if (!function_exists('permanentRedirect')) {
    function permanentRedirect(string $url): ResponseInterface
    {
        return Response::redirect($url, 301);
    }
}
