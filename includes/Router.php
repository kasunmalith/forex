<?php
declare(strict_types=1);

namespace App;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $basePath = rtrim((string) (parse_url((string) APP_CONFIG['SITE_URL'], PHP_URL_PATH) ?: ''), '/');
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: '/'));
        if ($basePath !== '' && $path !== $basePath && !str_starts_with($path, $basePath . '/')) {
            $this->notFound();
            return;
        }
        $path = $basePath === '' ? $path : substr($path, strlen($basePath));
        $path = '/' . trim($path, '/');
        if ($path === '//') { $path = '/'; }
        $handler = $this->routes[$method][$path] ?? null;
        if ($handler === null) {
            foreach ($this->routes[$method] ?? [] as $route => $candidate) {
                if (!str_contains($route, '{')) { continue; }
                $pattern = preg_replace('/\\{[A-Za-z][A-Za-z0-9_]*\\}/', '([^/]+)', $route);
                if (preg_match('~^' . $pattern . '$~D', $path, $matches) === 1) {
                    array_shift($matches);
                    $arguments = array_map(static fn(string $value): string => rawurldecode($value), $matches);
                    $handler = static fn() => $candidate(...$arguments);
                    break;
                }
            }
        }
        if ($handler === null) {
            if (str_starts_with($path, '/client/')) { \App\Middleware\AuthMiddleware::requireAuthenticated(); }
            if ($path === '/admin' || str_starts_with($path, '/admin/')) {
                \App\Middleware\AdminAuthMiddleware::requireAuthenticated();
                \App\Middleware\AdminAuthMiddleware::notFound();
            }
            $this->notFound();
            return;
        }
        $handler();
    }

    private function notFound(): void
    {
        http_response_code(404);
        \render('public/not-found', ['title' => 'Page not found']);
    }
}
