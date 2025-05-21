<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use MonkeysLegion\Http\Negotiation\Accept;
use MonkeysLegion\Http\Negotiation\PayloadInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serialises controller results to JSON, XML, or HTML
 * depending on the *first* acceptable media-type.
 *
 * Order of preference recognised here:
 *   1. application/json
 *   2. application/xml, text/xml
 *   3. text/html
 *   4. "* / *" (falls back to JSON)
 */
final class ContentNegotiationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $factory
    ) {}

    public function process(ServerRequestInterface $request,
                            RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        /* If controller already returned a ResponseInterface, keep it */
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        /* Otherwise we must serialise the payload we just got */
        $acceptable = Accept::parse(
            $request->getHeaderLine('Accept')
        );

        // normalise to plain PHP value
        if ($response instanceof PayloadInterface) {
            $data = $response->toPayload();
        } else {
            $data = $response;         // array / scalar / stdClass …
        }

        /* Choose the best representation */
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

        /* Fallback – JSON */
        return $this->json($data);
    }

    /* ─────────────── helpers ─────────────── */

    private function json(mixed $data): ResponseInterface
    {
        $resp = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json');
        $resp->getBody()->write(json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
        return $resp;
    }

    private function xml(mixed $data): ResponseInterface
    {
        $xml = new \SimpleXMLElement('<root/>');
        $this->toXml($xml, $data);
        $resp = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/xml');
        $resp->getBody()->write($xml->asXML() ?: '');
        return $resp;
    }

    /** Very small array→XML helper (not for attributes / complex docs) */
    private function toXml(\SimpleXMLElement $node, mixed $data): void
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $child = is_string($k) ? $node->addChild($k) : $node->addChild('item');
                $this->toXml($child, $v);
            }
        } else {
            $node[0] = htmlspecialchars((string) $data, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }
    }

    private function html(mixed $data): ResponseInterface
    {
        /* VERY naive (for APIs) – you probably want Twig / Blade etc. */
        $body = '<pre>'.htmlspecialchars(print_r($data, true)).'</pre>';

        $resp = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8');
        $resp->getBody()->write($body);
        return $resp;
    }
}