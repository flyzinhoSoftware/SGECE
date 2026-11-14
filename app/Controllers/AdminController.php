<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use Config\Database;

class AdminController extends Controller
{
    public function login(): void
    {
        $this->startSession();
        $this->preventCaching();

        if ($this->authenticatedAdministrator() !== null) {
            $this->redirect('/admin/dashboard');
        }

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $erro = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $submittedToken = $_POST['csrf_token'] ?? null;

            if (
                !is_string($submittedToken) ||
                !hash_equals($_SESSION['csrf_token'], $submittedToken)
            ) {
                $erro = 'A sessão expirou. Atualize a página e tente novamente.';
            } else {
                $email = $_POST['email'] ?? null;
                $password = $_POST['password'] ?? null;

                if (
                    !is_string($email) ||
                    !is_string($password) ||
                    strlen($email = trim($email)) > 150 ||
                    filter_var($email, FILTER_VALIDATE_EMAIL) === false
                ) {
                    $erro = 'E-mail ou palavra-passe inválidos.';
                } else {
                    $statement = Database::connect()->prepare(
                        "SELECT u.id, u.nome, u.email, u.palavra_passe, na.nome AS nivel
                         FROM usuarios u
                         INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
                         WHERE u.email = :email
                           AND u.estado = 'activo'
                           AND na.nome IN ('Root', 'Admin')
                         LIMIT 1"
                    );
                    $statement->execute(['email' => $email]);
                    $user = $statement->fetch();

                    if ($user && password_verify($password, $user['palavra_passe'])) {
                        $updateLastAccess = Database::connect()->prepare(
                            'UPDATE usuarios SET ultimo_acesso = CURRENT_TIMESTAMP WHERE id = :id'
                        );
                        $updateLastAccess->execute(['id' => $user['id']]);

                        session_regenerate_id(true);
                        $_SESSION['auth'] = [
                            'user_id' => $user['id'],
                            'nome' => $user['nome'],
                            'nivel' => $user['nivel']
                        ];
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        $this->redirect('/admin/dashboard');
                    }

                    $erro = 'E-mail ou palavra-passe inválidos.';
                }
            }
        }

        $this->view('admin/login', [
            'erro' => $erro,
            'csrfToken' => $_SESSION['csrf_token']
        ]);
    }

    public function index(): void
    {
        $this->startSession();
        $this->preventCaching();

        $user = $this->authenticatedAdministrator();

        if ($user === null) {
            $this->redirect('/admin');
        }

        ob_start();
        require dirname(__DIR__) . '/Views/admin/dashboard.php';
        $content = ob_get_clean();

        $this->view('admin/layouts/main', [
            'pageTitle' => 'Painel de Controlo',
            'activeMenu' => 'dashboard',
            'nomeUsuario' => trim((string) $user['nome']),
            'nivelUsuario' => trim((string) $user['nivel']),
            'csrfToken' => $_SESSION['csrf_token'],
            'content' => $content
        ]);
    }

    public function logout(): void
    {
        $this->startSession();
        $this->preventCaching();

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            echo 'Método não permitido.';
            return;
        }

        $submittedToken = $_POST['csrf_token'] ?? null;

        if (
            !is_string($submittedToken) ||
            !isset($_SESSION['csrf_token']) ||
            !is_string($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $submittedToken)
        ) {
            http_response_code(403);
            echo 'A sessão expirou. Atualize a página e tente novamente.';
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Lax'
            ]);
        }

        session_destroy();
        $this->redirect('/admin');
    }

    private function authenticatedAdministrator(): ?array
    {
        $auth = $_SESSION['auth'] ?? null;

        if (!is_array($auth) || !isset($auth['user_id'])) {
            return null;
        }

        $userId = filter_var(
            $auth['user_id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($userId === false) {
            $this->invalidateAuthentication();
            return null;
        }

        $statement = Database::connect()->prepare(
            "SELECT u.id, u.nome, u.email, na.nome AS nivel
             FROM usuarios u
             INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
             WHERE u.id = :id
               AND u.estado = 'activo'
               AND na.nome IN ('Root', 'Admin')
             LIMIT 1"
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        if (!$user) {
            $this->invalidateAuthentication();
            return null;
        }

        $_SESSION['auth'] = [
            'user_id' => $user['id'],
            'nome' => $user['nome'],
            'nivel' => $user['nivel']
        ];

        return $user;
    }

    private function invalidateAuthentication(): void
    {
        unset($_SESSION['auth']);
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    private function startSession(): void
    {
        Session::start();
    }

    private function preventCaching(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function redirect(string $path): never
    {
        $scriptDir = str_replace(
            '\\',
            '/',
            dirname($_SERVER['SCRIPT_NAME'] ?? '/')
        );
        $basePath = rtrim(
            (string) preg_replace('#/public$#', '', $scriptDir),
            '/'
        );

        header('Location: ' . $basePath . $path);
        exit;
    }
}
