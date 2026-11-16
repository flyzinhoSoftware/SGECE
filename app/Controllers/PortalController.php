<?php
namespace App\Controllers;

use App\Core\Controller;
use Config\Database;

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
            'feedback' => null,
            'documentos' => []
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

        $codigoValue = $_POST['codigo_aluno'] ?? '';
        $birthValue = $_POST['data_nascimento'] ?? '';
        $codigoAluno = is_string($codigoValue) ? trim($codigoValue) : '';
        $dataNascimento = is_string($birthValue) ? trim($birthValue) : '';

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

        $documents = Database::connect()->prepare(
            "SELECT pd.codigo_validacao, pd.tipo_documento, pd.emitido_em,
                    e.nome AS escola_nome
             FROM alunos a
             INNER JOIN aluno_escolas ae ON ae.aluno_id = a.id
             INNER JOIN pedidos_documentos pd ON pd.aluno_escola_id = ae.id
             INNER JOIN escolas e ON e.id = pd.escola_id
             WHERE a.codigo_central = :codigo_aluno
               AND a.data_nascimento = :data_nascimento
               AND pd.estado = 'emitido'
               AND pd.documento_pdf IS NOT NULL
               AND pd.codigo_validacao IS NOT NULL
             ORDER BY pd.emitido_em DESC, pd.id DESC"
        );
        $documents->execute([
            'codigo_aluno' => $codigoAluno,
            'data_nascimento' => $dataNascimento
        ]);
        $issuedDocuments = $documents->fetchAll();
        $this->mostrarResultado(
            $issuedDocuments === [] ? 'info' : 'success',
            $issuedDocuments === [] ? 'Sem documentos emitidos' : 'Documentos disponíveis',
            $issuedDocuments === []
                ? 'Não foram encontrados documentos emitidos para os dados indicados.'
                : 'Foram encontrados documentos emitidos para o aluno.',
            $issuedDocuments
        );
    }

    public function verifyDocument(): void
    {
        header('Cache-Control: private, no-store, max-age=0');

        $value = $_GET['codigo'] ?? '';
        $code = is_string($value) ? strtoupper(trim($value)) : '';
        $document = null;
        $error = null;

        if ($code !== '') {
            if (preg_match('/^[A-F0-9]{32}$/', $code) !== 1) {
                $error = 'O código de validação é inválido.';
            } else {
                $statement = Database::connect()->prepare(
                    "SELECT pd.codigo_validacao, pd.tipo_documento, pd.emitido_em,
                            e.nome AS escola_nome, e.distrito AS escola_distrito
                     FROM pedidos_documentos pd
                     INNER JOIN escolas e ON e.id = pd.escola_id
                     WHERE pd.codigo_validacao = :codigo
                       AND pd.estado = 'emitido'
                       AND pd.documento_pdf IS NOT NULL
                     LIMIT 1"
                );
                $statement->execute(['codigo' => $code]);
                $document = $statement->fetch() ?: null;
                if ($document === null) {
                    $error = 'Não foi encontrado um documento emitido com esse código.';
                }
            }
        }

        $this->view('portal/verify-document', [
            'code' => $code,
            'document' => $document,
            'error' => $error,
            'basePath' => $this->basePath()
        ]);
    }

    public function downloadIssuedDocument(): void
    {
        $value = $_GET['codigo'] ?? '';
        $code = is_string($value) ? strtoupper(trim($value)) : '';
        if (preg_match('/^[A-F0-9]{32}$/', $code) !== 1) {
            $this->notFound();
            return;
        }

        $statement = Database::connect()->prepare(
            "SELECT codigo, tipo_documento, documento_pdf
             FROM pedidos_documentos
             WHERE codigo_validacao = :codigo
               AND estado = 'emitido'
               AND documento_pdf IS NOT NULL
             LIMIT 1"
        );
        $statement->execute(['codigo' => $code]);
        $document = $statement->fetch();
        if (!$document || !is_string($document['documento_pdf'])) {
            $this->notFound();
            return;
        }

        $type = $document['tipo_documento'] === 'certificado' ? 'certificado' : 'declaracao';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="sgece-' . $type . '-' . $code . '.pdf"');
        header('Content-Length: ' . strlen($document['documento_pdf']));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $document['documento_pdf'];
    }

    private function mostrarResultado(
        string $tipo,
        string $titulo,
        string $mensagem,
        array $documents = []
    ): void {
        $this->iniciarSessao();
        header('Cache-Control: private, no-store, max-age=0');

        $this->view('portal/index', [
            'csrfToken' => $_SESSION['portal_csrf'],
            'documentos' => $documents,
            'feedback' => [
                'tipo' => $tipo,
                'titulo' => $titulo,
                'mensagem' => $mensagem
            ]
        ]);
    }

    private function basePath(): string
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

        return $basePath;
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Documento não encontrado.';
    }
}
