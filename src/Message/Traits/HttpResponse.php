<?php

declare(strict_types=1);

namespace Unk\LaravelApiResponse\Traits;

use MonkeysLegion\Http\Message\JsonResponse;

enum ApiStatus: string
{
    case SUCCESS = 'success';
    case ERROR   = 'error';
}

trait HttpResponse
{
    protected function success(
        array|string|object|null $data = null,
        ?string $message = null,
        int $code = 200,
        array $meta = []
    ): JsonResponse {
        return new JsonResponse([
            'status'  => ApiStatus::SUCCESS->value,
            'message' => $message,
            'data'    => $data,
            'meta'    => $meta,
        ], $code);
    }

    protected function error(
        ?string $message = null,
        int $code = 400,
        array $errors = []
    ): JsonResponse {
        return new JsonResponse([
            'status'  => ApiStatus::ERROR->value,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }
}
