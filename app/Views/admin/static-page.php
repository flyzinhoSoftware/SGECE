<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>

<div class="dashboard-page static-admin-page">
    <section class="page-heading">
        <div>
            <span class="page-eyebrow">MÓDULO ADMINISTRATIVO</span>
            <h1><?= $escape($page['title']) ?></h1>
            <p><?= $escape($page['description']) ?></p>
        </div>
        <span class="static-page-badge">
            <i class="bi bi-window"></i>
            Página estática
        </span>
    </section>

    <section class="dashboard-panel static-module-panel">
        <div class="panel-heading">
            <div>
                <h3><?= $escape($page['title']) ?></h3>
                <p>Conteúdo apresentado na área de trabalho do SGECE.</p>
            </div>
            <span class="panel-icon">
                <i class="bi <?= $escape($page['icon']) ?>"></i>
            </span>
        </div>

        <div class="static-module-intro">
            <div class="static-module-icon">
                <i class="bi <?= $escape($page['icon']) ?>"></i>
            </div>
            <div>
                <strong><?= $escape($page['title']) ?></strong>
                <p><?= $escape($page['description']) ?></p>
            </div>
        </div>

        <div class="static-module-items">
            <?php foreach ($page['items'] as $item): ?>
                <div class="static-module-item">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                    <span><?= $escape($item) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="information-notice static-module-notice">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <p>
                Esta página é estática. As operações e os dados deste módulo
                serão integrados numa etapa posterior.
            </p>
        </div>
    </section>
</div>
