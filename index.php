<?php

declare(strict_types=1);

// Ponto de entrada principal do SGECE

$publicPath = __DIR__ . '/public/index.php';

if (!file_exists($publicPath)) {
    http_response_code(500);
    exit('Erro: ficheiro principal do SGECE não encontrado.');
}

require $publicPath;