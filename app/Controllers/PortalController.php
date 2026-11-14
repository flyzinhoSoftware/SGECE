<?php
namespace App\Controllers;

use App\Core\Controller;

class PortalController extends Controller
{
    private function iniciarSessao(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public function index(): void
    {
        $this->iniciarSessao();

        if (empty($_SESSION['portal_csrf'])) {
            $_SESSION['portal_csrf'] = bin2hex(random_bytes(32));
        }

        $this->view('portal/index', [
            'csrfToken' => $_SESSION['portal_csrf'],
            'feedback' => null
        ]);
    }

    public function consultar(): void
    {
        $this->iniciarSessao();

        $token = $_POST['_token'] ?? '';

        if (
            empty($_SESSION['portal_csrf']) ||
            !is_string($token) ||
            !hash_equals($_SESSION['portal_csrf'], $token)
        ) {
            http_response_code(419);

            $this->view('errors/404', []);
            return;
        }

        $codigoAluno = trim($_POST['codigo_aluno'] ?? '');
        $dataNascimento = trim($_POST['data_nascimento'] ?? '');

        if ($codigoAluno === '' || $dataNascimento === '') {
            $this->mostrarResultado(
                'warning',
                'Dados incompletos',
                'Preencha o código do aluno e a data de nascimento.'
            );
            return;
        }

        if (strlen($codigoAluno) > 60) {
            $this->mostrarResultado(
                'warning',
                'Código inválido',
                'Verifique o código do aluno.'
            );
            return;
        }

        $data = \DateTime::createFromFormat(
            '!Y-m-d',
            $dataNascimento
        );

        if (
            !$data ||
            $data->format('Y-m-d') !== $dataNascimento ||
            $data > new \DateTime('today')
        ) {
            $this->mostrarResultado(
                'warning',
                'Data inválida',
                'Introduza uma data de nascimento válida.'
            );
            return;
        }

        /*
         * A consulta real será ligada ao módulo de documentos
         * depois da criação das tabelas de certificados,
         * declarações e respectivos estados de aprovação.
         *
         * Não apresentar resultados fictícios ao cidadão.
         */

        $this->mostrarResultado(
            'info',
            'Consulta em preparação',
            'O serviço de consulta está a ser preparado. ' .
            'A disponibilidade dos documentos ainda não pode ser confirmada.'
        );
    }

    private function mostrarResultado(
        string $tipo,
        string $titulo,
        string $mensagem
    ): void {
        $this->iniciarSessao();

        $this->view('portal/index', [
            'csrfToken' => $_SESSION['portal_csrf'],
            'feedback' => [
                'tipo' => $tipo,
                'titulo' => $titulo,
                'mensagem' => $mensagem
            ]
        ]);
    }
}
