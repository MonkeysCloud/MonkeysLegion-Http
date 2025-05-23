<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\OpenApi;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class OpenApiMiddleware implements MiddlewareInterface
{
    public function __construct(
        private OpenApiGenerator          $generator,
        private ResponseFactoryInterface  $responses,
        private string                    $jsonPath = '/openapi.json',
        private string                    $uiPath   = '/docs'
    ) {}

    public function process(ServerRequestInterface $req, RequestHandlerInterface $h): ResponseInterface
    {
        $uri = $req->getUri()->getPath();

        // 1) Serve JSON spec
        if ($uri === $this->jsonPath) {
            $res = $this->responses->createResponse(200)
                ->withHeader('Content-Type', 'application/json');
            $res->getBody()->write($this->generator->toJson());
            return $res;
        }

        // 2) Serve Swagger UI HTML (CDN build)
        if ($uri === $this->uiPath || rtrim($uri, '/') === rtrim($this->uiPath, '/')) {
            $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <title>API docs</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist/swagger-ui.css" />
</head>
<body>
<div id="swagger"></div>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist/swagger-ui-bundle.js"></script>
<script>
  SwaggerUIBundle({
    url: '{$this->jsonPath}',
    dom_id: '#swagger'
  });
</script>
</body>
</html>
HTML;

            $res = $this->responses->createResponse(200)
                ->withHeader('Content-Type', 'text/html; charset=utf-8');
            $res->getBody()->write($html);
            return $res;
        }

        // Pass through
        return $h->handle($req);
    }
}