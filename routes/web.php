<?php

use App\Controllers\PortalController;

use App\Controllers\AdminController;
use App\Controllers\SchoolController;

// Página inicial pública
$router->get('/', [PortalController::class, 'index']);

// Acesso alternativo ao portal
$router->get('/portal', [PortalController::class, 'index']);

// Consulta de disponibilidade de documentos
$router->post('/portal/consultar', [PortalController::class, 'consultar']);
$router->get('/portal/recibo-disponibilidade', [PortalController::class, 'downloadAvailabilityReceipt']);
$router->get('/validar-documento', [PortalController::class, 'verifyDocument']);


// Área administrativa
$router->get('/admin', [AdminController::class, 'login']);
$router->post('/admin', [AdminController::class, 'login']);
$router->get('/admin/dashboard', [AdminController::class, 'index']);
$router->get('/escola', [SchoolController::class, 'dashboard']);
$router->get('/escola/dashboard', [SchoolController::class, 'dashboard']);
$router->get('/escola/alunos', [SchoolController::class, 'students']);
$router->post('/escola/alunos', [SchoolController::class, 'saveStudent']);
$router->get('/escola/matriculas', [SchoolController::class, 'enrollments']);
$router->post('/escola/matriculas', [SchoolController::class, 'saveEnrollment']);
$router->get('/escola/notas', [SchoolController::class, 'grades']);
$router->get('/escola/notas/lancar', [SchoolController::class, 'launchGrades']);
$router->post('/escola/notas/lancar', [SchoolController::class, 'saveGrades']);
$router->get('/escola/notas/pauta.pdf', [SchoolController::class, 'downloadGradeReport']);
$router->get('/escola/notas/pauta-geral.pdf', [SchoolController::class, 'downloadGeneralGradeReport']);
$router->get('/escola/notas/pauta-anual.pdf', [SchoolController::class, 'downloadAnnualGradeReport']);
$router->get('/escola/pedidos-documentos', [SchoolController::class, 'documentRequests']);
$router->post('/escola/pedidos-documentos', [SchoolController::class, 'saveDocumentRequest']);
$router->post('/escola/pedidos-documentos/emitir', [SchoolController::class, 'issueDocumentRequest']);
$router->get('/escola/pedidos-documentos/recibo.pdf', [SchoolController::class, 'downloadDocumentRequestReceipt']);
$router->get('/escola/pedidos-documentos/documento.pdf', [SchoolController::class, 'downloadIssuedDocument']);
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
$router->get('/admin/pedidos-documentos', [AdminController::class, 'documentRequests']);
$router->post('/admin/pedidos-documentos/estado', [AdminController::class, 'updateDocumentRequest']);
$router->get('/admin/pedidos-documentos/documento.pdf', [AdminController::class, 'downloadIssuedDocument']);

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