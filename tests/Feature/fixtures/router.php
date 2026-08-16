<?php

declare(strict_types=1);

// Router script for `php -S` used by EmitterFeatureTest.
// Builds responses with this package and emits them through SapiEmitter,
// giving a real HTTP round-trip (status line + headers + body over TCP).

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

use MonkeysLegion\Http\Emitter\SapiEmitter;
use MonkeysLegion\Http\Message\Response;

$requestUri = \is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
$path = (string) \parse_url($requestUri, \PHP_URL_PATH);

$response = match ($path) {
    '/json' => Response::json(['ok' => true]),
    '/nocontent' => Response::noContent(),
    '/notmodified' => Response::text('stale')->withStatus(304),
    '/custom' => Response::text('custom')
        ->withHeader('X-Custom', 'yes')
        ->withAddedHeader('X-Multi', 'a')
        ->withAddedHeader('X-Multi', 'b'),
    '/large' => Response::text(\str_repeat('x', 100_000)),
    default => Response::text('Hello World'),
};

new SapiEmitter()->emit($response);
