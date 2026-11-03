<?php

use App\Core\Router;
use App\Controllers\HomeController;

$router->get('/', [HomeController::class, 'index']);

$router->get('/inicio', [HomeController::class, 'index']);