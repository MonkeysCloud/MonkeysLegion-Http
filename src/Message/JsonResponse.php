<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

use JsonException;

class JsonResponse extends Response
{
    /**
     * @throws JsonException
     */
    public function __construct(mixed $data, int $status = 200)
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        parent::__construct(
            $status,
            ['Content-Type' => 'application/json'],
            Stream::createFromString($json)
        );
    }
}