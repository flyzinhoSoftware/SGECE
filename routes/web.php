<?php

use App\Controllers\PortalController;

use App\Controllers\AdminController;

// Página inicial pública
$router->get('/', [PortalController::class, 'index']);

// Acesso alternativo ao portal
$router->get('/portal', [PortalController::class, 'index']);

// Consulta de disponibilidade de documentos
$router->post('/portal/consultar', [PortalController::class, 'consultar']);


// Área administrativa
$router->get('/admin', [AdminController::class, 'index']);
$router->get('/admin/dashboard', [AdminController::class, 'index']);