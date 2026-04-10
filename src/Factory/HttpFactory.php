<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Factory;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Message\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * PSR-17 factory implementation using MonkeysLegion's own
 * PSR-7 message classes. No external dependencies.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class HttpFactory implements
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UriFactoryInterface
{
    /** {@inheritDoc} */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response(
            Stream::empty(),
            $code,
            [],
            '1.1',
            $reasonPhrase,
        );
    }

    /** {@inheritDoc} */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        $uriObj = is_string($uri) ? new Uri($uri) : $uri;

        return new ServerRequest(
            $method,
            $uriObj,
            Stream::empty(),
            [],
            '1.1',
            $serverParams,
        );
    }

    /** {@inheritDoc} */
    public function createStream(string $content = ''): StreamInterface
    {
        return Stream::createFromString($content);
    }

    /** {@inheritDoc} */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return Stream::createFromFile($filename, $mode);
    }

    /** {@inheritDoc} */
    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }

    /** {@inheritDoc} */
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
