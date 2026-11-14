
<?php
$scriptDir = str_replace(
    '\\',
    '/',
    dirname($_SERVER['SCRIPT_NAME'] ?? '/')
);
$appBaseUrl = rtrim((string) preg_replace('#/public$#', '', $scriptDir), '/');
$publicPath = $appBaseUrl . '/public';
$csrfToken  = $csrfToken ?? '';
$erro        = $erro ?? null;

function loginEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar no Sistema | SGECE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link rel="stylesheet" href="<?= loginEscape($publicPath) ?>/assets/css/login.css">
</head>
<body>

<main class="login-page">
    <section class="login-wrapper">

        <div class="login-card">

            <header class="login-header">
                <div class="brand-emblem">
                    <img
                        src="<?= loginEscape($publicPath) ?>/assets/img/brasao-mocambique.png"
                        alt="Brasão da República de Moçambique"
                        onerror="this.style.display='none'; document.getElementById('fallbackEmblem').style.display='flex';"
                    >
                    <div id="fallbackEmblem" class="emblem-fallback">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                </div>

                <h1>Sistema de Certificação</h1>
                <p>Gestão e Emissão de Certificados Escolares</p>
            </header>

            <div class="login-body">

                <?php if (!empty($erro)): ?>
                    <div class="alert alert-danger login-alert" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <?= loginEscape($erro) ?>
                    </div>
                <?php endif; ?>

                <form action="<?= loginEscape($appBaseUrl) ?>/admin"
                      method="POST"
                      id="loginForm">

                    <input type="hidden" name="csrf_token"
                           value="<?= loginEscape($csrfToken) ?>">

                    <div class="form-group">
                        <label for="email">E-mail</label>

                        <div class="input-wrapper">
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                placeholder="ex: admin@sgece.gov.mz"
                                autocomplete="username"
                                required
                            >
                            <i class="bi bi-person"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="senha">Senha</label>

                        <div class="input-wrapper">
                            <input
                                type="password"
                                id="senha"
                                name="password"
                                class="form-control"
                                placeholder="Introduza a sua senha"
                                autocomplete="current-password"
                                required
                            >

                            <button type="button"
                                    class="password-toggle"
                                    id="togglePassword"
                                    aria-label="Mostrar senha">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-login" id="loginButton">
                        <span class="button-text">Entrar no Sistema</span>
                        <span class="button-loading d-none">
                            <span class="spinner-border spinner-border-sm me-2"></span>
                            A processar...
                        </span>
                    </button>
                </form>
            </div>

            <footer class="login-footer">
                <p>© <?= date('Y') ?> SGECE - Sistema de Gestão e Emissão de Certificados Escolares</p>
                <small>Versão 1.0.0 | Acesso Restrito</small>
            </footer>
        </div>

        <div class="language-switch">
            <button type="button" class="active">Português</button>
            <button type="button" disabled>English</button>
        </div>

    </section>
</main>

<script src="<?= loginEscape($publicPath) ?>/assets/js/login.js"></script>
</body>
</html>
