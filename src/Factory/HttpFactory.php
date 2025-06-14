<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Factory;

use GuzzleHttp\Psr7\HttpFactory as GuzzleFactory;
use Psr\Http\Message\{
    RequestInterface, ResponseInterface, ServerRequestInterface,
    StreamInterface, UploadedFileInterface, UriInterface
};
use Psr\Http\Message\{
    RequestFactoryInterface, ResponseFactoryInterface,
    ServerRequestFactoryInterface, StreamFactoryInterface,
    UploadedFileFactoryInterface, UriFactoryInterface
};

/**
 * HttpFactory is a concrete implementation of the PSR-17 factories
 * using Guzzle's HttpFactory.
 *
 * @see https://www.php-fig.org/psr/psr-17/
 */
final class HttpFactory implements
    RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface
{
    /**
     * @var GuzzleFactory
     */
    private GuzzleFactory $inner;

    /**
     * HttpFactory constructor.
     */
    public function __construct()
    {
        $this->inner = new GuzzleFactory();
    }

    /**
     * Creates a new request instance.
     *
     * @param string $method
     * @param $uri
     * @return RequestInterface
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        return $this->inner->createRequest($method, $uri);
    }

    /**
     * Creates a new response instance.
     *
     * @param int $code
     * @param string $reasonPhrase
     * @return ResponseInterface
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return $this->inner->createResponse($code, $reasonPhrase);
    }

    /**
     * Creates a new server request instance.
     *
     * @param string $method
     * @param $uri
     * @param array $serverParams
     * @return ServerRequestInterface
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return $this->inner->createServerRequest($method, $uri, $serverParams);
    }

    /**
     * Creates a new stream instance.
     *
     * @param string $content
     * @return StreamInterface
     */
    public function createStream(string $content = ''): StreamInterface
    {
        return $this->inner->createStream($content);
    }

    /**
     * Creates a new stream from a file.
     *
     * @param string $filename
     * @param string $mode
     * @return StreamInterface
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return $this->inner->createStreamFromFile($filename, $mode);
    }

    /**
     * Creates a new stream from a resource.
     *
     * @param resource $resource
     * @return StreamInterface
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return $this->inner->createStreamFromResource($resource);
    }

    /**
     * Creates a new uploaded file instance.
     *
     * @param StreamInterface $stream
     * @param int|null $size
     * @param int $error
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     * @return UploadedFileInterface
     */
    public function createUploadedFile(
        StreamInterface $stream,
        ?int            $size = null,
        int             $error = \UPLOAD_ERR_OK,
        ?string         $clientFilename = null,
        ?string         $clientMediaType = null
    ): UploadedFileInterface {
        return $this->inner->createUploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }

    /**
     * Creates a new URI instance.
     *
     * @param string $uri
     * @return UriInterface
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return $this->inner->createUri($uri);
    }
}