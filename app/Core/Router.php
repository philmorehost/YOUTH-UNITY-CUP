<?php

declare(strict_types=1);

namespace Yuc\Core;

final class Router
{
    /** @var array<string,array<string,callable():void>> */
    private array $routes = [];

    public function get(string $path, callable $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): self
    {
        $this->routes[$method][$path] = $handler;
        return $this;
    }

    public function dispatch(string $method, string $path): void
    {
        $handler = $this->routes[$method][$path] ?? null;
        if ($handler !== null) {
            $handler();
            return;
        }

        foreach ($this->routes as $registeredMethod => $routes) {
            if (isset($routes[$path])) {
                http_response_code(405);
                header('Allow: ' . implode(', ', array_keys(array_filter(
                    $this->routes,
                    static fn (array $items): bool => isset($items[$path])
                ))));
                echo 'Method not allowed.';
                return;
            }
        }

        http_response_code(404);
        View::render('not-found', ['title' => 'Page not found']);
    }
}
