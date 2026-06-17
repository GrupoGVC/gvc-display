<?php
namespace App\Core;

class Router
{
    private array $routes = [];

    // Registra rota: GET /api/devices
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
        // Remove o BASE_PATH para que as rotas sejam relativas à raiz do app
        // Ex: /gvc-display-mvc/api/devices → /api/devices
        if (defined('BASE_PATH') && BASE_PATH !== '' && str_starts_with($uri, BASE_PATH)) {
            $uri = substr($uri, strlen(BASE_PATH));
        }
        $uri = '/' . ltrim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $method !== 'OPTIONS') continue;

            if (preg_match($route['pattern'], $uri, $m)) {
                // Remove índices numéricos, mantém só named captures
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
        // Converte :id, :slug etc. em named captures regex
        $regex = preg_replace('/:([a-zA-Z_]+)/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }
}
