<?php

declare(strict_types=1);

namespace VeloForum\Core;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    private readonly string $basePath;

    public function __construct(string $basePath = '')
    {
        $trimmed = '/' . trim($basePath, '/');
        $this->basePath = $trimmed === '/' ? '' : $trimmed;
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $regex = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method'  => $method,
            'pattern' => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath)) ?: '/';
        }
        if ($path === '/public' || str_starts_with($path, '/public/')) {
            $path = substr($path, 7) ?: '/';
        }
        if ($path === '/index.php') {
            $path = '/';
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            if (preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter(
                    $matches,
                    static fn (int|string $key): bool => is_string($key),
                    ARRAY_FILTER_USE_KEY
                );
                ($route['handler'])($params);
                return;
            }
        }

        http_response_code(404);
        echo '404 — Strona nie została znaleziona.';
    }
}
