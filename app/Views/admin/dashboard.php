<?php
$nomeApresentacao = htmlspecialchars(
    $primeiroNome ?? 'Utilizador',
    ENT_QUOTES,
    'UTF-8'
);
$dashboardEscape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$documentTypeLabels = [
    'certificado' => 'Certificado',
    'declaracao' => 'Declaração anual',
    'declaracao_notas' => 'Declaração de notas'
];
$requestStatusLabels = [
    'pendente' => 'Pendente',
    'em_analise' => 'Em análise',
    'aprovado' => 'Aprovado para emissão',
    'emitido' => 'Emitido',
    'rejeitado' => 'Rejeitado'
];
$requestStatusClasses = [
    'pendente' => 'secondary',
    'em_analise' => 'warning',
    'aprovado' => 'primary',
    'emitido' => 'success',
    'rejeitado' => 'danger'
];
?>

<div class="dashboard-page">

    <!-- CABEÇALHO -->
    <section class="page-heading">

        <div>
            <span class="page-eyebrow">VISÃO GERAL</span>

            <h1>Painel de controlo</h1>

            <p>
                Bem-vindo, <?= $nomeApresentacao ?>.
                Aqui poderá acompanhar as actividades do SGECE.
            </p>
        </div>

        <div class="page-date">
            <i class="bi bi-calendar3"></i>
            <?= date('d/m/Y') ?>
        </div>

    </section>


    <!-- INDICADORES -->
    <section class="dashboard-stats">

        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-icon stat-blue">
                    <i class="bi bi-buildings"></i>
                </div>

                <span class="stat-tag">Escolas</span>
            </div>

            <h2><?= number_format((int) ($totalSchools ?? 0), 0, ',', '.') ?></h2>
            <p>Total de escolas registadas</p>

            <div class="stat-footer">
                <span>Registos institucionais</span>
            </div>
        </article>


        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-icon stat-purple">
                    <i class="bi bi-people"></i>
                </div>

                <span class="stat-tag">Alunos</span>
            </div>

            <h2><?= number_format((int) ($totalStudents ?? 0), 0, ',', '.') ?></h2>
            <p>Alunos activos registados</p>

            <div class="stat-footer">
                <span>Registos activos</span>
            </div>
        </article>


        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-icon stat-green">
                    <i class="bi bi-file-earmark-check"></i>
                </div>

                <span class="stat-tag">Certificados</span>
            </div>

            <h2><?= number_format((int) ($issuedCertificateCount ?? 0), 0, ',', '.') ?></h2>
            <p>Certificados emitidos</p>

            <div class="stat-footer">
                <span>Documentos disponíveis</span>
            </div>
        </article>


        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-icon stat-orange">
                    <i class="bi bi-file-earmark-text"></i>
                </div>

                <span class="stat-tag">Declarações</span>
            </div>

            <h2><?= number_format((int) ($issuedDeclarationCount ?? 0), 0, ',', '.') ?></h2>
            <p>Declarações emitidas</p>

            <div class="stat-footer">
                <span>Documentos disponíveis</span>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-icon stat-green">
                    <i class="bi bi-file-earmark-check"></i>
                </div>

                <span class="stat-tag">Documentos</span>
            </div>

            <h2><?= number_format((int) ($approvedDocumentCount ?? 0), 0, ',', '.') ?></h2>
            <p>Aprovados para emissão</p>

            <div class="stat-footer">
                <a href="<?= htmlspecialchars($approvedDocumentsUrl, ENT_QUOTES, 'UTF-8') ?>">
                    Rever e emitir
                </a>
            </div>
        </article>

    </section>


    <!-- CONTEÚDO PRINCIPAL -->
    <section class="dashboard-grid">

        <!-- MÓDULOS -->
        <div class="dashboard-panel modules-panel">

            <div class="panel-heading">
                <div>
                    <h3>Módulos do sistema</h3>
                    <p>Áreas de gestão do SGECE</p>
                </div>

                <span class="panel-icon">
                    <i class="bi bi-grid"></i>
                </span>
            </div>


            <div class="module-grid">

                <a class="module-item" href="<?= htmlspecialchars($schoolsUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="module-icon module-blue">
                        <i class="bi bi-buildings"></i>
                    </div>

                    <strong>Escolas</strong>
                    <small>Gestão institucional</small>
                </a>


                <a class="module-item" href="<?= htmlspecialchars($moduleUrls['students'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="module-icon module-purple">
                        <i class="bi bi-people"></i>
                    </div>

                    <strong>Alunos</strong>
                    <small>Registo e identificação</small>
                </a>


                <a class="module-item" href="<?= htmlspecialchars($moduleUrls['academic'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="module-icon module-green">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>

                    <strong>Gestão académica</strong>
                    <small>Classes e matrículas</small>
                </a>


                <a class="module-item" href="<?= htmlspecialchars($moduleUrls['certificates'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="module-icon module-orange">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>

                    <strong>Documentos</strong>
                    <small>Certificados e declarações</small>
                </a>


                <a class="module-item" href="<?= htmlspecialchars($moduleUrls['users'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="module-icon module-cyan">
                        <i class="bi bi-person-gear"></i>
                    </div>

                    <strong>Utilizadores</strong>
                    <small>Contas e permissões</small>
                </a>


            </div>

        </div>


        <!-- INFORMAÇÃO -->
        <div class="dashboard-panel information-panel">

            <div class="panel-heading">
                <div>
                    <h3>Informações do sistema</h3>
                    <p>Estado da plataforma</p>
                </div>

                <span class="panel-icon">
                    <i class="bi bi-info-circle"></i>
                </span>
            </div>


            <div class="information-item">
                <div class="information-icon">
                    <i class="bi bi-database-check"></i>
                </div>

                <div>
                    <strong>Base de dados</strong>
                    <small>Ligação activa</small>
                </div>
            </div>


            <div class="information-item">
                <div class="information-icon">
                    <i class="bi bi-person-lock"></i>
                </div>

                <div>
                    <strong>Controlo de acesso</strong>
                    <small>Acesso autenticado</small>
                </div>
            </div>


            <div class="information-item">
                <div class="information-icon">
                    <i class="bi bi-arrow-repeat"></i>
                </div>

                <div>
                    <strong>Dados do painel</strong>
                    <small>Actualizados a cada acesso</small>
                </div>
            </div>


            <div class="information-notice">
                <i class="bi bi-lightbulb"></i>

                <p>
                    Os indicadores são actualizados com os registos de alunos,
                    escolas e pedidos de documentos.
                </p>
            </div>

        </div>

    </section>

    <section class="dashboard-panel recent-requests-panel">
        <div class="panel-heading">
            <div>
                <h3>Pedidos recentes</h3>
                <p>Últimos pedidos de documentos registados no sistema</p>
            </div>
            <a class="btn btn-sm btn-outline-primary"
               href="<?= $dashboardEscape($documentRequestsUrl ?? '#') ?>">
                Ver todos
            </a>
        </div>

        <?php if (($recentRequests ?? []) === []): ?>
            <p class="text-muted mb-0">Ainda não existem pedidos de documentos.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Protocolo</th>
                            <th>Documento</th>
                            <th>Escola</th>
                            <th>Data do pedido</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRequests as $request): ?>
                            <?php
                            $requestStatus = (string) $request['estado'];
                            $statusClass = $requestStatusClasses[$requestStatus] ?? 'secondary';
                            ?>
                            <tr>
                                <td><strong><?= $dashboardEscape($request['codigo']) ?></strong></td>
                                <td><?= $dashboardEscape(
                                    $documentTypeLabels[$request['tipo_documento']]
                                        ?? $request['tipo_documento']
                                ) ?></td>
                                <td><?= $dashboardEscape($request['escola_nome']) ?></td>
                                <td><?= $dashboardEscape($request['solicitado_em']) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= $dashboardEscape($statusClass) ?>">
                                        <?= $dashboardEscape(
                                            $requestStatusLabels[$requestStatus] ?? $requestStatus
                                        ) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>


    <!-- BLOCO INFERIOR -->
    <section class="dashboard-welcome">

        <div class="welcome-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>

        <div class="welcome-content">
            <span>PLATAFORMA SGECE</span>

            <h3>Gestão escolar centralizada.</h3>

            <p>
                Utilize o painel para gerir as actividades da instituição,
                acompanhar os registos académicos e controlar a emissão
                de documentos escolares.
            </p>
        </div>

    </section>

</div>