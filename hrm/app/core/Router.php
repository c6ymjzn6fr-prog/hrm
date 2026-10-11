<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $route, callable $handler): void
    {
        $this->routes['GET'][$route] = $handler;
    }

    public function post(string $route, callable $handler): void
    {
        $this->routes['POST'][$route] = $handler;
    }

    public function dispatch(string $route, string $method): void
    {
        $handler = $this->routes[$method][$route] ?? null;
        if ($handler === null) {
            http_response_code(404);
            render('errors/404', ['title' => 'Không tìm thấy trang']);
            return;
        }

        $handler();
    }
}
