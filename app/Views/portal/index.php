<?php
/**
 * SGECE — Portal Público
 * View: app/Views/portal/index.php
 */

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

if (preg_match('#/public$#', $scriptDir)) {
    $appBaseUrl = substr($scriptDir, 0, -7);
} else {
    $appBaseUrl = $scriptDir;
}

$appBaseUrl = $appBaseUrl === '/' ? '' : $appBaseUrl;
$publicBaseUrl = $appBaseUrl . '/public';

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$csrfToken = $csrfToken ?? ($_SESSION['csrf_token'] ?? '');
$feedback = $feedback ?? null;
$documentos = is_array($documentos ?? null) ? $documentos : [];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SGECE | Portal de Serviços Escolares</title>

    <meta name="description"
          content="Portal público do Sistema de Gestão e Emissão de Certificados Escolares. Consulte a disponibilidade dos seus documentos escolares.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <link rel="stylesheet"
          href="<?= $escape($publicBaseUrl) ?>/assets/css/portal.css">
</head>

<body>

<!-- NAVEGAÇÃO -->
<header class="site-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">

            <a class="navbar-brand brand" href="<?= $escape($appBaseUrl) ?>/">
                <span class="brand-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </span>

                <span class="brand-text">
                    <strong>SGECE</strong>
                    <small>Gestão Escolar</small>
                </span>
            </a>

            <button class="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#mainNavigation"
                    aria-controls="mainNavigation"
                    aria-expanded="false"
                    aria-label="Abrir navegação">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavigation">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">

                    <li class="nav-item">
                        <a class="nav-link active" href="#inicio">Início</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#servicos">Serviços</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#consulta">Consultar documento</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#sobre">Sobre o sistema</a>
                    </li>

                    <li class="nav-item ms-lg-2">
                        <a href="<?= $escape($appBaseUrl) ?>/admin"
                            class="btn btn-outline-primary btn-login">
                            <i class="bi bi-speedometer2 me-1"></i>
                            Área administrativa
                        </a>
                    </li>

                </ul>
            </div>

        </div>
    </nav>
</header>


