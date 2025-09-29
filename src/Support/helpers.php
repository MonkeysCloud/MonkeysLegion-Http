<?php

declare(strict_types=1);

use MonkeysLegion\Http\Message\JsonResponse;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use Psr\Http\Message\ResponseInterface;

/**
 * General response creator, auto-detects JSON for arrays/objects.
 * Ignores $headers when $data is array or object.
 *
 * @param mixed $data
 * @param int $status
 * @param array $headers
 */
function response(
    mixed $data = '',
    int $status = 200,
    array $headers = []
): ResponseInterface {
    if (is_array($data) || is_object($data)) {
        return new JsonResponse($data, $status);
    }

    $body = Stream::createFromString((string)$data);

    return new Response($body, $status, $headers);
}

function json(mixed $data, int $status = 200): JsonResponse
{
    return new JsonResponse($data, $status);
}

function ok(mixed $data = 'OK'): ResponseInterface
{
    return response($data, 200);
}

function created(mixed $data = 'Created'): ResponseInterface
{
    return response($data, 201);
}

function accepted(mixed $data = 'Accepted'): ResponseInterface
{
    return response($data, 202);
}

function noContent(): ResponseInterface
{
    return response('', 204);
}

function badRequest(mixed $data = 'Bad Request'): ResponseInterface
{
    return response($data, 400);
}

function unauthorized(mixed $data = 'Unauthorized'): ResponseInterface
{
    return response($data, 401);
}

function forbidden(mixed $data = 'Forbidden'): ResponseInterface
{
    return response($data, 403);
}

function notFound(mixed $data = 'Not Found'): ResponseInterface
{
    return response($data, 404);
}

function methodNotAllowed(mixed $data = 'Method Not Allowed'): ResponseInterface
{
    return response($data, 405);
}

function conflict(mixed $data = 'Conflict'): ResponseInterface
{
    return response($data, 409);
}

function unprocessableEntity(mixed $data = 'Unprocessable Entity'): ResponseInterface
{
    return response($data, 422);
}

function internalServerError(mixed $data = 'Internal Server Error'): ResponseInterface
{
    return response($data, 500);
}

function notImplemented(mixed $data = 'Not Implemented'): ResponseInterface
{
    return response($data, 501);
}

function serviceUnavailable(mixed $data = 'Service Unavailable'): ResponseInterface
{
    return response($data, 503);
}

function redirect(string $url, int $status = 302): ResponseInterface
{
    return response('', $status, ['Location' => $url]);
}

function permanentRedirect(string $url): ResponseInterface
{
    return redirect($url, 301);
}
