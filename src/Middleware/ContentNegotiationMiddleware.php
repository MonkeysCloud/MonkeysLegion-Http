<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use MonkeysLegion\Http\Negotiation\Accept;
use MonkeysLegion\Http\Negotiation\PayloadInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Content-negotiation middleware — serializes controller payloads
 * to JSON, XML, or HTML based on the client's Accept header.
 *
 * Preference order:
 *  1. application/json
 *  2. application/xml, text/xml
 *  3. text/html
 *  4. * / * (falls back to JSON)
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ContentNegotiationMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        // If controller returned a ResponseInterface, keep it as-is
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        // Otherwise serialize the payload
        $acceptable = Accept::parse($request->getHeaderLine('Accept'));

        $data = ($response instanceof PayloadInterface)
            ? $response->toPayload()
            : $response;

        foreach ($acceptable as $mime) {
            if ($mime === 'application/json' || $mime === '*/*') {
                return $this->json($data);
            }
            if ($mime === 'application/xml' || $mime === 'text/xml') {
                return $this->xml($data);
            }
            if ($mime === 'text/html') {
                return $this->html($data);
            }
        }

        return $this->json($data);
    }

    // ── Serializers ────────────────────────────────────────────

    private function json(mixed $data): ResponseInterface
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::createFromString($json),
            200,
            ['Content-Type' => 'application/json'],
        );
    }

    private function xml(mixed $data): ResponseInterface
    {
        $xml = new \SimpleXMLElement('<root/>', LIBXML_NONET);
        $this->arrayToXml($xml, $data);

        return new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::createFromString($xml->asXML() ?: ''),
            200,
            ['Content-Type' => 'application/xml'],
        );
    }

    private function html(mixed $data): ResponseInterface
    {
        $body = '<pre>' . htmlspecialchars(print_r($data, true)) . '</pre>';

        return new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::createFromString($body),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    // ── Internal ───────────────────────────────────────────────

    private function arrayToXml(\SimpleXMLElement $node, mixed $data): void
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $child = is_string($k) ? $node->addChild($k) : $node->addChild('item');
                $this->arrayToXml($child, $v);
            }
        } else {
            $node[0] = htmlspecialchars((string) $data, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }
    }
}