<main>

    <!-- HERO -->
    <section class="hero-section" id="inicio">
        <div class="container">
            <div class="row align-items-center gy-5">

                <div class="col-lg-7">
                    <div class="hero-content">

                        <span class="eyebrow">
                            <span class="eyebrow-dot"></span>
                            Plataforma de serviços escolares
                        </span>

                        <h1>
                            Os seus documentos escolares,
                            <span>com mais facilidade.</span>
                        </h1>

                        <p class="hero-description">
                            Consulte a disponibilidade dos seus certificados
                            e declarações escolares através de uma plataforma
                            simples, segura e acessível.
                        </p>

                        <div class="hero-actions">
                            <a href="#consulta" class="btn btn-primary btn-lg">
                                Consultar documento
                                <i class="bi bi-arrow-right ms-2"></i>
                            </a>

                            <a href="#servicos" class="btn btn-light btn-lg btn-secondary-action">
                                Conhecer os serviços
                            </a>
                        </div>

                        <div class="hero-trust">
                            <div class="trust-icon">
                                <i class="bi bi-shield-check"></i>
                            </div>

                            <div>
                                <strong>Informação institucional</strong>
                                <small>Consulta simples e segura</small>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="hero-illustration">

                        <div class="illustration-orbit orbit-one"></div>
                        <div class="illustration-orbit orbit-two"></div>

                        <div class="document-card">
                            <div class="document-top">
                                <span class="document-logo">
                                    <i class="bi bi-mortarboard-fill"></i>
                                </span>

                                <span class="document-status">
                                    <i class="bi bi-check-circle-fill"></i>
                                    Documento escolar
                                </span>
                            </div>

                            <div class="document-lines">
                                <span class="line line-small"></span>
                                <span class="line line-large"></span>
                                <span class="line line-medium"></span>
                                <span class="line line-medium"></span>
                            </div>

                            <div class="document-footer">
                                <div class="signature-line"></div>
                                <span>Documento institucional</span>
                            </div>
                        </div>

                        <div class="floating-card floating-card-top">
                            <span class="floating-icon">
                                <i class="bi bi-shield-lock-fill"></i>
                            </span>
                            <span>
                                <strong>Consulta segura</strong>
                                <small>Dados protegidos</small>
                            </span>
                        </div>

                        <div class="floating-card floating-card-bottom">
                            <span class="floating-icon green">
                                <i class="bi bi-search"></i>
                            </span>
                            <span>
                                <strong>Consulta online</strong>
                                <small>Sem deslocação inicial</small>
                            </span>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>


    <!-- SERVIÇOS -->
    <section class="services-section section-padding" id="servicos">
        <div class="container">

            <div class="section-heading text-center">
                <span class="section-label">Os nossos serviços</span>

                <h2>Serviços disponíveis no portal</h2>

                <p>
                    Aceda aos principais serviços relacionados com os
                    seus documentos e informações escolares.
                </p>
            </div>

            <div class="row g-4">

                <div class="col-md-6 col-lg-4">
                    <article class="service-card">
                        <div class="service-icon blue">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>

                        <h3>Certificados escolares</h3>

                        <p>
                            Consulte a disponibilidade do seu certificado
                            escolar emitido pela instituição de ensino.
                        </p>

                        <a href="#consulta" class="service-link">
                            Consultar disponibilidade
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </article>
                </div>

                <div class="col-md-6 col-lg-4">
                    <article class="service-card">
                        <div class="service-icon purple">
                            <i class="bi bi-file-earmark-check"></i>
                        </div>

                        <h3>Declarações escolares</h3>

                        <p>
                            Verifique a disponibilidade de declarações
                            relacionadas com o seu percurso escolar.
                        </p>

                        <a href="#consulta" class="service-link">
                            Consultar disponibilidade
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </article>
                </div>

                <div class="col-md-6 col-lg-4">
                    <article class="service-card">
                        <div class="service-icon green">
                            <i class="bi bi-patch-check"></i>
                        </div>

                        <h3>Validação de documentos</h3>

                        <p>
                            Confirme a autenticidade de um documento
                            através do respectivo código de validação.
                        </p>

                        <a href="<?= $escape($appBaseUrl) ?>/validar-documento"
                           class="service-link">
                            Validar documento
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </article>
                </div>

            </div>
        </div>
    </section>


    <!-- CONSULTA -->
    <section class="consultation-section section-padding" id="consulta">
        <div class="container">

            <div class="row align-items-center gy-5">

                <div class="col-lg-5">
                    <span class="section-label">Consulta de documentos</span>

                    <h2 class="consultation-title">
                        Verifique se o seu documento está disponível.
                    </h2>

                    <p class="consultation-description">
                        Introduza os seus dados de identificação para
                        consultar o estado de disponibilidade do documento
                        junto da instituição de ensino.
                    </p>

                    <div class="consultation-note">
                        <i class="bi bi-info-circle-fill"></i>

                        <p>
                            Esta consulta permite verificar a disponibilidade.
                            O levantamento do documento é realizado
                            presencialmente na respectiva escola.
                        </p>
                    </div>

                    <div class="security-note">
                        <i class="bi bi-shield-lock-fill"></i>
                        <span>Os dados devem ser utilizados apenas para fins de consulta.</span>
                    </div>
                </div>


                <div class="col-lg-7">
                    <div class="consultation-card">

                        <div class="form-card-heading">
                            <div class="form-heading-icon">
                                <i class="bi bi-search"></i>
                            </div>

                            <div>
                                <h3>Consultar disponibilidade</h3>
                                <p>Preencha os campos abaixo.</p>
                            </div>
                        </div>

                        <?php if (is_array($feedback)): ?>
                            <?php
                            $feedbackType = $feedback['tipo'] ?? 'info';
                            $feedbackClass = in_array($feedbackType, ['success', 'warning', 'danger', 'info'], true)
                                ? $feedbackType
                                : 'info';
                            ?>
                            <div class="alert alert-<?= $escape($feedbackClass) ?>" role="status">
                                <strong><?= $escape($feedback['titulo'] ?? '') ?></strong>
                                <div><?= $escape($feedback['mensagem'] ?? '') ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($documentos !== []): ?>
                            <div class="list-group mb-4">
                                <?php foreach ($documentos as $documento): ?>
                                    <?php
                                    $documentLabel = match ($documento['tipo_documento']) {
                                        'certificado' => 'Certificado escolar',
                                        'declaracao_notas' => 'Declaração de notas',
                                        default => 'Declaração escolar'
                                    };
                                    ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                            <div>
                                                <strong><?= $escape($documentLabel) ?></strong>
                                                <div class="small text-muted">
                                                    <?= $escape($documento['escola_nome']) ?>
                                                    · <?= $escape($documento['emitido_em']) ?>
                                                </div>
                                            </div>
                                            <a class="btn btn-sm btn-primary"
                                               href="<?= $escape($appBaseUrl) ?>/portal/documento?codigo=<?= $escape(urlencode($documento['codigo_validacao'])) ?>">
                                                <i class="bi bi-download me-1"></i>Descarregar PDF
                                            </a>
                                        </div>
                                        <a class="small d-inline-block mt-2"
                                           href="<?= $escape($appBaseUrl) ?>/validar-documento?codigo=<?= $escape(urlencode($documento['codigo_validacao'])) ?>">
                                            Validar autenticidade
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form id="consultationForm"
                              action="<?= $escape($appBaseUrl) ?>/portal/consultar"
                              method="POST">

                            <input type="hidden"
                                   name="_token"
                                   value="<?= $escape($csrfToken) ?>">

                            <div class="mb-4">
                                <label for="codigo_aluno" class="form-label">
                                    Código do aluno
                                    <span class="required">*</span>
                                </label>

                                <div class="input-with-icon">
                                    <i class="bi bi-person-vcard"></i>

                                    <input type="text"
                                           class="form-control"
                                           id="codigo_aluno"
                                           name="codigo_aluno"
                                           placeholder="Ex.: ALU-2026-0001"
                                           maxlength="50"
                                           autocomplete="off"
                                           required>
                                </div>

                                <div class="form-text">
                                    Introduza o código atribuído pela escola.
                                </div>
                            </div>


                            <div class="mb-4">
                                <label for="data_nascimento" class="form-label">
                                    Data de nascimento
                                    <span class="required">*</span>
                                </label>

                                <div class="input-with-icon">
                                    <i class="bi bi-calendar3"></i>

                                    <input type="date"
                                           class="form-control"
                                           id="data_nascimento"
                                           name="data_nascimento"
                                           required>
                                </div>

                                <div class="form-text">
                                    Utilizada como informação adicional de confirmação.
                                </div>
                            </div>


                            <button type="submit"
                                    class="btn btn-primary btn-submit"
                                    id="consultButton">

                                <span class="button-text">
                                    Consultar documento
                                    <i class="bi bi-arrow-right ms-2"></i>
                                </span>

                                <span class="button-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-2"
                                          aria-hidden="true"></span>
                                    A processar...
                                </span>
                            </button>

                            <p class="form-privacy">
                                <i class="bi bi-lock-fill"></i>
                                Só são apresentados documentos emitidos após validação administrativa.
                            </p>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>


    <!-- SOBRE -->
    <section class="about-section section-padding" id="sobre">
        <div class="container">

            <div class="row align-items-center gy-4">

                <div class="col-lg-6">
                    <div class="about-visual">
                        <div class="about-main-icon">
                            <i class="bi bi-mortarboard-fill"></i>
                        </div>

                        <div class="about-decoration decoration-one"></div>
                        <div class="about-decoration decoration-two"></div>

                        <div class="about-caption">
                            <strong>SGECE</strong>
                            <span>Gestão e emissão de certificados escolares</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <span class="section-label">Sobre o SGECE</span>

                    <h2>Uma plataforma para facilitar a gestão documental escolar.</h2>

                    <p>
                        O Sistema de Gestão e Emissão de Certificados Escolares
                        (SGECE) foi concebido para apoiar a gestão dos documentos
                        escolares e facilitar a consulta da sua disponibilidade.
                    </p>

                    <p>
                        A plataforma procura melhorar a organização da informação,
                        apoiar as instituições de ensino e proporcionar aos alunos
                        e cidadãos um meio simples de consulta.
                    </p>

                    <div class="about-feature">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Consulta de disponibilidade de documentos</span>
                    </div>

                    <div class="about-feature">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Validação através de código próprio</span>
                    </div>

                    <div class="about-feature">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Informação centralizada e organizada</span>
                    </div>
                </div>

            </div>
        </div>
    </section>


    <!-- CHAMADA FINAL -->
    <section class="final-cta">
        <div class="container">
            <div class="cta-content">
                <div>
                    <span>SGECE — Serviços escolares</span>
                    <h2>Precisa de consultar um documento?</h2>
                    <p>Utilize o portal para verificar a sua disponibilidade.</p>
                </div>

                <a href="#consulta" class="btn btn-light btn-lg">
                    Fazer consulta
                    <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </section>

