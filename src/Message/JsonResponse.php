<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

use JsonException;

class JsonResponse extends Response
{
    /**
     * @param mixed $data   Any value that can be json-encoded
     * @param int   $status HTTP status code (default 200)
     *
     * @throws JsonException
     */
    public function __construct(mixed $data, int $status = 200)
    {
        $json  = json_encode($data, JSON_THROW_ON_ERROR);
        $body  = Stream::createFromString($json);

        // parent signature: (StreamInterface $body, int $status, array $headers)
        parent::__construct(
            $body,
            $status,
            ['Content-Type' => 'application/json']
        );
    }
}