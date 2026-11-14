<?php

namespace App\Controllers;

use App\Core\Controller;

class AdminController extends Controller
{
    public function index(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Verifica se existe uma sessão autenticada.
        $auth = $_SESSION['auth'] ?? null;

        if (
            !is_array($auth) ||
            empty($auth['user_id']) ||
            empty($auth['nivel'])
        ) {
            $scriptDir = str_replace(
                '\\',
                '/',
                dirname($_SERVER['SCRIPT_NAME'] ?? '/')
            );

            $basePath = preg_replace('#/public$#', '', $scriptDir);
            $basePath = rtrim($basePath, '/');

            header('Location: ' . $basePath . '/login');
            exit;
        }

        $nome = trim((string) ($auth['nome'] ?? 'Utilizador'));
        $nivel = trim((string) $auth['nivel']);

        // Conteúdo específico do dashboard.
        ob_start();

        require dirname(__DIR__) . '/Views/admin/dashboard.php';

        $content = ob_get_clean();

        // Layout geral da área administrativa.
        $this->view('admin/layouts/main', [
            'pageTitle' => 'Painel de Controlo',
            'activeMenu' => 'dashboard',
            'nomeUsuario' => $nome,
            'nivelUsuario' => $nivel,
            'content' => $content
        ]);
    }
}