</main>


<!-- RODAPÉ -->
<footer class="site-footer">
    <div class="container">

        <div class="row gy-4">

            <div class="col-lg-5">
                <a class="navbar-brand brand footer-brand"
                   href="<?= $escape($appBaseUrl) ?>/">

                    <span class="brand-icon">
                        <i class="bi bi-mortarboard-fill"></i>
                    </span>

                    <span class="brand-text">
                        <strong>SGECE</strong>
                        <small>Gestão Escolar</small>
                    </span>
                </a>

                <p class="footer-description">
                    Sistema de Gestão e Emissão de Certificados Escolares.
                    Uma plataforma de apoio à gestão documental das instituições
                    de ensino.
                </p>
            </div>

            <div class="col-6 col-lg-3">
                <h4>Links úteis</h4>

                <ul class="footer-links">
                    <li><a href="#inicio">Página inicial</a></li>
                    <li><a href="#servicos">Serviços</a></li>
                    <li><a href="#consulta">Consultar documento</a></li>
                    <li><a href="#sobre">Sobre o sistema</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-4">
                <h4>Serviços</h4>

                <ul class="footer-links">
                    <li><a href="#consulta">Certificados escolares</a></li>
                    <li><a href="#consulta">Declarações escolares</a></li>
                    <li>
                        <a href="<?= $escape($appBaseUrl) ?>/validar-documento">
                            Validação de documentos
                        </a>
                    </li>
                </ul>
            </div>

        </div>

        <div class="footer-bottom">
            <span>
                &copy; <?= date('Y') ?> SGECE. Todos os direitos reservados.
            </span>

            <span>Gestão e emissão de certificados escolares</span>
        </div>

    </div>
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="<?= $escape($publicBaseUrl) ?>/assets/js/portal.js"></script>

</body>
</html>