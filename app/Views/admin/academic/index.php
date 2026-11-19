<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$yearStatusLabels = [
    'activo' => 'Activo',
    'planeado' => 'Planeado',
    'encerrado' => 'Encerrado'
];
$statusLabels = [
    'activa' => 'Activa',
    'inactiva' => 'Inactiva'
];
?>

<div class="dashboard-page academic-page">
    <section class="page-heading">
        <div>
            <span class="page-eyebrow">MÓDULO ADMINISTRATIVO</span>
            <h1>Gestão académica</h1>
            <p>Organize anos lectivos, classes e disciplinas e consulte as turmas das escolas.</p>
        </div>
        <div class="academic-heading-actions">
            <button class="btn btn-outline-primary" type="button"
                    data-bs-toggle="modal" data-bs-target="#academicDisciplineModal"
                    data-academic-new="discipline">
                <i class="bi bi-journal-plus"></i>
                Nova disciplina
            </button>
            <button class="btn btn-primary" type="button"
                    data-bs-toggle="modal" data-bs-target="#academicYearModal"
                    data-academic-new="year">
                <i class="bi bi-calendar-plus"></i>
                Novo ano lectivo
            </button>
        </div>
    </section>

    <?php if (is_array($flash) && isset($flash['message'])): ?>
        <div class="alert alert-<?= $escape($flash['type'] ?? 'info') ?> alert-dismissible fade show"
             role="alert">
            <?= $escape($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <section class="academic-summary-grid" aria-label="Resumo académico">
        <article class="academic-summary-card">
            <span class="academic-summary-icon"><i class="bi bi-calendar-check"></i></span>
            <div><small>Anos lectivos activos</small><strong><?= (int) $summary['years'] ?></strong></div>
        </article>
        <article class="academic-summary-card">
            <span class="academic-summary-icon"><i class="bi bi-journal-bookmark"></i></span>
            <div><small>Classes activas</small><strong><?= (int) $summary['classes'] ?></strong></div>
        </article>
        <article class="academic-summary-card">
            <span class="academic-summary-icon"><i class="bi bi-book"></i></span>
            <div><small>Disciplinas activas</small><strong><?= (int) $summary['disciplines'] ?></strong></div>
        </article>
        <article class="academic-summary-card">
            <span class="academic-summary-icon"><i class="bi bi-people"></i></span>
            <div><small>Turmas no filtro</small><strong><?= (int) $summary['turmas'] ?></strong></div>
        </article>
    </section>

    <section class="dashboard-panel academic-panel" id="anos">
        <div class="panel-heading academic-panel-heading">
            <div>
                <h3>Anos lectivos</h3>
                <p>Defina o período escolar e o estado de cada ano.</p>
            </div>
            <button class="btn btn-sm btn-outline-primary" type="button"
                    data-bs-toggle="modal" data-bs-target="#academicYearModal"
                    data-academic-new="year">
                <i class="bi bi-plus-lg"></i> Novo ano
            </button>
        </div>
        <div class="table-responsive academic-table-wrap">
            <table class="table academic-table">
                <thead>
                    <tr><th>Ano</th><th>Período</th><th>Estado</th><th class="text-end">Acções</th></tr>
                </thead>
                <tbody>
                    <?php if ($years === []): ?>
                        <tr><td class="academic-empty" colspan="4">Ainda não existem anos lectivos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($years as $year): ?>
                            <?php
                            $start = $year['data_inicio'] ?? '';
                            $end = $year['data_fim'] ?? '';
                            $period = $start !== '' || $end !== ''
                                ? ($start !== '' ? $start : '—') . ' a ' . ($end !== '' ? $end : '—')
                                : 'Não definido';
                            ?>
                            <tr>
                                <td><strong><?= (int) $year['ano'] ?></strong></td>
                                <td><?= $escape($period) ?></td>
                                <td>
                                    <span class="academic-status academic-status-<?= $escape($year['estado']) ?>">
                                        <?= $escape($yearStatusLabels[$year['estado']] ?? $year['estado']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-light academic-edit-button" type="button"
                                            data-bs-toggle="modal" data-bs-target="#academicYearModal"
                                            data-academic-edit-year
                                            data-id="<?= (int) $year['id'] ?>"
                                            data-year="<?= (int) $year['ano'] ?>"
                                            data-start="<?= $escape($start) ?>"
                                            data-end="<?= $escape($end) ?>"
                                            data-status="<?= $escape($year['estado']) ?>"
                                            aria-label="Editar ano lectivo <?= (int) $year['ano'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="academic-catalog-grid">
        <section class="dashboard-panel academic-panel" id="classes">
            <div class="panel-heading academic-panel-heading">
                <div>
                    <h3>Classes</h3>
                    <p>Associe cada classe ao nível e às respectivas disciplinas.</p>
                </div>
                <button class="btn btn-sm btn-outline-primary" type="button"
                        data-bs-toggle="modal" data-bs-target="#academicClassModal"
                        data-academic-new="class">
                    <i class="bi bi-plus-lg"></i> Nova classe
                </button>
            </div>
            <div class="table-responsive academic-table-wrap">
                <table class="table academic-table">
                    <thead>
                        <tr><th>Classe</th><th>Nível</th><th>Disciplinas</th><th>Estado</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if ($classes === []): ?>
                            <tr><td class="academic-empty" colspan="5">Ainda não existem classes.</td></tr>
                        <?php else: ?>
                            <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td>
                                        <strong><?= $escape($class['nome']) ?></strong>
                                        <small><?= $escape($class['codigo']) ?></small>
                                    </td>
                                    <td><?= $escape($class['nivel_nome']) ?></td>
                                    <td><?= $escape($class['disciplinas'] ?: 'Sem disciplinas') ?></td>
                                    <td>
                                        <span class="academic-status academic-status-<?= $escape($class['estado']) ?>">
                                            <?= $escape($statusLabels[$class['estado']] ?? $class['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light academic-edit-button" type="button"
                                                data-bs-toggle="modal" data-bs-target="#academicClassModal"
                                                data-academic-edit-class
                                                data-id="<?= (int) $class['id'] ?>"
                                                data-level="<?= (int) $class['nivel_ensino_id'] ?>"
                                                data-code="<?= $escape($class['codigo']) ?>"
                                                data-name="<?= $escape($class['nome']) ?>"
                                                data-order="<?= (int) $class['ordem'] ?>"
                                                data-status="<?= $escape($class['estado']) ?>"
                                                data-discipline-ids="<?= $escape($class['disciplina_ids'] ?? '') ?>"
                                                aria-label="Editar classe <?= $escape($class['nome']) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-panel academic-panel" id="disciplinas">
            <div class="panel-heading academic-panel-heading">
                <div>
                    <h3>Disciplinas</h3>
                    <p>Catálogo de disciplinas por nível de ensino.</p>
                </div>
                <button class="btn btn-sm btn-outline-primary" type="button"
                        data-bs-toggle="modal" data-bs-target="#academicDisciplineModal"
                        data-academic-new="discipline">
                    <i class="bi bi-plus-lg"></i> Nova disciplina
                </button>
            </div>
            <div class="table-responsive academic-table-wrap">
                <table class="table academic-table">
                    <thead>
                        <tr><th>Disciplina</th><th>Nível</th><th>Classes</th><th>Estado</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if ($disciplines === []): ?>
                            <tr><td class="academic-empty" colspan="5">Ainda não existem disciplinas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($disciplines as $discipline): ?>
                                <tr>
                                    <td>
                                        <strong><?= $escape($discipline['nome']) ?></strong>
                                        <small><?= $escape($discipline['codigo']) ?></small>
                                    </td>
                                    <td><?= $escape($discipline['nivel_nome']) ?></td>
                                    <td><?= (int) $discipline['total_classes'] ?></td>
                                    <td>
                                        <span class="academic-status academic-status-<?= $escape($discipline['estado']) ?>">
                                            <?= $escape($statusLabels[$discipline['estado']] ?? $discipline['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light academic-edit-button" type="button"
                                                data-bs-toggle="modal" data-bs-target="#academicDisciplineModal"
                                                data-academic-edit-discipline
                                                data-id="<?= (int) $discipline['id'] ?>"
                                                data-level="<?= (int) $discipline['nivel_ensino_id'] ?>"
                                                data-code="<?= $escape($discipline['codigo']) ?>"
                                                data-name="<?= $escape($discipline['nome']) ?>"
                                                data-status="<?= $escape($discipline['estado']) ?>"
                                                aria-label="Editar disciplina <?= $escape($discipline['nome']) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="dashboard-panel academic-panel" id="turmas">
        <div class="panel-heading">
            <div>
                <h3>Turmas por escola</h3>
                <p>As turmas são geradas automaticamente durante a matrícula; aqui pode consultar e filtrar os registos.</p>
            </div>
            <span class="academic-total"><?= (int) $totalTurmas ?> registos</span>
        </div>

        <form class="academic-filters" method="get" action="<?= $escape($adminPath) ?>/gestao-academica#turmas">
            <label>
                <span>Distrito</span>
                <select class="form-select" name="distrito" id="academic-district-filter">
                    <option value="">Todos os distritos</option>
                    <?php foreach ($districts as $district): ?>
                        <option value="<?= $escape($district) ?>"
                            <?= $filters['distrito'] === $district ? 'selected' : '' ?>>
                            <?= $escape($district) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Escola</span>
                <select class="form-select" name="escola_id" id="academic-school-filter">
                    <option value="">Todas as escolas</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?= (int) $school['id'] ?>"
                                data-district="<?= $escape($school['distrito']) ?>"
                            <?= $filters['escola_id'] === (string) $school['id'] ? 'selected' : '' ?>>
                            <?= $escape($school['nome']) ?> — <?= $escape($school['distrito']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Ano lectivo</span>
                <select class="form-select" name="ano_id">
                    <option value="">Todos os anos</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= (int) $year['id'] ?>"
                            <?= $filters['ano_id'] === (string) $year['id'] ? 'selected' : '' ?>>
                            <?= (int) $year['ano'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Nível</span>
                <select class="form-select" name="nivel_id" id="academic-level-filter">
                    <option value="">Todos os níveis</option>
                    <?php foreach ($levels as $level): ?>
                        <option value="<?= (int) $level['id'] ?>"
                            <?= $filters['nivel_id'] === (string) $level['id'] ? 'selected' : '' ?>>
                            <?= $escape($level['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Classe</span>
                <select class="form-select" name="classe_id" id="academic-class-filter">
                    <option value="">Todas as classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= (int) $class['id'] ?>"
                                data-level="<?= (int) $class['nivel_ensino_id'] ?>"
                            <?= $filters['classe_id'] === (string) $class['id'] ? 'selected' : '' ?>>
                            <?= $escape($class['nome']) ?> — <?= $escape($class['nivel_nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Estado da turma</span>
                <select class="form-select" name="estado">
                    <option value="">Todos</option>
                    <option value="activa" <?= $filters['estado'] === 'activa' ? 'selected' : '' ?>>Activa</option>
                    <option value="inactiva" <?= $filters['estado'] === 'inactiva' ? 'selected' : '' ?>>Inactiva</option>
                </select>
            </label>
            <div class="academic-filter-actions">
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-link" href="<?= $escape($adminPath) ?>/gestao-academica#turmas">Limpar</a>
            </div>
        </form>

        <div class="table-responsive academic-table-wrap">
            <table class="table academic-table academic-turma-table">
                <thead>
                    <tr>
                        <th>Turma</th><th>Escola</th><th>Distrito</th><th>Ano</th>
                        <th>Nível / classe</th><th>Alunos</th><th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($turmas === []): ?>
                        <tr><td class="academic-empty" colspan="7">Não foram encontradas turmas para estes filtros.</td></tr>
                    <?php else: ?>
                        <?php foreach ($turmas as $turma): ?>
                            <?php
                            $capacity = $turma['capacidade'] === null
                                ? '—'
                                : (int) $turma['capacidade'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= $escape($turma['identificacao']) ?></strong>
                                    <small><?= $escape($turma['turno'] ?: 'Turno não definido') ?></small>
                                </td>
                                <td><?= $escape($turma['escola_nome']) ?></td>
                                <td><?= $escape($turma['distrito']) ?></td>
                                <td><?= (int) $turma['ano'] ?></td>
                                <td>
                                    <?= $escape($turma['nivel_nome']) ?>
                                    <small><?= $escape($turma['classe_nome']) ?></small>
                                </td>
                                <td>
                                    <span class="academic-capacity">
                                        <?= (int) $turma['matriculas_activas'] ?> / <?= $capacity ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="academic-status academic-status-<?= $escape($turma['estado']) ?>">
                                        <?= $escape($statusLabels[$turma['estado']] ?? $turma['estado']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <?php
            $queryFilters = array_filter($filters, static function ($value): bool {
                return $value !== '';
            });
            ?>
            <nav class="academic-pagination" aria-label="Paginação das turmas">
                <span>Página <?= (int) $page ?> de <?= (int) $totalPages ?></span>
                <div>
                    <?php if ($page > 1): ?>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= $escape($adminPath) ?>/gestao-academica?<?= $escape(http_build_query($queryFilters + ['pagina' => $page - 1])) ?>#turmas">
                            Anterior
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= $escape($adminPath) ?>/gestao-academica?<?= $escape(http_build_query($queryFilters + ['pagina' => $page + 1])) ?>#turmas">
                            Seguinte
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</div>

<div class="academic-form-modal modal fade" id="academicYearModal" tabindex="-1"
     aria-labelledby="academicYearModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="academicYearModalLabel">Novo ano lectivo</h2>
                    <p class="mb-0 mt-1 text-muted">
                        O ano abre automaticamente a 14 de Fevereiro, com data final a 20 de Dezembro.
                        O fecho depende das notas completas do 3.º trimestre.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="<?= $escape($adminPath) ?>/gestao-academica/anos" method="post">
                <div class="modal-body academic-modal-grid">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="id" id="academic-year-id">
                    <label class="academic-field">
                        <span>Ano lectivo *</span>
                        <input class="form-control" type="number" name="ano" id="academic-year-value"
                               min="1900" max="2200" required>
                    </label>
                    <label class="academic-field">
                        <span>Data de início</span>
                        <input class="form-control" type="date" name="data_inicio" id="academic-year-start">
                    </label>
                    <label class="academic-field">
                        <span>Data de fim</span>
                        <input class="form-control" type="date" name="data_fim" id="academic-year-end">
                    </label>
                    <label class="academic-field">
                        <span>Estado *</span>
                        <select class="form-select" name="estado" id="academic-year-status" required>
                            <option value="planeado">Planeado</option>
                            <option value="activo">Activo</option>
                            <option value="encerrado">Encerrado</option>
                        </select>
                    </label>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Guardar ano lectivo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="academic-form-modal modal fade" id="academicClassModal" tabindex="-1"
     aria-labelledby="academicClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="academicClassModalLabel">Nova classe</h2>
                    <p class="mb-0 mt-1 text-muted">As disciplinas disponíveis devem pertencer ao mesmo nível da classe.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="<?= $escape($adminPath) ?>/gestao-academica/classes" method="post">
                <div class="modal-body academic-modal-grid">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="id" id="academic-class-id">
                    <label class="academic-field">
                        <span>Nível de ensino *</span>
                        <select class="form-select" name="nivel_ensino_id" id="academic-class-level" required>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?= (int) $level['id'] ?>"><?= $escape($level['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="academic-field">
                        <span>Código *</span>
                        <input class="form-control" type="text" name="codigo" id="academic-class-code"
                               maxlength="30" required>
                    </label>
                    <label class="academic-field">
                        <span>Nome *</span>
                        <input class="form-control" type="text" name="nome" id="academic-class-name"
                               maxlength="100" required>
                    </label>
                    <label class="academic-field">
                        <span>Ordem *</span>
                        <input class="form-control" type="number" name="ordem" id="academic-class-order"
                               min="1" max="100" required>
                    </label>
                    <label class="academic-field">
                        <span>Estado *</span>
                        <select class="form-select" name="estado" id="academic-class-status" required>
                            <option value="activa">Activa</option>
                            <option value="inactiva">Inactiva</option>
                        </select>
                    </label>
                    <label class="academic-field academic-field-wide">
                        <span>Disciplinas</span>
                        <select class="form-select academic-discipline-select" name="disciplinas[]"
                                id="academic-class-disciplines" multiple size="7">
                            <?php foreach ($disciplines as $discipline): ?>
                                <option value="<?= (int) $discipline['id'] ?>"
                                        data-level="<?= (int) $discipline['nivel_ensino_id'] ?>">
                                    <?= $escape($discipline['nome']) ?><?= $discipline['estado'] === 'inactiva' ? ' (inactiva)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Use Ctrl (Windows) ou Command (Mac) para seleccionar várias disciplinas.</small>
                    </label>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Guardar classe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="academic-form-modal modal fade" id="academicDisciplineModal" tabindex="-1"
     aria-labelledby="academicDisciplineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="academicDisciplineModalLabel">Nova disciplina</h2>
                    <p class="mb-0 mt-1 text-muted">O código da disciplina deve ser único dentro do nível de ensino.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="<?= $escape($adminPath) ?>/gestao-academica/disciplinas" method="post">
                <div class="modal-body academic-modal-grid">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="id" id="academic-discipline-id">
                    <label class="academic-field">
                        <span>Nível de ensino *</span>
                        <select class="form-select" name="nivel_ensino_id" id="academic-discipline-level" required>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?= (int) $level['id'] ?>"><?= $escape($level['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="academic-field">
                        <span>Código *</span>
                        <input class="form-control" type="text" name="codigo" id="academic-discipline-code"
                               maxlength="30" required>
                    </label>
                    <label class="academic-field">
                        <span>Nome *</span>
                        <input class="form-control" type="text" name="nome" id="academic-discipline-name"
                               maxlength="120" required>
                    </label>
                    <label class="academic-field">
                        <span>Estado *</span>
                        <select class="form-select" name="estado" id="academic-discipline-status" required>
                            <option value="activa">Activa</option>
                            <option value="inactiva">Inactiva</option>
                        </select>
                    </label>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Guardar disciplina</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= $escape($publicPath ?? '') ?>/assets/js/academic.js"></script>
