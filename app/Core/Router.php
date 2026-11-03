<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $callback): void
    {
        $this->addRoute('GET', $path, $callback);
    }

    public function post(string $path, callable|array $callback): void
    {
        $this->addRoute('POST', $path, $callback);
    }

    /**
     * Registar uma nova rota.
     */
    private function addRoute(
        string $method,
        string $path,
        callable|array $callback
    ): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->normalizePath($path),
            'callback' => $callback
        ];
    }

    /**
     * Normalizar os caminhos.
     */
    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '//' ? '/' : $path;
    }

    /**
     * Identificar o caminho base do projecto.
     */
    private function getBasePath(): string
    {
        $scriptName = str_replace(
            '\\',
            '/',
            $_SERVER['SCRIPT_NAME'] ?? ''
        );

        $basePath = dirname($scriptName);

        return $basePath === '/' || $basePath === '.'
            ? ''
            : rtrim($basePath, '/');
    }

    /**
     * Executar a rota solicitada.
     */
    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri = parse_url(
            $_SERVER['REQUEST_URI'] ?? '/',
            PHP_URL_PATH
        );

        $path = $this->normalizePath($uri ?: '/');

        // Remover o caminho base do projecto.
        $basePath = $this->getBasePath();

        if ($basePath !== '') {
            if ($path === $basePath) {
                $path = '/';
            } elseif (str_starts_with($path, $basePath . '/')) {
                $path = substr($path, strlen($basePath));
            }
        }

        $path = $this->normalizePath($path);

        // Procurar a rota correspondente.
        foreach ($this->routes as $route) {

            if (
                $route['method'] === $method &&
                $route['path'] === $path
            ) {
                $callback = $route['callback'];

                // Executar Controller.
                if (is_array($callback)) {

                    [$controllerClass, $action] = $callback;

                    $controller = new $controllerClass();

                    if (!method_exists($controller, $action)) {
                        throw new \RuntimeException(
                            "Método {$action} não encontrado no Controller."
                        );
                    }

                    $controller->$action();

                    return;
                }

                // Executar função ou callback.
                call_user_func($callback);

                return;
            }
        }

        // Rota não encontrada.
        http_response_code(404);

        echo '<h1>404 - Página não encontrada</h1>';
        echo '<p>A página solicitada não existe no SGECE.</p>';
    }
}
