<?php

use Dotenv\Dotenv;
use App\Core\Router;
use Config\Database;

require_once dirname(__DIR__) . '/vendor/autoload.php';

try {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();

    // Estabelecer ligação à base de dados
    Database::connect();

    // Inicializar o sistema de rotas
    $router = new Router();

    // Carregar as rotas
    require dirname(__DIR__) . '/routes/web.php';

    // Executar a rota solicitada
    $router->dispatch();

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo '<h2>Erro ao iniciar o SGECE</h2>';

    echo '<pre>';
    echo htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );
    echo '</pre>';
}