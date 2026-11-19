<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Services\SchoolPdfGenerator;
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
            'pedido' => null
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

        $codeValue = $_POST['codigo_pedido'] ?? '';
        $requestCode = is_string($codeValue)
            ? strtoupper(trim($codeValue))
            : '';

        if ($requestCode === '') {
            $this->mostrarResultado(
                'warning',
                'Protocolo em falta',
                'Introduza o protocolo indicado no recibo do pedido.'
            );
            return;
        }

        if (preg_match('/^SGECE-PED-[A-F0-9]{12}$/', $requestCode) !== 1) {
            $this->mostrarResultado(
                'warning',
                'Protocolo inválido',
                'Confira o formato do protocolo impresso no recibo.'
            );
            return;
        }

        $statement = Database::connect()->prepare(
            "SELECT pd.codigo, pd.tipo_documento, pd.estado, pd.resposta_admin,
                    pd.solicitado_em, pd.emitido_em, pd.concluido_em,
                    (pd.documento_pdf IS NOT NULL) AS tem_documento,
                    e.nome AS escola_nome
             FROM pedidos_documentos pd
             INNER JOIN escolas e ON e.id = pd.escola_id
             WHERE pd.codigo = :codigo
             LIMIT 1"
        );
        $statement->execute(['codigo' => $requestCode]);
        $request = $statement->fetch() ?: null;
        if ($request === null) {
            $this->mostrarResultado(
                'warning',
                'Pedido não encontrado',
                'Não foi encontrado um pedido com esse protocolo. Confira os dados do recibo.'
            );
            return;
        }

        $this->mostrarResultado(
            'success',
            'Consulta realizada',
            'O estado actual do pedido está apresentado abaixo.',
            $request
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

    public function downloadAvailabilityReceipt(): void
    {
        header('Cache-Control: private, no-store, max-age=0');
        $value = $_GET['codigo'] ?? '';
        $code = is_string($value) ? strtoupper(trim($value)) : '';
        if (preg_match('/^SGECE-PED-[A-F0-9]{12}$/', $code) !== 1) {
            $this->notFound();
            return;
        }

        $statement = Database::connect()->prepare(
            "SELECT pd.codigo, pd.tipo_documento, pd.solicitado_em,
                    pd.emitido_em, e.nome AS escola_nome
             FROM pedidos_documentos pd
             INNER JOIN escolas e ON e.id = pd.escola_id
             WHERE pd.codigo = :codigo
               AND pd.estado = 'emitido'
               AND pd.documento_pdf IS NOT NULL
             LIMIT 1"
        );
        $statement->execute(['codigo' => $code]);
        $request = $statement->fetch();
        if (!$request) {
            $this->notFound();
            return;
        }

        $pdf = (new SchoolPdfGenerator())->documentAvailabilityReceipt($request);
        header('Content-Type: application/pdf');
        header(
            'Content-Disposition: attachment; filename="comprovativo-disponibilidade-'
            . $code . '.pdf"'
        );
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $pdf;
    }

    private function mostrarResultado(
        string $tipo,
        string $titulo,
        string $mensagem,
        ?array $request = null
    ): void {
        $this->iniciarSessao();
        header('Cache-Control: private, no-store, max-age=0');

        $this->view('portal/index', [
            'csrfToken' => $_SESSION['portal_csrf'],
            'pedido' => $request,
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
