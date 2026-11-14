<?php

namespace App\Core;

class Router
{
    private const REQUEST_LIMIT = 100;
    private const REQUEST_WINDOW_SECONDS = 60;
    private const BLOCK_SECONDS = 60;

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
        $basePath = preg_replace('#/public$#', '', $basePath);

        return $basePath === '/' || $basePath === '.'
            ? ''
            : rtrim($basePath, '/');
    }

    /**
     * Executar a rota solicitada.
     */
    public function dispatch(): void
    {
        Session::start();

        if (!$this->allowRequest()) {
            return;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri = parse_url(
            $_SERVER['REQUEST_URI'] ?? '/',
            PHP_URL_PATH
        );

        $path = $this->normalizePath($uri ?: '/');

        // Remover o caminho base do projecto.
        $basePath = $this->getBasePath();

        if ($basePath !== '') {
            if (strcasecmp($path, $basePath) === 0) {
                $path = '/';
            } elseif (
                strncasecmp(
                    $path,
                    $basePath . '/',
                    strlen($basePath) + 1
                ) === 0
            ) {
                $path = substr($path, strlen($basePath));
            }
        }

        $path = $this->normalizePath($path);

        if ($this->servePublicAsset($path)) {
            return;
        }

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

    private function servePublicAsset(string $path): bool
    {
        $assetPath = '/public/assets';
        $assetPrefix = $assetPath . '/';

        if (strcasecmp($path, $assetPath) === 0) {
            http_response_code(404);
            return true;
        }

        if (
            strncasecmp($path, $assetPrefix, strlen($assetPrefix)) !== 0
        ) {
            return false;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            header('Allow: GET, HEAD');
            http_response_code(405);
            return true;
        }

        $root = realpath(
            dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'assets'
        );
        $relativePath = rawurldecode(substr($path, strlen($assetPrefix)));

        if ($root === false || $relativePath === '' || str_contains($relativePath, "\0")) {
            http_response_code(404);
            return true;
        }

        $relativePath = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $relativePath
        );
        $file = realpath($root . DIRECTORY_SEPARATOR . $relativePath);
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (
            $file === false ||
            strncasecmp($file, $rootPrefix, strlen($rootPrefix)) !== 0 ||
            !is_file($file)
        ) {
            http_response_code(404);
            return true;
        }

        $contentTypes = [
            'css' => 'text/css; charset=UTF-8',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'js' => 'text/javascript; charset=UTF-8',
            'otf' => 'font/otf',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'ttf' => 'font/ttf',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2'
        ];
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!isset($contentTypes[$extension])) {
            http_response_code(404);
            return true;
        }

        header('Content-Type: ' . $contentTypes[$extension]);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=3600');
        header('Content-Length: ' . filesize($file));
        header('Last-Modified: ' . gmdate(DATE_RFC7231, filemtime($file)));

        if ($method === 'GET') {
            readfile($file);
        }

        return true;
    }

    private function allowRequest(): bool
    {
        $now = time();
        $blockedUntil = $_SESSION['_rate_limit_blocked_until'] ?? 0;

        if (is_int($blockedUntil) && $blockedUntil > $now) {
            $this->rejectRateLimitedRequest($blockedUntil);
            return false;
        }

        unset($_SESSION['_rate_limit_blocked_until']);

        $timestamps = $_SESSION['_request_timestamps'] ?? [];
        if (!is_array($timestamps)) {
            $timestamps = [];
        }

        $windowStart = $now - self::REQUEST_WINDOW_SECONDS;
        $timestamps = array_values(array_filter(
            $timestamps,
            static fn ($timestamp): bool =>
                is_int($timestamp) &&
                $timestamp > $windowStart &&
                $timestamp <= $now
        ));

        if (count($timestamps) >= self::REQUEST_LIMIT) {
            $blockedUntil = $now + self::BLOCK_SECONDS;
            $_SESSION = ['_rate_limit_blocked_until' => $blockedUntil];
            session_regenerate_id(true);
            $this->rejectRateLimitedRequest($blockedUntil);
            return false;
        }

        $timestamps[] = $now;
        $_SESSION['_request_timestamps'] = $timestamps;

        return true;
    }

    private function rejectRateLimitedRequest(int $blockedUntil): void
    {
        $retryAfter = max(1, $blockedUntil - time());

        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=UTF-8');

        echo 'Limite de 100 requisicoes por minuto excedido. '
            . 'A sessao foi bloqueada por 60 segundos; inicie sessao novamente.';
    }
}
