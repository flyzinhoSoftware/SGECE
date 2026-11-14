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
$router->get('/admin/utilizadores', [AdminController::class, 'users']);
$router->post('/admin/utilizadores', [AdminController::class, 'saveUser']);
$router->post('/admin/utilizadores/estado', [AdminController::class, 'updateUserStatus']);
$router->get('/admin/matriculas', [AdminController::class, 'enrollment']);
$router->post('/admin/matriculas', [AdminController::class, 'saveEnrollment']);
$router->post('/admin/matriculas/estado', [AdminController::class, 'updateEnrollmentStatus']);
$router->get('/admin/gestao-academica', [AdminController::class, 'academic']);
$router->post('/admin/gestao-academica/anos', [AdminController::class, 'saveAcademicYear']);
$router->post('/admin/gestao-academica/classes', [AdminController::class, 'saveAcademicClass']);
$router->post('/admin/gestao-academica/disciplinas', [AdminController::class, 'saveAcademicDiscipline']);

$staticAdminPages = [
    '/admin/certificados' => 'certificates',
    '/admin/declaracoes' => 'declarations',
    '/admin/validacoes' => 'validations',
    '/admin/auditoria' => 'audit',
    '/admin/configuracoes' => 'settings'
];

foreach ($staticAdminPages as $path => $slug) {
    $router->get($path, static function () use ($slug): void {
        (new AdminController())->staticPage($slug);
    });
}

$router->post('/logout', [AdminController::class, 'logout']);