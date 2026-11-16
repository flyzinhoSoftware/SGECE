<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<section class="school-dashboard">
    <div class="page-heading">
        <div>
            <span class="page-eyebrow">ÁREA DA ESCOLA</span>
            <h1>Painel da escola</h1>
            <p>Bem-vindo(a), <?= $escape($user['nome']) ?>.</p>
        </div>
    </div>

    <div class="users-summary-grid">
            <article class="users-summary-card">
                <span class="users-summary-icon users-summary-schools">
                    <i class="bi bi-people"></i>
                </span>
                <div>
                    <small>ALUNOS ACTIVOS</small>
                    <strong><?= (int) $summary['alunos'] ?></strong>
                </div>
            </article>
            <article class="users-summary-card">
                <span class="users-summary-icon users-summary-active">
                    <i class="bi bi-journal-check"></i>
                </span>
                <div>
                    <small>MATRÍCULAS ACTIVAS</small>
                    <strong><?= (int) $summary['matriculas'] ?></strong>
                </div>
            </article>
            <article class="users-summary-card">
                <span class="users-summary-icon users-summary-active">
                    <i class="bi bi-journal-check"></i>
                </span>
                <div>
                    <small>NOTAS REGISTADAS</small>
                    <strong><?= (int) $summary['notas'] ?></strong>
                </div>
            </article>
            <article class="users-summary-card">
                <span class="users-summary-icon users-summary-schools">
                    <i class="bi bi-inboxes"></i>
                </span>
                <div>
                    <small>PEDIDOS PENDENTES</small>
                    <strong><?= (int) $summary['pedidos'] ?></strong>
                </div>
            </article>
    </div>

    <section class="card border-0 shadow-sm mt-4" aria-labelledby="school-info-heading">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-building text-primary"></i>
                <h2 class="h5 mb-0" id="school-info-heading"><?= $escape($user['escola_nome']) ?></h2>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-3">Nível de ensino</dt>
                <dd class="col-sm-9"><?= $escape($user['escola_tipo']) ?></dd>
                <dt class="col-sm-3">Província</dt>
                <dd class="col-sm-9"><?= $escape($user['escola_provincia']) ?></dd>
                <dt class="col-sm-3">Distrito</dt>
                <dd class="col-sm-9"><?= $escape($user['escola_distrito']) ?></dd>
                <?php if (!empty($user['escola_localidade'])): ?>
                    <dt class="col-sm-3">Localidade</dt>
                    <dd class="col-sm-9"><?= $escape($user['escola_localidade']) ?></dd>
                <?php endif; ?>
                <dt class="col-sm-3">Endereço</dt>
                <dd class="col-sm-9"><?= $escape($user['escola_endereco']) ?></dd>
                <dt class="col-sm-3">Telefone</dt>
                <dd class="col-sm-9"><?= $escape($user['escola_telefone']) ?></dd>
                <dt class="col-sm-3">E-mail</dt>
                <dd class="col-sm-9"><?= $escape($user['escola_email']) ?></dd>
            </dl>
        </div>
    </section>

    <section class="card border-0 shadow-sm mt-4" aria-labelledby="school-actions-heading">
        <div class="card-body p-4">
            <h2 class="h5 mb-3" id="school-actions-heading">Gestão da escola</h2>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="<?= $escape($basePath) ?>/escola/notas">
                    <i class="bi bi-journal-check me-1"></i>Registar notas
                </a>
                <a class="btn btn-outline-primary" href="<?= $escape($basePath) ?>/escola/pedidos-documentos">
                    <i class="bi bi-file-earmark-text me-1"></i>Pedir certificado ou declaração
                </a>
            </div>
        </div>
    </section>
</section>
