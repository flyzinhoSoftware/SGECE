<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$gradeValue = static function ($value): string {
    return $value === null ? '' : number_format((float) $value, 2, '.', '');
};
?>
<section>
    <div class="page-heading d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <span class="page-eyebrow">GESTÃO ESCOLAR</span>
            <h1>Lançamento de pauta</h1>
            <p>
                Seleccione a classe, a turma e a disciplina para lançar as notas.
                A pauta final apresenta os alunos da turma seleccionada. Cada média trimestral
                é a média simples das duas ACS e da avaliação provincial.
                Ano lectivo activo: <?= $year === null ? 'indisponível' : (int) $year['ano'] ?>.
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= $escape($basePath) ?>/escola/notas">
            <i class="bi bi-arrow-left me-1"></i>Ver pautas existentes
        </a>
    </div>

    <?php if ($flash !== null): ?>
        <div class="alert alert-<?= $escape($flash['type'] ?? 'info') ?>" role="status">
            <?= $escape($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php if ($year === null): ?>
        <div class="alert alert-warning" role="status">
            Não existe um ano lectivo activo. Contacte o administrador.
        </div>
    <?php else: ?>
        <form method="get" action="<?= $escape($basePath) ?>/escola/notas/lancar"
              class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label" for="classe_id">1. Classe</label>
                        <select class="form-select" id="classe_id" name="classe_id" required
                            onchange="this.form.submit()"
                            <?= $classes === [] ? 'disabled' : '' ?>>
                            <option value="" disabled <?= $classId === null ? 'selected' : '' ?>>
                                <?= $classes === [] ? 'Sem classes disponíveis' : 'Seleccione uma classe' ?>
                            </option>
                            <?php foreach ($classes as $item): ?>
                                <option value="<?= (int) $item['id'] ?>"
                                    <?= $classId === (int) $item['id'] ? 'selected' : '' ?>>
                                    <?= $escape($item['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="turma_id">2. Turma</label>
                        <select class="form-select" id="turma_id" name="turma_id" required
                            onchange="this.form.submit()"
                            <?= $turmas === [] ? 'disabled' : '' ?>>
                            <option value="" disabled <?= $turmaId === null ? 'selected' : '' ?>>
                                <?= $class === null
                                    ? 'Seleccione primeiro uma classe'
                                    : ($turmas === [] ? 'Sem turmas disponíveis' : 'Seleccione uma turma') ?>
                            </option>
                            <?php foreach ($turmas as $item): ?>
                                <option value="<?= (int) $item['id'] ?>"
                                    <?= $turmaId === (int) $item['id'] ? 'selected' : '' ?>>
                                    <?= $escape($item['identificacao']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="disciplina_id">3. Disciplina</label>
                        <select class="form-select" id="disciplina_id" name="disciplina_id" required
                            <?= $disciplines === [] ? 'disabled' : '' ?>>
                            <option value="" disabled <?= $disciplineId === null ? 'selected' : '' ?>>
                                <?= $turma === null
                                    ? 'Seleccione primeiro uma turma'
                                    : ($disciplines === [] ? 'Sem disciplinas disponíveis' : 'Seleccione uma disciplina') ?>
                            </option>
                            <?php foreach ($disciplines as $item): ?>
                                <option value="<?= (int) $item['id'] ?>"
                                    <?= $disciplineId === (int) $item['id'] ? 'selected' : '' ?>>
                                    <?= $escape($item['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <label class="form-label" for="trimestre">4. Trimestre</label>
                        <select class="form-select" id="trimestre" name="trimestre" required>
                            <?php foreach ([1, 2, 3] as $term): ?>
                                <option value="<?= $term ?>" <?= $quarter === $term ? 'selected' : '' ?>>
                                    <?= $term ?>.º trimestre
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1 d-grid">
                        <button class="btn btn-primary" type="submit">Abrir</button>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($classes === []): ?>
            <div class="alert alert-info" role="status">
                Não há matrículas activas neste ano lectivo.
            </div>
        <?php elseif ($class === null): ?>
            <div class="alert alert-info" role="status">
                Seleccione uma classe para ver as respectivas turmas.
            </div>
        <?php elseif ($turmas === []): ?>
            <div class="alert alert-info" role="status">
                Não há turmas activas com alunos matriculados nesta classe.
            </div>
        <?php elseif ($turma === null): ?>
            <div class="alert alert-info" role="status">
                Seleccione uma turma para ver as disciplinas disponíveis.
            </div>
        <?php elseif ($disciplines === []): ?>
            <div class="alert alert-info" role="status">
                Não há disciplinas associadas à classe seleccionada.
            </div>
        <?php elseif ($discipline === null): ?>
            <div class="alert alert-info" role="status">
                Seleccione uma disciplina para abrir a pauta da turma.
            </div>
        <?php elseif ($students === []): ?>
            <div class="alert alert-info" role="status">
                Não há alunos matriculados nesta turma para os filtros seleccionados.
            </div>
        <?php else: ?>
            <form method="post" action="<?= $escape($basePath) ?>/escola/notas/lancar">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="ano_id" value="<?= (int) $year['id'] ?>">
                <input type="hidden" name="classe_id" value="<?= (int) $classId ?>">
                <input type="hidden" name="turma_id" value="<?= (int) $turmaId ?>">
                <input type="hidden" name="trimestre" value="<?= $quarter ?>">
                <input type="hidden" name="disciplina_id" value="<?= (int) $disciplineId ?>">

            <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                            <strong><?= $escape($class['nome']) ?> · Turma <?= $escape($turma['identificacao']) ?> · <?= $escape($discipline['nome']) ?></strong>
                            <div class="text-muted small">
                                <?= (int) $year['ano'] ?> · <?= $quarter ?>.º trimestre
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($hasExistingGrades): ?>
                            <a class="btn btn-outline-primary"
                               href="<?= $escape($basePath) ?>/escola/notas/pauta.pdf?classe_id=<?= (int) $classId ?>&amp;turma_id=<?= (int) $turmaId ?>&amp;disciplina_id=<?= (int) $disciplineId ?>&amp;trimestre=<?= $quarter ?>">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Emitir pauta da turma
                            </a>
                            <?php endif; ?>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-save me-1"></i>Guardar avaliações
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Aluno</th>
                                    <th>Código</th>
                                    <th>ACS 1</th>
                                    <th>ACS 2</th>
                                    <th>Avaliação provincial</th>
                                    <th>Média trimestral</th>
                                    <th>Média anual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?= $escape($student['nome_completo']) ?></td>
                                        <td><?= $escape($student['codigo_central']) ?></td>
                                        <?php foreach ([
                                            'acs1' => 'ACS 1',
                                            'acs2' => 'ACS 2',
                                            'avaliacao_provincial' => 'Avaliação provincial'
                                        ] as $field => $label): ?>
                                            <td>
                                                <input class="form-control" type="number"
                                                       name="notas[<?= (int) $student['matricula_id'] ?>][<?= $escape($field) ?>]"
                                                       min="0" max="20" step="0.01"
                                                       value="<?= $escape($gradeValue($student[$field])) ?>"
                                                       aria-label="<?= $escape($label) ?> de <?= $escape($student['nome_completo']) ?>">
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="fw-semibold">
                                            <?= $student['media_trimestre'] === null
                                                ? '—'
                                                : $escape($student['media_trimestre']) ?>
                                        </td>
                                        <td>
                                            <?= $student['media_anual'] === null
                                                ? '—'
                                                : $escape($student['media_anual']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white small text-muted">
                        A média trimestral só aparece com as três avaliações. A média anual
                        só aparece quando os três trimestres têm média.
                        <?php if (!$reportIsComplete): ?>
                            <strong>A pauta PDF será identificada como provisória enquanto houver notas em falta.</strong>
                        <?php endif; ?>
                    </div>
            </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
