<?php
namespace App\Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $this->compile($pattern),
            'handler' => $handler,
        ];
    }

    public function get(string $p, callable|array $h): void    { $this->add('GET',    $p, $h); }
    public function post(string $p, callable|array $h): void   { $this->add('POST',   $p, $h); }
    public function put(string $p, callable|array $h): void    { $this->add('PUT',    $p, $h); }
    public function delete(string $p, callable|array $h): void { $this->add('DELETE', $p, $h); }

    public function dispatch(string $method, string $uri): void
    {
        // Remove query string
        $uri = strtok($uri, '?');
        // Remove BASE_PATH
        if (defined('BASE_PATH') && BASE_PATH !== '' && str_starts_with($uri, BASE_PATH)) {
            $uri = substr($uri, strlen(BASE_PATH));
        }
        $uri = '/' . ltrim($uri, '/');
        // Remove barra final (exceto na raiz "/")
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $method !== 'OPTIONS') continue;

            if (preg_match($route['pattern'], $uri, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

                if ($method === 'OPTIONS') {
                    http_response_code(204);
                    exit;
                }

                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    (new $class())->$action($params);
                } else {
                    $handler($params);
                }
                return;
            }
        }

        Response::notFound("Rota não encontrada: $method $uri");
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace('/:([a-zA-Z_]+)/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }
}
