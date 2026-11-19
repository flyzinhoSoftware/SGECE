<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<section>
    <div class="page-heading">
        <div>
            <span class="page-eyebrow">GESTÃO ESCOLAR</span>
            <h1>Matrículas</h1>
            <p>Matricule alunos da sua escola numa classe e turno do ano lectivo activo.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= $escape($basePath) ?>/escola/alunos">
            <i class="bi bi-person-plus me-1"></i>Registar alunos
        </a>
    </div>

    <?php if ($flash !== null): ?>
        <div class="alert alert-<?= $escape($flash['type'] ?? 'info') ?>" role="status">
            <?= $escape($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Nova matrícula</h2>
            <?php if ($year === null): ?>
                <div class="alert alert-warning mb-0" role="status">
                    Não existe um ano lectivo activo. O sistema abre o ano automaticamente
                    a partir de 14 de Fevereiro, desde que o ano anterior esteja concluído.
                    Se este período já começou, confirme com a administração se faltam notas
                    do 3.º trimestre no ano anterior.
                </div>
            <?php elseif ($students === []): ?>
                <div class="alert alert-info mb-0" role="status">
                    Não há alunos activos disponíveis para matrícula neste ano. Registe um aluno
                    ou confirme se já possui matrícula activa.
                </div>
            <?php elseif ($classes === []): ?>
                <div class="alert alert-warning mb-0" role="status">
                    Não existem classes activas para o nível de ensino desta escola.
                </div>
            <?php else: ?>
                <p class="text-muted">Ano lectivo activo: <strong><?= (int) $year['ano'] ?></strong></p>
                <form action="<?= $escape($basePath) ?>/escola/matriculas" method="post">
                    <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="aluno_id">Aluno *</label>
                            <select class="form-select" id="aluno_id" name="aluno_id" required>
                                <option value="">Seleccione um aluno</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= (int) $student['id'] ?>"
                                        <?= $selectedStudentId === (int) $student['id'] ? 'selected' : '' ?>>
                                        <?= $escape($student['nome_completo']) ?> — <?= $escape($student['codigo_central']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="classe_id">Classe *</label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Seleccione uma classe</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= (int) $class['id'] ?>">
                                        <?= $escape($class['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="turno">Turno *</label>
                            <select class="form-select" id="turno" name="turno" required>
                                <option value="">Seleccione um turno</option>
                                <?php foreach ($shiftOptions as $value => $label): ?>
                                    <option value="<?= $escape($value) ?>"><?= $escape($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1 d-grid align-items-end">
                            <button class="btn btn-primary" type="submit" aria-label="Registar matrícula">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </div>
                    </div>
                    <small class="form-text d-block mt-2">
                        A turma e o número de processo são atribuídos automaticamente; cada turma
                        aceita até 50 matrículas activas.
                    </small>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Matrículas da escola</h2>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Processo</th>
                            <th>Aluno</th>
                            <th>Classe / turma</th>
                            <th>Turno</th>
                            <th>Ano</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($enrollments === []): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">
                                Ainda não há matrículas registadas nesta escola.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr>
                                    <td><?= str_pad((string) $enrollment['numero_processo'], 6, '0', STR_PAD_LEFT) ?></td>
                                    <td>
                                        <strong><?= $escape($enrollment['nome_completo']) ?></strong>
                                        <div class="small text-muted"><?= $escape($enrollment['codigo_central']) ?></div>
                                    </td>
                                    <td><?= $escape($enrollment['classe_nome']) ?> — <?= $escape($enrollment['turma_nome']) ?></td>
                                    <td><?= $escape($enrollment['turno'] ?: 'Não definido') ?></td>
                                    <td><?= (int) $enrollment['ano'] ?></td>
                                    <td>
                                        <span class="badge text-bg-<?= $enrollment['estado'] === 'activa' ? 'success' : 'secondary' ?>">
                                            <?= $enrollment['estado'] === 'activa' ? 'Activa' : 'Cancelada' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
