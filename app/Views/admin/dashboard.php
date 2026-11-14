<?php
$nomeApresentacao = htmlspecialchars(
    $primeiroNome ?? 'Utilizador',
    ENT_QUOTES,
    'UTF-8'
);
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

            <h2>—</h2>
            <p>Total de alunos registados</p>

            <div class="stat-footer">
                <span>Aguardando integração</span>
            </div>
        </article>


        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-icon stat-green">
                    <i class="bi bi-file-earmark-check"></i>
                </div>

                <span class="stat-tag">Certificados</span>
            </div>

            <h2>—</h2>
            <p>Certificados emitidos</p>

            <div class="stat-footer">
                <span>Aguardando integração</span>
            </div>
        </article>


        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-icon stat-orange">
                    <i class="bi bi-file-earmark-text"></i>
                </div>

                <span class="stat-tag">Declarações</span>
            </div>

            <h2>—</h2>
            <p>Declarações emitidas</p>

            <div class="stat-footer">
                <span>Aguardando integração</span>
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


                <a class="module-item" href="<?= htmlspecialchars($moduleUrls['audit'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="module-icon module-red">
                        <i class="bi bi-shield-check"></i>
                    </div>

                    <strong>Auditoria</strong>
                    <small>Registo de actividades</small>
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
                    <small>Ligação configurada no projecto</small>
                </div>
            </div>


            <div class="information-item">
                <div class="information-icon">
                    <i class="bi bi-person-lock"></i>
                </div>

                <div>
                    <strong>Controlo de acesso</strong>
                    <small>Autenticação necessária</small>
                </div>
            </div>


            <div class="information-item">
                <div class="information-icon">
                    <i class="bi bi-clock-history"></i>
                </div>

                <div>
                    <strong>Auditoria</strong>
                    <small>Módulo ainda por integrar</small>
                </div>
            </div>


            <div class="information-notice">
                <i class="bi bi-lightbulb"></i>

                <p>
                    Os indicadores serão actualizados automaticamente
                    após a integração dos módulos com a base de dados.
                </p>
            </div>

        </div>

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