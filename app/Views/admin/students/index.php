<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$isEditing = (string) ($formValues['id'] ?? '') !== '';
$statusOptions = [
    'todas' => 'Todos os estados',
    'activo' => 'Activos',
    'inactivo' => 'Inactivos'
];
$selectedDistrict = (string) ($formValues['distrito'] ?? '');
$selectedSchoolId = (string) ($formValues['escola_id'] ?? '');
$paginationQuery = http_build_query(array_filter([
    'q' => $search,
    'estado' => $statusFilter === 'todas' ? null : $statusFilter
], static fn ($value): bool => $value !== null && $value !== ''));
?>

<div class="schools-page students-page">
    <section class="page-heading">
        <div>
            <span class="page-eyebrow">GESTÃO DE ALUNOS</span>
            <h1>Alunos</h1>
            <p>Registe e mantenha os dados de identificação dos alunos.</p>
        </div>

        <button
            class="btn btn-primary schools-new-button"
            type="button"
            data-bs-toggle="modal"
            data-bs-target="#studentFormModal">
            <i class="bi bi-plus-lg"></i>
            Novo aluno
        </button>
    </section>

    <?php if (is_array($flash) && isset($flash['message'])): ?>
        <div class="alert alert-<?= $escape($flash['type'] ?? 'info') ?>" role="status">
            <?= $escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div
        class="student-form-modal modal fade"
        id="studentFormModal"
        tabindex="-1"
        aria-labelledby="studentFormModalLabel"
        aria-hidden="true"
        data-open-on-load="<?= $isEditing || $errors !== [] ? 'true' : 'false' ?>">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="studentFormModalLabel">
                            <?= $isEditing ? 'Editar aluno' : 'Registar aluno' ?>
                        </h2>
                        <p class="mb-0 mt-1 text-muted">Os campos assinalados com * são obrigatórios.</p>
                    </div>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <?php if (isset($errors['form']) || isset($errors['id'])): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $escape($errors['form'] ?? $errors['id']) ?>
                        </div>
                    <?php endif; ?>

                    <form
                        id="student-form"
                        action="<?= $escape($adminPath) ?>/alunos"
                        method="post"
                        data-student-editing="<?= $isEditing ? 'true' : 'false' ?>"
                        data-school-association-locked="<?= $schoolAssociationLocked ? 'true' : 'false' ?>"
                        novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= $escape($formValues['id'] ?? '') ?>">

                        <div
                            id="school-select2-warning"
                            class="alert alert-warning d-none"
                            role="alert">
                            O Select2 não foi carregado. Pode continuar com as listas simples; se a lista de escolas não actualizar, recarregue a página.
                        </div>

                        <?php if ($schools === []): ?>
                            <div class="alert alert-warning" role="alert">
                                Não existem escolas activas para associar. Registe ou active uma escola antes de continuar.
                            </div>
                        <?php endif; ?>

                        <div class="schools-form-grid">
                            <div class="schools-field">
                                <label for="student-code" class="form-label">Código central</label>
                                <input
                                    id="student-code"
                                    class="form-control"
                                    type="text"
                                    value="<?= $escape($formValues['codigo_central'] ?? '') ?>"
                                    placeholder="Gerado automaticamente ao registar"
                                    readonly>
                            </div>

                            <div class="schools-field">
                                <label for="student-name" class="form-label">Nome completo *</label>
                                <input
                                    id="student-name"
                                    class="form-control<?= isset($errors['nome_completo']) ? ' is-invalid' : '' ?>"
                                    type="text"
                                    name="nome_completo"
                                    value="<?= $escape($formValues['nome_completo'] ?? '') ?>"
                                    maxlength="180"
                                    required>
                                <?php if (isset($errors['nome_completo'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['nome_completo']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="student-birth-date" class="form-label">Data de nascimento *</label>
                                <input
                                    id="student-birth-date"
                                    class="form-control<?= isset($errors['data_nascimento']) ? ' is-invalid' : '' ?>"
                                    type="date"
                                    name="data_nascimento"
                                    value="<?= $escape($formValues['data_nascimento'] ?? '') ?>"
                                    max="<?= date('Y-m-d') ?>"
                                    required>
                                <?php if (isset($errors['data_nascimento'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['data_nascimento']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="student-naturalidade" class="form-label">
                                    Naturalidade (localidade e província) *
                                </label>
                                <input
                                    id="student-naturalidade"
                                    class="form-control<?= isset($errors['naturalidade']) ? ' is-invalid' : '' ?>"
                                    type="text"
                                    name="naturalidade"
                                    value="<?= $escape($formValues['naturalidade'] ?? '') ?>"
                                    maxlength="180"
                                    required>
                                <?php if (isset($errors['naturalidade'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['naturalidade']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="student-sex" class="form-label">Sexo *</label>
                                <select
                                    id="student-sex"
                                    class="form-select<?= isset($errors['sexo']) ? ' is-invalid' : '' ?>"
                                    name="sexo"
                                    required>
                                    <option value="">Seleccione</option>
                                    <?php foreach ($sexOptions as $value => $label): ?>
                                        <option
                                            value="<?= $escape($value) ?>"
                                            <?= (string) ($formValues['sexo'] ?? '') === $value ? 'selected' : '' ?>>
                                            <?= $escape($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['sexo'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['sexo']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="student-district" class="form-label">Distrito da escola *</label>
                                <select
                                    id="student-district"
                                    class="form-select<?= isset($errors['distrito']) ? ' is-invalid' : '' ?>"
                                    <?= $schoolAssociationLocked ? 'disabled' : 'name="distrito" required' ?>>
                                    <option value="">Seleccione o distrito</option>
                                    <?php foreach ($districts as $district): ?>
                                        <option
                                            value="<?= $escape($district) ?>"
                                            <?= $selectedDistrict === $district ? 'selected' : '' ?>>
                                            <?= $escape($district) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($schoolAssociationLocked): ?>
                                    <input type="hidden" name="distrito" value="<?= $escape($selectedDistrict) ?>">
                                    <small class="form-text">A escola associada não pode ser alterada nesta página.</small>
                                <?php endif; ?>
                                <?php if (isset($errors['distrito'])): ?>
                                    <div class="invalid-feedback d-block"><?= $escape($errors['distrito']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="student-school" class="form-label">Escola *</label>
                                <select
                                    id="student-school"
                                    class="form-select<?= isset($errors['escola_id']) ? ' is-invalid' : '' ?>"
                                    <?= $schoolAssociationLocked ? '' : 'name="escola_id" required' ?>
                                    <?= $schoolAssociationLocked || $selectedDistrict === '' ? 'disabled' : '' ?>>
                                    <option value="">Seleccione primeiro o distrito</option>
                                    <?php foreach ($schools as $school): ?>
                                        <?php if (!$schoolAssociationLocked || (string) $school['id'] === $selectedSchoolId): ?>
                                            <option
                                                value="<?= (int) $school['id'] ?>"
                                                data-district="<?= $escape($school['distrito']) ?>"
                                                <?= (string) $school['id'] === $selectedSchoolId ? 'selected' : '' ?>>
                                                <?= $escape($school['nome']) ?> — <?= $escape($school['codigo']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($schoolAssociationLocked): ?>
                                    <input type="hidden" name="escola_id" value="<?= $escape($selectedSchoolId) ?>">
                                <?php endif; ?>
                                <small class="form-text">
                                    <?= $schoolAssociationLocked
                                        ? 'A escola associada fica bloqueada durante a edição.'
                                        : 'As escolas disponíveis dependem do distrito seleccionado.' ?>
                                </small>
                                <?php if (isset($errors['escola_id'])): ?>
                                    <div class="invalid-feedback d-block"><?= $escape($errors['escola_id']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="student-bi" class="form-label">Número do BI <?= $isEditing ? '' : '*' ?></label>
                                <input
                                    id="student-bi"
                                    class="form-control<?= isset($errors['bi']) ? ' is-invalid' : '' ?>"
                                    type="text"
                                    name="bi"
                                    value="<?= $escape($formValues['bi'] ?? '') ?>"
                                    maxlength="30"
                                    autocomplete="off"
                                    spellcheck="false"
                                    placeholder="<?= $isEditing ? 'Deixe vazio para manter o BI actual' : '' ?>"
                                    <?= $isEditing ? '' : 'required' ?>>
                                <?php if ($isEditing): ?>
                                    <small class="form-text">O BI não é apresentado; deixe vazio para o manter.</small>
                                <?php else: ?>
                                    <small class="form-text">O BI é cifrado e usado apenas para detectar duplicados.</small>
                                <?php endif; ?>
                                <?php if (isset($errors['bi'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['bi']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="student-bi-issue-date" class="form-label">Data de emissão do BI</label>
                                <input
                                    id="student-bi-issue-date"
                                    class="form-control<?= isset($errors['data_emissao_bi']) ? ' is-invalid' : '' ?>"
                                    type="date"
                                    name="data_emissao_bi"
                                    value="<?= $escape($formValues['data_emissao_bi'] ?? '') ?>"
                                    max="<?= date('Y-m-d') ?>">
                                <?php if (isset($errors['data_emissao_bi'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['data_emissao_bi']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="student-father" class="form-label">Nome do pai</label>
                                <input
                                    id="student-father"
                                    class="form-control<?= isset($errors['nome_pai']) ? ' is-invalid' : '' ?>"
                                    type="text"
                                    name="nome_pai"
                                    value="<?= $escape($formValues['nome_pai'] ?? '') ?>"
                                    maxlength="150">
                                <?php if (isset($errors['nome_pai'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['nome_pai']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="student-mother" class="form-label">Nome da mãe</label>
                                <input
                                    id="student-mother"
                                    class="form-control<?= isset($errors['nome_mae']) ? ' is-invalid' : '' ?>"
                                    type="text"
                                    name="nome_mae"
                                    value="<?= $escape($formValues['nome_mae'] ?? '') ?>"
                                    maxlength="150">
                                <?php if (isset($errors['nome_mae'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['nome_mae']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="student-contact" class="form-label">Contacto</label>
                                <input
                                    id="student-contact"
                                    class="form-control<?= isset($errors['contacto']) ? ' is-invalid' : '' ?>"
                                    type="tel"
                                    name="contacto"
                                    value="<?= $escape($formValues['contacto'] ?? '') ?>"
                                    maxlength="30">
                                <?php if (isset($errors['contacto'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['contacto']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="student-address" class="form-label">Endereço</label>
                                <input
                                    id="student-address"
                                    class="form-control<?= isset($errors['endereco']) ? ' is-invalid' : '' ?>"
                                    type="text"
                                    name="endereco"
                                    value="<?= $escape($formValues['endereco'] ?? '') ?>"
                                    maxlength="255">
                                <?php if (isset($errors['endereco'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['endereco']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="modal-footer px-0 pb-0">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-check-lg"></i>
                                <?= $isEditing ? 'Guardar alterações' : 'Registar aluno' ?>
                            </button>
                            <?php if ($isEditing): ?>
                                <a class="btn btn-outline-secondary" href="<?= $escape($adminPath) ?>/alunos">
                                    Cancelar edição
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
                                    Cancelar
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <section class="dashboard-panel schools-list-panel">
        <div class="panel-heading schools-list-heading">
            <div>
                <h3>Alunos registados</h3>
                <p><?= number_format($totalStudents, 0, ',', '.') ?> registo(s) encontrados</p>
            </div>
            <span class="panel-icon"><i class="bi bi-people"></i></span>
        </div>

        <form class="schools-filters" action="<?= $escape($adminPath) ?>/alunos" method="get">
            <label class="schools-search">
                <span class="visually-hidden">Pesquisar alunos</span>
                <i class="bi bi-search" aria-hidden="true"></i>
                <input
                    class="form-control"
                    type="search"
                    name="q"
                    value="<?= $escape($search) ?>"
                    maxlength="150"
                    placeholder="Código central ou nome do aluno">
            </label>

            <label>
                <span class="visually-hidden">Filtrar por estado</span>
                <select class="form-select" name="estado">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option
                            value="<?= $escape($value) ?>"
                            <?= $statusFilter === $value ? 'selected' : '' ?>>
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button class="btn btn-outline-primary" type="submit">Filtrar</button>
            <?php if ($search !== '' || $statusFilter !== 'todas'): ?>
                <a class="btn btn-link" href="<?= $escape($adminPath) ?>/alunos">Limpar</a>
            <?php endif; ?>
        </form>

        <div class="table-responsive schools-table-wrap">
            <table class="table align-middle schools-table">
                <thead>
                    <tr>
                        <th scope="col">Aluno</th>
                        <th scope="col">Código central</th>
                        <th scope="col">Escola</th>
                        <th scope="col">Nascimento</th>
                        <th scope="col">Sexo</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="text-end">Acções</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students === []): ?>
                        <tr>
                            <td colspan="7" class="schools-empty">
                                <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
                                <strong>Nenhum aluno encontrado</strong>
                                <span>Ajuste os filtros ou registe um aluno.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <?php
                            $birthDate = \DateTimeImmutable::createFromFormat(
                                '!Y-m-d',
                                (string) $student['data_nascimento']
                            );
                            ?>
                            <tr>
                                <td><strong><?= $escape($student['nome_completo']) ?></strong></td>
                                <td><span class="school-code"><?= $escape($student['codigo_central']) ?></span></td>
                                <td>
                                    <strong><?= $escape($student['escola_nome'] ?? 'Sem escola associada') ?></strong>
                                    <?php if (!empty($student['distrito'])): ?>
                                        <small><?= $escape($student['distrito']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $birthDate ? $escape($birthDate->format('d/m/Y')) : '—' ?></td>
                                <td><?= $escape($student['sexo']) ?></td>
                                <td>
                                    <span class="badge rounded-pill <?= $student['estado'] === 'activo' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= $student['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="schools-row-actions">
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="<?= $escape($adminPath) ?>/alunos?editar=<?= (int) $student['id'] ?>"
                                            aria-label="Editar <?= $escape($student['nome_completo']) ?>"
                                            title="Editar aluno">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="<?= $escape($adminPath) ?>/alunos/estado" method="post">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
                                            <input
                                                type="hidden"
                                                name="estado"
                                                value="<?= $student['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                            <button
                                                class="btn btn-sm <?= $student['estado'] === 'activo' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                type="submit"
                                                aria-label="<?= $student['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?> <?= $escape($student['nome_completo']) ?>"
                                                title="<?= $student['estado'] === 'activo' ? 'Desactivar aluno' : 'Activar aluno' ?>">
                                                <i class="bi <?= $student['estado'] === 'activo' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="schools-pagination" aria-label="Paginação dos alunos">
                <span>Página <?= (int) $page ?> de <?= (int) $totalPages ?></span>
                <div>
                    <?php if ($page > 1): ?>
                        <a
                            class="btn btn-sm btn-outline-secondary"
                            href="<?= $escape($adminPath) ?>/alunos?<?= $escape($paginationQuery) ?>&pagina=<?= (int) ($page - 1) ?>">
                            Anterior
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a
                            class="btn btn-sm btn-outline-secondary"
                            href="<?= $escape($adminPath) ?>/alunos?<?= $escape($paginationQuery) ?>&pagina=<?= (int) ($page + 1) ?>">
                            Seguinte
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</div>

<script>
(() => {
    const initializeStudentForm = () => {
        const modalElement = document.getElementById('studentFormModal');
        const form = document.getElementById('student-form');
        const districtSelect = document.getElementById('student-district');
        const schoolSelect = document.getElementById('student-school');

        if (!modalElement || !form || !districtSelect || !schoolSelect) {
            return;
        }

        const schoolAssociationLocked =
            form.dataset.schoolAssociationLocked === 'true';
        const schoolOptions = Array.from(schoolSelect.options)
            .filter((option) => option.value !== '')
            .map((option) => option.cloneNode(true));
        const initialSchoolId = schoolSelect.value;
        const hasSelect2 = Boolean(window.jQuery && window.jQuery.fn.select2);
        const select2Warning = document.getElementById('school-select2-warning');

        const refreshSchoolOptions = (selectedId) => {
            if (schoolAssociationLocked) {
                return;
            }

            const district = districtSelect.value;
            const matchingSchools = schoolOptions.filter(
                (option) => option.dataset.district === district
            );
            schoolSelect.replaceChildren(new Option(
                district ? 'Seleccione a escola' : 'Seleccione primeiro o distrito',
                ''
            ));
            matchingSchools.forEach((option) => {
                schoolSelect.add(option.cloneNode(true));
            });
            schoolSelect.disabled = district === '' || matchingSchools.length === 0;
            schoolSelect.value = matchingSchools.some(
                (option) => option.value === selectedId
            ) ? selectedId : '';
            schoolSelect.dispatchEvent(new Event('change', { bubbles: true }));
        };

        refreshSchoolOptions(initialSchoolId);

        if (hasSelect2) {
            const $district = window.jQuery(districtSelect);
            const $school = window.jQuery(schoolSelect);
            const dropdownParent = window.jQuery(modalElement);
            $district.select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent,
                placeholder: 'Seleccione o distrito'
            });
            $school.select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent,
                placeholder: 'Seleccione uma escola'
            });
            $district.on('change', () => {
                refreshSchoolOptions('');
            });
        } else if (select2Warning) {
            select2Warning.classList.remove('d-none');
            districtSelect.addEventListener('change', () => {
                refreshSchoolOptions('');
            });
        }

        if (
            modalElement.dataset.openOnLoad === 'true' &&
            window.bootstrap &&
            window.bootstrap.Modal
        ) {
            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }

        modalElement.addEventListener('hidden.bs.modal', () => {
            if (form.dataset.studentEditing !== 'true') {
                form.reset();
                if (hasSelect2) {
                    window.jQuery(districtSelect).trigger('change');
                } else {
                    districtSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeStudentForm, { once: true });
    } else {
        initializeStudentForm();
    }
})();
</script>
