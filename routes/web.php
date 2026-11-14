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
$router->get('/admin', [AdminController::class, 'login']);
$router->post('/admin', [AdminController::class, 'login']);
$router->get('/admin/dashboard', [AdminController::class, 'index']);
$router->get('/admin/escolas', [AdminController::class, 'schools']);
$router->post('/admin/escolas', [AdminController::class, 'saveSchool']);
$router->post('/admin/escolas/estado', [AdminController::class, 'updateSchoolStatus']);
$router->get('/admin/alunos', [AdminController::class, 'students']);
$router->post('/admin/alunos', [AdminController::class, 'saveStudent']);
$router->post('/admin/alunos/estado', [AdminController::class, 'updateStudentStatus']);

$staticAdminPages = [
    '/admin/matriculas' => 'enrollment',
    '/admin/gestao-academica' => 'academic',
    '/admin/certificados' => 'certificates',
    '/admin/declaracoes' => 'declarations',
    '/admin/validacoes' => 'validations',
    '/admin/utilizadores' => 'users',
    '/admin/auditoria' => 'audit',
    '/admin/configuracoes' => 'settings'
];

foreach ($staticAdminPages as $path => $slug) {
    $router->get($path, static function () use ($slug): void {
        (new AdminController())->staticPage($slug);
    });
}

$router->post('/logout', [AdminController::class, 'logout']);