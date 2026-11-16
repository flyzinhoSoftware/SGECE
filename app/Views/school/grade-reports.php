<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<section>
    <div class="page-heading d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <span class="page-eyebrow">GESTÃO ESCOLAR</span>
            <h1>Pautas existentes</h1>
            <p>
                Consulte as pautas agrupadas por classe e filtre por turma, disciplina e período.
                Ano lectivo activo: <?= $year === null ? 'indisponível' : (int) $year['ano'] ?>.
            </p>
        </div>
        <a class="btn btn-primary" href="<?= $escape($basePath) ?>/escola/notas/lancar">
            <i class="bi bi-plus-circle me-1"></i>Lançar pauta
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
        <form method="get" action="<?= $escape($basePath) ?>/escola/notas"
              class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label" for="turma_id">Turma</label>
                        <select class="form-select" id="turma_id" name="turma_id">
                            <option value="">Todas as turmas</option>
                            <?php foreach ($turmas as $item): ?>
                                <option value="<?= (int) $item['id'] ?>"
                                    <?= $turmaId === (int) $item['id'] ? 'selected' : '' ?>>
                                    <?= $escape($item['classe_nome']) ?> · <?= $escape($item['identificacao']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="disciplina_id">Disciplina</label>
                        <select class="form-select" id="disciplina_id" name="disciplina_id">
                            <option value="">Todas as disciplinas</option>
                            <?php foreach ($disciplines as $item): ?>
                                <option value="<?= (int) $item['id'] ?>"
                                    <?= $disciplineId === (int) $item['id'] ? 'selected' : '' ?>>
                                    <?= $escape($item['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="periodo">Período</label>
                        <select class="form-select" id="periodo" name="periodo">
                            <option value="todos" <?= $periodFilter === 'todos' ? 'selected' : '' ?>>
                                Todos os períodos
                            </option>
                            <?php foreach ([
                                '1' => '1.º trimestre',
                                '2' => '2.º trimestre',
                                '3' => '3.º trimestre',
                                'anual' => 'Anual'
                            ] as $value => $label): ?>
                                <option value="<?= $escape($value) ?>"
                                    <?= $periodFilter === $value ? 'selected' : '' ?>>
                                    <?= $escape($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="bi bi-funnel me-1"></i>Filtrar
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($periodFilter !== 'anual'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <span class="page-eyebrow">PAUTA COMPLETA</span>
                <h2 class="h5 mb-1">Pautas gerais das turmas</h2>
                <p class="mb-0 text-muted">
                    Cada pauta geral reúne todas as disciplinas da turma, mesmo quando
                    existe um filtro de disciplina seleccionado. Apresenta as três
                    avaliações, a média de cada disciplina e a média final trimestral
                    (média simples das disciplinas), seguida do resultado do estudante;
                    a aprovação exige pelo menos 10 valores.
                </p>
            </div>
            <?php if ($generalReports === []): ?>
                <div class="card-body">
                    <p class="text-muted mb-0">
                        Ainda não existem notas para emitir uma pauta geral.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Turma</th>
                                <th>Trimestre</th>
                                <th>Alunos</th>
                                <th>Disciplinas</th>
                                <th>Médias completas</th>
                                <th>Estado</th>
                                <th class="text-end">Pauta geral</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($generalReports as $report): ?>
                                <?php
                                $expectedGrades = (int) $report['total_alunos']
                                    * (int) $report['total_disciplinas'];
                                $isComplete = $expectedGrades > 0
                                    && (int) $report['pares_completos'] === $expectedGrades;
                                $pdfUrl = $basePath . '/escola/notas/pauta-geral.pdf?'
                                    . http_build_query([
                                        'turma_id' => (int) $report['turma_id'],
                                        'trimestre' => (int) $report['trimestre']
                                    ]);
                                ?>
                                <tr>
                                    <td><?= $escape($report['classe_nome']) ?></td>
                                    <td><?= $escape($report['turma_nome']) ?></td>
                                    <td><?= (int) $report['trimestre'] ?>.º</td>
                                    <td><?= (int) $report['total_alunos'] ?></td>
                                    <td><?= (int) $report['total_disciplinas'] ?></td>
                                    <td>
                                        <?= (int) $report['pares_completos'] ?>
                                        / <?= $expectedGrades ?>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-<?= $isComplete ? 'success' : 'warning' ?>">
                                            <?= $isComplete ? 'Final' : 'Provisória' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= $escape($pdfUrl) ?>">
                                            <i class="bi bi-file-earmark-pdf me-1"></i>Ver pauta geral
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($periodFilter === 'todos' || $periodFilter === 'anual'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <span class="page-eyebrow">ANO LECTIVO</span>
                <h2 class="h5 mb-1">Pautas anuais das turmas</h2>
                <p class="mb-0 text-muted">
                    Apresenta as três avaliações e a média de cada disciplina em todos
                    os trimestres, além da média final anual do estudante e do resultado
                    de aprovação.
                </p>
            </div>
            <?php if ($annualReports === []): ?>
                <div class="card-body">
                    <p class="text-muted mb-0">
                        Ainda não existem notas para emitir uma pauta anual.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Turma</th>
                                <th>Alunos</th>
                                <th>Disciplinas</th>
                                <th class="text-end">Pauta anual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($annualReports as $report): ?>
                                <?php
                                $pdfUrl = $basePath . '/escola/notas/pauta-anual.pdf?'
                                    . http_build_query([
                                        'turma_id' => (int) $report['turma_id']
                                    ]);
                                ?>
                                <tr>
                                    <td><?= $escape($report['classe_nome']) ?></td>
                                    <td><?= $escape($report['turma_nome']) ?></td>
                                    <td><?= (int) $report['total_alunos'] ?></td>
                                    <td><?= (int) $report['total_disciplinas'] ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= $escape($pdfUrl) ?>">
                                            <i class="bi bi-file-earmark-pdf me-1"></i>Ver pauta anual
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($periodFilter !== 'anual'): ?>
        <?php if ($reportGroups === []): ?>
            <div class="alert alert-info" role="status">
                Não existem pautas lançadas para os filtros seleccionados.
            </div>
        <?php else: ?>
            <?php foreach ($reportGroups as $group): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <span class="page-eyebrow">CLASSE</span>
                        <h2 class="h5 mb-0"><?= $escape($group['nome']) ?></h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Turma</th>
                                    <th>Disciplina</th>
                                    <th>Trimestre</th>
                                    <th>Alunos com notas</th>
                                    <th>Estado da pauta</th>
                                    <th class="text-end">Pauta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group['pautas'] as $report): ?>
                                    <?php
                                    $isComplete = (int) $report['alunos_completos']
                                        === (int) $report['total_alunos'];
                                    $pdfUrl = $basePath . '/escola/notas/pauta.pdf?'
                                        . http_build_query([
                                            'classe_id' => (int) $report['classe_id'],
                                            'turma_id' => (int) $report['turma_id'],
                                            'disciplina_id' => (int) $report['disciplina_id'],
                                            'trimestre' => (int) $report['trimestre']
                                        ]);
                                    ?>
                                    <tr>
                                        <td><?= $escape($report['turma_nome']) ?></td>
                                        <td><?= $escape($report['disciplina_nome']) ?></td>
                                        <td><?= (int) $report['trimestre'] ?>.º</td>
                                        <td>
                                            <?= (int) $report['alunos_com_notas'] ?>
                                            / <?= (int) $report['total_alunos'] ?>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-<?= $isComplete ? 'success' : 'warning' ?>">
                                                <?= $isComplete ? 'Final' : 'Provisória' ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="<?= $escape($pdfUrl) ?>">
                                                <i class="bi bi-file-earmark-pdf me-1"></i>Ver pauta
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
