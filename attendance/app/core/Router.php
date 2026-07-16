<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalize(parse_url($uri, PHP_URL_PATH) ?: '/');
        $base = rtrim((string) config('base_url'), '/');
        $basePath = parse_url($base, PHP_URL_PATH) ?: '';
        if ($basePath && str_starts_with($path, $this->normalize($basePath))) {
            $path = $this->normalize(substr($path, strlen($this->normalize($basePath))));
        }

        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) {
            http_response_code(404);
            echo 'Page not found.';
            return;
        }

        [$class, $action] = $handler;
        (new $class())->{$action}();
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
