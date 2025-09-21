<?php

declare(strict_types=1);

use MonkeysLegion\Http\Message\JsonResponse;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;

function response(
    mixed $data = '',
    int $status = 200,
    array $headers = []
): Response {
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

function ok(mixed $data = 'OK'): Response
{
    return response($data, 200);
}

function created(mixed $data = 'Created'): Response
{
    return response($data, 201);
}

function accepted(mixed $data = 'Accepted'): Response
{
    return response($data, 202);
}

function noContent(): Response
{
    return response('', 204);
}

function badRequest(mixed $data = 'Bad Request'): Response
{
    return response($data, 400);
}

function unauthorized(mixed $data = 'Unauthorized'): Response
{
    return response($data, 401);
}

function forbidden(mixed $data = 'Forbidden'): Response
{
    return response($data, 403);
}

function notFound(mixed $data = 'Not Found'): Response
{
    return response($data, 404);
}

function methodNotAllowed(mixed $data = 'Method Not Allowed'): Response
{
    return response($data, 405);
}

function conflict(mixed $data = 'Conflict'): Response
{
    return response($data, 409);
}

function unprocessableEntity(mixed $data = 'Unprocessable Entity'): Response
{
    return response($data, 422);
}

function internalServerError(mixed $data = 'Internal Server Error'): Response
{
    return response($data, 500);
}

function notImplemented(mixed $data = 'Not Implemented'): Response
{
    return response($data, 501);
}

function serviceUnavailable(mixed $data = 'Service Unavailable'): Response
{
    return response($data, 503);
}

function redirect(string $url, int $status = 302): Response
{
    return response('', $status, ['Location' => $url]);
}

function permanentRedirect(string $url): Response
{
    return redirect($url, 301);
}
