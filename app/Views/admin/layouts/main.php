<?php

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$scriptDir = str_replace(
    '\\',
    '/',
    dirname($_SERVER['SCRIPT_NAME'] ?? '/')
);

$basePath = preg_replace('#/public$#', '', $scriptDir);
$basePath = rtrim($basePath, '/');

$publicPath = $basePath . '/public';

$nomeUsuario = $nomeUsuario ?? 'Utilizador';
$nivelUsuario = $nivelUsuario ?? 'Escola';
$csrfToken = $csrfToken ?? '';
$pageTitle = $pageTitle ?? 'Painel de Controlo';
$activeMenu = $activeMenu ?? 'dashboard';

$primeiroNome = explode(' ', trim($nomeUsuario))[0] ?: 'Utilizador';

$iniciais = strtoupper(
    substr($primeiroNome, 0, 1)
);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $escape($pageTitle) ?> | SGECE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <?php if (in_array($activeMenu, ['students', 'enrollment'], true)): ?>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
              rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
              rel="stylesheet">
    <?php endif; ?>

    <link rel="stylesheet"
          href="<?= $escape($publicPath) ?>/assets/css/admin.css">
</head>

<body>

<div class="admin-wrapper">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="adminSidebar">

        <div class="sidebar-brand">
            <a href="<?= $escape($basePath) ?>/admin/dashboard" class="brand-link">

                <span class="brand-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </span>

                <span class="brand-label">
                    <strong>SGECE</strong>
                    <small>Administração</small>
                </span>

            </a>

            <button type="button"
                    class="sidebar-close"
                    id="sidebarClose"
                    aria-label="Fechar menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>


        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <?= $escape($iniciais) ?>
            </div>

            <div class="sidebar-user-info">
                <strong><?= $escape($nomeUsuario) ?></strong>
                <small><?= $escape($nivelUsuario) ?></small>
            </div>
        </div>


        <div class="sidebar-section-label">MENU PRINCIPAL</div>

        <nav class="sidebar-navigation">

            <a href="<?= $escape($basePath) ?>/admin/dashboard"
               class="sidebar-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">

                <i class="bi bi-grid-1x2-fill"></i>
                <span>Painel de controlo</span>
            </a>


            <div class="sidebar-section-label">GESTÃO ESCOLAR</div>

            <a href="<?= $escape($basePath) ?>/admin/escolas"
               class="sidebar-link <?= $activeMenu === 'schools' ? 'active' : '' ?>">
                <i class="bi bi-buildings"></i>
                <span>Escolas</span>
            </a>

            <a href="<?= $escape($basePath) ?>/admin/alunos"
               class="sidebar-link <?= $activeMenu === 'students' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Alunos</span>
            </a>

            <a href="<?= $escape($basePath) ?>/admin/matriculas"
               class="sidebar-link <?= $activeMenu === 'enrollment' ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i>
                <span>Matrículas</span>
            </a>

            <a href="<?= $escape($basePath) ?>/admin/gestao-academica"
               class="sidebar-link <?= $activeMenu === 'academic' ? 'active' : '' ?>">
                <i class="bi bi-journal-bookmark"></i>
                <span>Gestão académica</span>
            </a>


            <div class="sidebar-section-label">DOCUMENTAÇÃO</div>

            <a href="<?= $escape($basePath) ?>/admin/certificados"
               class="sidebar-link <?= $activeMenu === 'certificates' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i>
                <span>Certificados</span>
            </a>

            <a href="<?= $escape($basePath) ?>/admin/declaracoes"
               class="sidebar-link <?= $activeMenu === 'declarations' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-check"></i>
                <span>Declarações</span>
            </a>

            <a href="<?= $escape($basePath) ?>/admin/validacoes"
               class="sidebar-link <?= $activeMenu === 'validations' ? 'active' : '' ?>">
                <i class="bi bi-patch-check"></i>
                <span>Validações</span>
            </a>


            <div class="sidebar-section-label">SISTEMA</div>

            <a href="<?= $escape($basePath) ?>/admin/utilizadores"
               class="sidebar-link <?= $activeMenu === 'users' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>Utilizadores</span>
            </a>

            <a href="<?= $escape($basePath) ?>/admin/auditoria"
               class="sidebar-link <?= $activeMenu === 'audit' ? 'active' : '' ?>">
                <i class="bi bi-shield-check"></i>
                <span>Auditoria</span>
            </a>

            <a href="<?= $escape($basePath) ?>/admin/configuracoes"
               class="sidebar-link <?= $activeMenu === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Configurações</span>
            </a>

        </nav>


        <div class="sidebar-footer">
            <div class="sidebar-footer-icon">
                <i class="bi bi-shield-lock"></i>
            </div>

            <div>
                <strong>SGECE</strong>
                <small>Ambiente administrativo</small>
            </div>
        </div>

    </aside>


    <!-- ÁREA PRINCIPAL -->
    <div class="admin-main">

        <!-- TOPBAR -->
        <header class="admin-topbar">

            <div class="topbar-left">

                <button type="button"
                        class="sidebar-toggle"
                        id="sidebarToggle"
                        aria-label="Abrir menu"
                        aria-controls="adminSidebar"
                        aria-expanded="false">

                    <i class="bi bi-list"></i>
                </button>

                <div class="breadcrumb-area">
                    <span>SGECE</span>
                    <i class="bi bi-chevron-right"></i>
                    <strong><?= $escape($pageTitle) ?></strong>
                </div>

            </div>


            <div class="topbar-right">

                <div class="system-status">
                    <span class="status-dot"></span>
                    <span>Sistema</span>
                </div>

                <div class="topbar-divider"></div>

                <div class="topbar-user">

                    <div class="topbar-avatar">
                        <?= $escape($iniciais) ?>
                    </div>

                    <div class="topbar-user-info">
                        <strong><?= $escape($nomeUsuario) ?></strong>
                        <small><?= $escape($nivelUsuario) ?></small>
                    </div>

                </div>

                <form action="<?= $escape($basePath) ?>/logout"
                      method="post"
                      class="logout-form">
                    <input type="hidden"
                           name="csrf_token"
                           value="<?= $escape($csrfToken) ?>">
                    <button type="submit"
                            class="logout-button"
                            title="Terminar sessão"
                            aria-label="Terminar sessão">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>

            </div>

        </header>


        <!-- CONTEÚDO -->
        <main class="admin-content">

            <?= $content ?? '' ?>

        </main>


        <!-- FOOTER -->
        <footer class="admin-footer">
            <span>&copy; <?= date('Y') ?> SGECE</span>
            <span>Sistema de Gestão e Emissão de Certificados Escolares</span>
        </footer>

    </div>

</div>


<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php if (in_array($activeMenu, ['students', 'enrollment'], true)): ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="<?= $escape($publicPath) ?>/assets/js/admin.js"></script>

</body>
</html>