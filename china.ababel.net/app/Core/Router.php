<?php
namespace App\Core;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    private array $middleware = [];

    public function get(string $path, string $handler, array $middleware = []): void
    {
        $this->routes['GET'][$path] = $handler;
        if ($middleware) {
            $this->middleware['GET'][$path] = $middleware;
        }
    }

    public function post(string $path, string $handler, array $middleware = []): void
    {
        $this->routes['POST'][$path] = $handler;
        if ($middleware) {
            $this->middleware['POST'][$path] = $middleware;
        }
    }

    public function dispatch(string $method, string $uri): void
    {
        if (!isset($this->routes[$method])) {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        foreach ($this->routes[$method] as $route => $handler) {
            $pattern = preg_replace('/\{(\w+)\}/', '(\\w+)', $route);
            $pattern = '#^' . $pattern . '$#';
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $this->runMiddleware($method, $route);
                [$controller, $action] = explode('@', $handler);
                $controllerClass = "App\\Controllers\\{$controller}";
                if (!class_exists($controllerClass)) {
                    http_response_code(500);
                    echo "Controller not found: $controllerClass";
                    return;
                }
                $instance = new $controllerClass();
                if (!method_exists($instance, $action)) {
                    http_response_code(500);
                    echo "Action not found: $action";
                    return;
                }
                call_user_func_array([$instance, $action], $matches);
                return;
            }
        }

        http_response_code(404);
        echo '404 - Page Not Found';
    }

    private function runMiddleware(string $method, string $route): void
    {
        $list = $this->middleware[$method][$route] ?? [];
        foreach ($list as $mw) {
            if (is_callable($mw)) {
                $mw();
            }
        }
    }
}