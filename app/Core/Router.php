<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router simples (Browser -> Routes -> Controller), sem framework
 * (OPS-PRD-001 capítulo 11.4).
 */
final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', rtrim($path, '/') ?: '/');
        $this->routes[] = [
            'method' => $method,
            'pattern' => '#^' . $pattern . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $params = array_filter($matches, fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY);

            foreach ($route['middleware'] as $middleware) {
                // Aceita 'Classe' ou ['Classe', 'argumento-extra', ...]
                $class = is_array($middleware) ? array_shift($middleware) : $middleware;
                $args = is_array($middleware) ? $middleware : [];
                (new $class())->handle($request, ...$args);
            }

            $handler = $route['handler'];

            if (is_array($handler)) {
                [$class, $action] = $handler;
                $controller = new $class();
                $controller->{$action}($request, $params);

                return;
            }

            $handler($request, $params);

            return;
        }

        http_response_code(404);
        echo '404 - Página não encontrada';
    }
}
