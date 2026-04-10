<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Auto-generates ETag headers and handles conditional requests.
 *
 * Cross-ecosystem innovation — zero-config HTTP caching:
 *  • Generates weak ETags from response body hash
 *  • Returns 304 Not Modified when If-None-Match matches
 *  • Skips non-GET/HEAD and non-2xx responses
 *  • No explicit cache keys needed — fully automatic
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ETagMiddleware implements MiddlewareInterface
{
    /**
     * @param bool $weakETag Use weak ETags (W/"...") instead of strong.
     */
    public function __construct(
        private readonly bool $weakETag = true,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        // Only generate ETags for successful GET/HEAD responses
        $method = $request->getMethod();
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return $response;
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            return $response;
        }

        // Skip if response already has an ETag
        if ($response->hasHeader('ETag')) {
            return $response;
        }

        // Generate ETag from body content
        $body = (string) $response->getBody();
        if ($response->getBody()->isSeekable()) {
            $response->getBody()->rewind();
        }

        $hash = hash('xxh3', $body);
        $etag = $this->weakETag ? sprintf('W/"%s"', $hash) : sprintf('"%s"', $hash);

        $response = $response->withHeader('ETag', $etag);

        // Check If-None-Match
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        if ($ifNoneMatch !== '' && $this->matches($etag, $ifNoneMatch)) {
            return $response
                ->withStatus(304)
                ->withBody(\MonkeysLegion\Http\Message\Stream::empty());
        }

        return $response;
    }

    private function matches(string $etag, string $ifNoneMatch): bool
    {
        // Strip whitespace and compare
        $candidates = array_map('trim', explode(',', $ifNoneMatch));
        foreach ($candidates as $candidate) {
            if ($candidate === $etag || $candidate === '*') {
                return true;
            }
            // Compare without weak prefix
            $cleanCandidate = preg_replace('/^W\//', '', $candidate);
            $cleanEtag      = preg_replace('/^W\//', '', $etag);
            if ($cleanCandidate === $cleanEtag) {
                return true;
            }
        }
        return false;
    }
}
