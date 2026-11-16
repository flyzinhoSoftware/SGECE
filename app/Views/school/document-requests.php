<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$statusClasses = [
    'pendente' => 'warning',
    'em_analise' => 'info',
    'emitido' => 'success',
    'rejeitado' => 'danger'
];
?>
<section>
    <div class="page-heading">
        <div>
            <span class="page-eyebrow">GESTÃO ESCOLAR</span>
            <h1>Pedidos de certificados e declarações</h1>
            <p>Submeta pedidos dos alunos da escola e acompanhe a análise administrativa.</p>
        </div>
    </div>

    <?php if ($flash !== null): ?>
        <div class="alert alert-<?= $escape($flash['type'] ?? 'info') ?>" role="status">
            <?= $escape($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Novo pedido</h2>
        </div>
        <div class="card-body">
            <?php if ($students === []): ?>
                <div class="alert alert-info mb-0" role="status">
                    Não existem alunos associados a esta escola para seleccionar.
                </div>
            <?php else: ?>
                <form method="post" action="<?= $escape($basePath) ?>/escola/pedidos-documentos">
                    <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <label class="form-label" for="aluno_escola_id">Aluno</label>
                            <select class="form-select" name="aluno_escola_id" id="aluno_escola_id" required>
                                <option value="">Seleccione um aluno</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= (int) $student['aluno_escola_id'] ?>">
                                        <?= $escape($student['nome_completo']) ?>
                                        (<?= $escape($student['codigo_central']) ?>)
                                        <?= $student['associacao_estado'] === 'activo' ? '' : ' — Histórico' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label" for="tipo_documento">Documento</label>
                            <select class="form-select" name="tipo_documento" id="tipo_documento" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($documentTypes as $type => $label): ?>
                                    <option value="<?= $escape($type) ?>"><?= $escape($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="motivo">Motivo do pedido</label>
                            <textarea class="form-control" name="motivo" id="motivo"
                                      rows="3" minlength="5" maxlength="500" required></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-send me-1"></i>Submeter pedido
                            </button>
                            <p class="form-text mb-0 mt-2">
                                Depois de submeter, imprima o recibo no histórico. Quando o documento
                                for emitido, o recibo incluirá o código para consulta de autenticidade.
                            </p>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Histórico de pedidos</h2>
        </div>
        <?php if ($requests === []): ?>
            <div class="card-body text-muted">Ainda não foram submetidos pedidos.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Aluno</th>
                            <th>Documento</th>
                            <th>Data</th>
                            <th>Estado</th>
                            <th>Resposta administrativa</th>
                            <th>Recibo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?= $escape($request['codigo']) ?></strong>
                                    <div class="small text-muted"><?= $escape($request['motivo']) ?></div>
                                </td>
                                <td>
                                    <?= $escape($request['nome_completo']) ?>
                                    <div class="small text-muted"><?= $escape($request['codigo_central']) ?></div>
                                </td>
                                <td><?= $escape($documentTypes[$request['tipo_documento']] ?? $request['tipo_documento']) ?></td>
                                <td>
                                    <div>Pedido: <?= $escape($request['solicitado_em']) ?></div>
                                    <?php if (!empty($request['emitido_em'])): ?>
                                        <div class="small text-muted">
                                            Emitido: <?= $escape($request['emitido_em']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= $escape($statusClasses[$request['estado']] ?? 'secondary') ?>">
                                        <?= $escape($statusLabels[$request['estado']] ?? $request['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $escape($request['resposta_admin'] ?: '—') ?>
                                    <?php if ($request['estado'] === 'emitido' && !empty($request['codigo_validacao'])): ?>
                                        <div class="d-flex flex-column gap-1 mt-2">
                                            <div class="small">
                                                Código de validação:
                                                <code><?= $escape($request['codigo_validacao']) ?></code>
                                            </div>
                                            <a href="<?= $escape($basePath) ?>/portal/documento?codigo=<?= $escape(urlencode($request['codigo_validacao'])) ?>">
                                                <i class="bi bi-download me-1"></i>Descarregar PDF
                                            </a>
                                            <a href="<?= $escape($basePath) ?>/validar-documento?codigo=<?= $escape(urlencode($request['codigo_validacao'])) ?>">
                                                Validar autenticidade
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="<?= $escape($basePath) ?>/escola/pedidos-documentos/recibo.pdf?pedido_id=<?= (int) $request['id'] ?>"
                                       target="_blank" rel="noopener">
                                        <i class="bi bi-printer me-1"></i>
                                        <?= $request['estado'] === 'emitido'
                                            ? 'Imprimir recibo de validação'
                                            : 'Imprimir recibo do pedido' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
