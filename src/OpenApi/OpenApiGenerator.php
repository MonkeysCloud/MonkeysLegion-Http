<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\OpenApi;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use MonkeysLegion\Router\RouteCollection;
use MonkeysLegion\Router\Route;
use MonkeysLegion\Router\Attributes\Route as RouteAttr;

final class OpenApiGenerator
{
    public function __construct(
        private RouteCollection $routes
    ) {}

    /**
     * Convert the OpenAPI spec to an array.
     *
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    public function toArray(): array
    {
        $paths = [];

        /** @var Route $route */
        foreach ($this->routes as $route) {
            $handler = $route->getHandler();          // e.g. [Controller::class, 'method']
            [$class, $method] = $handler;

            $refClass  = new ReflectionClass($class);
            $refMethod = $refClass->getMethod($method);

            /** @var RouteAttr $attr */
            $attr = $this->getRouteAttr($refMethod);

            $path   = $attr->path;
            $verb   = strtolower($attr->methods[0]);
            $opId   = $attr->name ?: $verb . '_' . trim($path, '/');

            $paths[$path][$verb] = [
                'operationId' => $opId,
                'summary'     => $attr->summary,
                'tags'        => $attr->tags,
                'responses'   => [
                    '200' => [
                        'description' => 'Successful response',
                    ]
                ]
            ];
        }

        return [
            'openapi' => '3.1.0',
            'info'    => [
                'title'   => 'MonkeysLegion API',
                'version' => '1.0.0',
            ],
            'paths'   => (object) $paths,
        ];
    }

    /**
     * Convert the OpenAPI spec to JSON.
     *
     * @param int $flags JSON encoding flags (default: JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
     * @throws ReflectionException
     */
    public function toJson(int $flags = JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $flags);
    }

    /**
     * Convert the OpenAPI spec to YAML.
     *
     * @param ReflectionMethod $m
     * @return RouteAttr
     */
    private function getRouteAttr(ReflectionMethod $m): RouteAttr
    {
        foreach ($m->getAttributes(RouteAttr::class) as $a) {
            return $a->newInstance();
        }
        throw new \RuntimeException('Route attribute missing on ' . $m->getName());
    }
}