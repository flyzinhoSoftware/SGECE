<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$statusClasses = [
    'pendente' => 'warning',
    'em_analise' => 'info',
    'aprovado' => 'primary',
    'emitido' => 'success',
    'rejeitado' => 'danger'
];
$typeLabels = [
    'certificado' => 'Certificado',
    'declaracao' => 'Declaração anual',
    'declaracao_notas' => 'Declaração de notas',
    'declaracoes' => 'Todas as declarações'
];
?>
<section>
    <div class="page-heading">
        <div>
            <span class="page-eyebrow">MÓDULO ADMINISTRATIVO</span>
            <h1><?= $escape($pageTitle ?? 'Pedidos de documentos') ?></h1>
            <p>Analise os pedidos submetidos pelas escolas e registe a decisão administrativa.</p>
        </div>
    </div>

    <?php if ($flash !== null): ?>
        <div class="alert alert-<?= $escape($flash['type'] ?? 'info') ?>" role="status">
            <?= $escape($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <form method="get" action="<?= $escape($basePath) ?>/admin/pedidos-documentos"
          class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="tipo">Tipo de documento</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <option value="">Todos</option>
                        <?php foreach ($typeLabels as $type => $label): ?>
                            <option value="<?= $escape($type) ?>"
                                <?= $selectedType === $type ? 'selected' : '' ?>>
                                <?= $escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="estado">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="">Todos</option>
                        <?php foreach ($statusLabels as $status => $label): ?>
                            <option value="<?= $escape($status) ?>"
                                <?= $selectedStatus === $status ? 'selected' : '' ?>>
                                <?= $escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary" type="submit">Filtrar</button>
                </div>
            </div>
        </div>
    </form>

    <?php if ($requests === []): ?>
        <div class="alert alert-info" role="status">
            Não há pedidos que correspondam aos filtros seleccionados.
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Escola e aluno</th>
                            <th>Documento e motivo</th>
                            <th>Submetido em</th>
                            <th>Estado e decisão</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?= $escape($request['codigo']) ?></strong>
                                    <div class="small text-muted">Por <?= $escape($request['solicitante_nome']) ?></div>
                                </td>
                                <td>
                                    <strong><?= $escape($request['escola_nome']) ?></strong>
                                    <div><?= $escape($request['aluno_nome']) ?></div>
                                    <div class="small text-muted"><?= $escape($request['codigo_central']) ?></div>
                                </td>
                                <td>
                                    <span class="badge text-bg-light">
                                        <?= $escape($typeLabels[$request['tipo_documento']] ?? $request['tipo_documento']) ?>
                                    </span>
                                    <div class="small mt-1"><?= $escape($request['motivo']) ?></div>
                                </td>
                                <td><?= $escape($request['solicitado_em']) ?></td>
                                <td style="min-width: 290px;">
                                    <span class="badge text-bg-<?= $escape($statusClasses[$request['estado']] ?? 'secondary') ?>">
                                        <?= $escape($statusLabels[$request['estado']] ?? $request['estado']) ?>
                                    </span>
                                    <?php if (in_array($request['estado'], ['pendente', 'em_analise', 'aprovado'], true)): ?>
                                        <form method="post" action="<?= $escape($basePath) ?>/admin/pedidos-documentos/estado"
                                              class="mt-2">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="pedido_id" value="<?= (int) $request['id'] ?>">
                                            <label class="visually-hidden" for="estado-<?= (int) $request['id'] ?>">
                                                Novo estado do pedido <?= $escape($request['codigo']) ?>
                                            </label>
                                            <select class="form-select form-select-sm mb-2"
                                                    id="estado-<?= (int) $request['id'] ?>" name="estado" required>
                                                <?php if ($request['estado'] === 'pendente'): ?>
                                                    <option value="em_analise">Em análise</option>
                                                    <option value="rejeitado">Rejeitar</option>
                                                <?php elseif ($request['estado'] === 'em_analise'): ?>
                                                    <option value="aprovado">Aprovar para emissão</option>
                                                    <option value="rejeitado">Rejeitar</option>
                                                <?php else: ?>
                                                    <option value="emitido">Emitir documento</option>
                                                    <option value="rejeitado">Rejeitar</option>
                                                <?php endif; ?>
                                            </select>
                                            <label class="visually-hidden" for="resposta-<?= (int) $request['id'] ?>">
                                                Nota para a escola ou motivo de rejeição
                                            </label>
                                            <textarea class="form-control form-control-sm mb-2"
                                                      id="resposta-<?= (int) $request['id'] ?>"
                                                      name="resposta_admin" rows="2" maxlength="500"
                                                      placeholder="Nota para a escola ou motivo de rejeição"></textarea>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">
                                                <?= $request['estado'] === 'aprovado'
                                                    ? 'Emitir documento PDF'
                                                    : 'Registar decisão' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <?php if (!empty($request['resposta_admin'])): ?>
                                            <div class="small mt-2"><?= $escape($request['resposta_admin']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($request['estado'] === 'emitido' && !empty($request['codigo_validacao'])): ?>
                                            <div class="d-flex flex-column gap-1 mt-2">
                                                <a href="<?= $escape($basePath) ?>/admin/pedidos-documentos/documento.pdf?codigo=<?= $escape(urlencode($request['codigo_validacao'])) ?>">
                                                    <i class="bi bi-download me-1"></i>Descarregar PDF
                                                </a>
                                                <a href="<?= $escape($basePath) ?>/validar-documento?codigo=<?= $escape(urlencode($request['codigo_validacao'])) ?>">
                                                    Validar autenticidade
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="form-text mt-2">
            A aprovação envia uma nota à escola e libera a emissão. O documento é gerado
            com o modelo oficial, guardado no pedido e recebe um código público de validação.
        </p>
    <?php endif; ?>
</section>
