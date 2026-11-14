<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$statusOptions = [
    'todas' => 'Todos os estados',
    'activa' => 'Activas',
    'cancelada' => 'Canceladas'
];
$paginationQuery = http_build_query(array_filter([
    'q' => $search,
    'estado' => $statusFilter === 'todas' ? null : $statusFilter,
    'ano_id' => $yearFilter === '' ? null : $yearFilter
], static fn ($value): bool => $value !== null && $value !== ''));
?>

<div class="schools-page enrollment-page">
    <section class="page-heading">
        <div>
            <span class="page-eyebrow">GESTÃO ESCOLAR</span>
            <h1>Matrículas</h1>
            <p>Matricule alunos associados a uma escola em turmas e anos lectivos activos.</p>
        </div>

        <button
            class="btn btn-primary schools-new-button"
            type="button"
            data-bs-toggle="modal"
            data-bs-target="#enrollmentFormModal">
            <i class="bi bi-plus-lg"></i>
            Nova matrícula
        </button>
    </section>

    <?php if (is_array($flash) && isset($flash['message'])): ?>
        <div class="alert alert-<?= $escape($flash['type'] ?? 'info') ?>" role="status">
            <?= $escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div
        class="enrollment-form-modal modal fade"
        id="enrollmentFormModal"
        tabindex="-1"
        aria-labelledby="enrollmentFormModalLabel"
        aria-hidden="true"
        data-open-on-load="<?= $errors !== [] ? 'true' : 'false' ?>">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="enrollmentFormModalLabel">
                            Registar matrícula
                        </h2>
                        <p class="mb-0 mt-1 text-muted">O número do processo é gerado automaticamente por escola e ano lectivo.</p>
                    </div>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <?php if (isset($errors['form'])): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $escape($errors['form']) ?>
                        </div>
                    <?php endif; ?>

                    <form
                        id="enrollment-form"
                        action="<?= $escape($adminPath) ?>/matriculas"
                        method="post"
                        novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

                        <div
                            id="enrollment-select2-warning"
                            class="alert alert-warning d-none"
                            role="alert">
                            O Select2 não foi carregado. Pode continuar com as listas simples; se as opções dependentes não actualizarem, recarregue a página.
                        </div>

                        <?php if ($districts === [] || $schools === []): ?>
                            <div class="alert alert-warning" role="alert">
                                Não existem escolas activas com alunos activos associados.
                            </div>
                        <?php endif; ?>
                        <?php if ($students === []): ?>
                            <div class="alert alert-warning" role="alert">
                                Não existem alunos activos associados a escolas activas. Registe um aluno e associe-o a uma escola primeiro.
                            </div>
                        <?php endif; ?>
                        <?php if ($activeYears === []): ?>
                            <div class="alert alert-warning" role="alert">
                                Não existe um ano lectivo activo para registar matrículas.
                            </div>
                        <?php endif; ?>
                        <?php if ($classes === []): ?>
                            <div class="alert alert-warning" role="alert">
                                Não existem classes activas cadastradas.
                            </div>
                        <?php endif; ?>

                        <div class="schools-form-grid">
                            <div class="schools-field">
                                <label for="enrollment-district" class="form-label">Distrito *</label>
                                <select
                                    id="enrollment-district"
                                    class="form-select<?= isset($errors['distrito']) ? ' is-invalid' : '' ?>"
                                    name="distrito"
                                    required>
                                    <option value="">Seleccione o distrito</option>
                                    <?php foreach ($districts as $district): ?>
                                        <option
                                            value="<?= $escape($district) ?>"
                                            <?= (string) ($formValues['distrito'] ?? '') === (string) $district ? 'selected' : '' ?>>
                                            <?= $escape($district) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['distrito'])): ?>
                                    <div class="invalid-feedback d-block"><?= $escape($errors['distrito']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="enrollment-school" class="form-label">Escola *</label>
                                <select
                                    id="enrollment-school"
                                    class="form-select<?= isset($errors['escola_id']) ? ' is-invalid' : '' ?>"
                                    name="escola_id"
                                    required
                                    <?= empty($formValues['distrito']) ? 'disabled' : '' ?>>
                                    <option value="">Seleccione primeiro o distrito</option>
                                    <?php foreach ($schools as $school): ?>
                                        <option
                                            value="<?= (int) $school['id'] ?>"
                                            data-district="<?= $escape($school['distrito']) ?>"
                                            data-level-code="<?= $escape($school['nivel_codigo']) ?>"
                                            <?= (string) ($formValues['escola_id'] ?? '') === (string) $school['id'] ? 'selected' : '' ?>>
                                            <?= $escape($school['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['escola_id'])): ?>
                                    <div class="invalid-feedback d-block"><?= $escape($errors['escola_id']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="enrollment-student" class="form-label">Aluno *</label>
                                <select
                                    id="enrollment-student"
                                    class="form-select<?= isset($errors['aluno_id']) ? ' is-invalid' : '' ?>"
                                    name="aluno_id"
                                    required
                                    disabled>
                                    <option value="">Seleccione primeiro a escola e o ano lectivo</option>
                                    <?php foreach ($students as $student): ?>
                                        <option
                                            value="<?= (int) $student['id'] ?>"
                                            data-school-id="<?= (int) $student['escola_id'] ?>"
                                            data-enrolled-years="<?= $escape($student['matriculated_year_ids']) ?>"
                                            <?= (string) ($formValues['aluno_id'] ?? '') === (string) $student['id'] ? 'selected' : '' ?>>
                                            <?= $escape($student['nome_completo']) ?> — <?= $escape($student['codigo_central']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['aluno_id'])): ?>
                                    <div class="invalid-feedback d-block"><?= $escape($errors['aluno_id']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="enrollment-year" class="form-label">Ano lectivo *</label>
                                <select
                                    id="enrollment-year"
                                    class="form-select<?= isset($errors['ano_lectivo_id']) ? ' is-invalid' : '' ?>"
                                    name="ano_lectivo_id"
                                    data-default-year-id="<?= (int) ($activeYears[0]['id'] ?? 0) ?>"
                                    required>
                                    <option value="">Seleccione o ano lectivo</option>
                                    <?php foreach ($activeYears as $year): ?>
                                        <option
                                            value="<?= (int) $year['id'] ?>"
                                            <?= (string) ($formValues['ano_lectivo_id'] ?? '') === (string) $year['id'] ? 'selected' : '' ?>>
                                            <?= (int) $year['ano'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['ano_lectivo_id'])): ?>
                                    <div class="invalid-feedback d-block"><?= $escape($errors['ano_lectivo_id']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label for="enrollment-class" class="form-label">Classe *</label>
                                <select
                                    id="enrollment-class"
                                    class="form-select<?= isset($errors['classe_id']) ? ' is-invalid' : '' ?>"
                                    name="classe_id"
                                    required
                                    disabled>
                                    <option value="">Seleccione primeiro a escola</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option
                                            value="<?= (int) $class['id'] ?>"
                                            data-level-code="<?= $escape($class['nivel_codigo']) ?>"
                                            <?= (string) ($formValues['classe_id'] ?? '') === (string) $class['id'] ? 'selected' : '' ?>>
                                            <?= $escape($class['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text">As classes são filtradas pelo nível da escola seleccionada.</small>
                                <?php if (isset($errors['classe_id'])): ?>
                                    <div class="invalid-feedback d-block"><?= $escape($errors['classe_id']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="schools-field">
                                <label class="form-label">Turma atribuída automaticamente</label>
                                <input
                                    class="form-control"
                                    type="text"
                                    value="A, B, C… — máximo de <?= (int) $maxStudentsPerClassroom ?> alunos"
                                    readonly>
                                <small class="form-text">A matrícula será colocada na primeira turma com vaga; uma nova turma é criada quando as anteriores atingem o limite.</small>
                            </div>

                            <div class="schools-field">
                                <label class="form-label">Número do processo</label>
                                <input
                                    class="form-control"
                                    type="text"
                                    value="Gerado automaticamente"
                                    readonly>
                                <small class="form-text">Sequência própria da escola e do ano lectivo.</small>
                            </div>
                        </div>

                        <div class="modal-footer px-0 pb-0">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-check-lg"></i>
                                Registar matrícula
                            </button>
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <section class="dashboard-panel schools-list-panel">
        <div class="panel-heading schools-list-heading">
            <div>
                <h3>Registos de matrícula</h3>
                <p><?= number_format($totalEnrollments, 0, ',', '.') ?> registo(s) encontrados</p>
            </div>
            <span class="panel-icon"><i class="bi bi-calendar3"></i></span>
        </div>

        <form class="schools-filters" action="<?= $escape($adminPath) ?>/matriculas" method="get">
            <label class="schools-search">
                <span class="visually-hidden">Pesquisar matrículas</span>
                <i class="bi bi-search" aria-hidden="true"></i>
                <input
                    class="form-control"
                    type="search"
                    name="q"
                    value="<?= $escape($search) ?>"
                    maxlength="150"
                    placeholder="Processo, aluno, código ou escola">
            </label>

            <label>
                <span class="visually-hidden">Filtrar por ano lectivo</span>
                <select class="form-select" name="ano_id">
                    <option value="">Todos os anos</option>
                    <?php foreach ($years as $year): ?>
                        <option
                            value="<?= (int) $year['id'] ?>"
                            <?= (string) $yearFilter === (string) $year['id'] ? 'selected' : '' ?>>
                            <?= (int) $year['ano'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
            <?php if ($search !== '' || $statusFilter !== 'todas' || $yearFilter !== ''): ?>
                <a class="btn btn-link" href="<?= $escape($adminPath) ?>/matriculas">Limpar</a>
            <?php endif; ?>
        </form>

        <div class="table-responsive schools-table-wrap">
            <table class="table align-middle schools-table enrollment-table">
                <thead>
                    <tr>
                        <th scope="col">Processo</th>
                        <th scope="col">Aluno</th>
                        <th scope="col">Escola</th>
                        <th scope="col">Classe / turma</th>
                        <th scope="col">Ano</th>
                        <th scope="col">Data</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="text-end">Acções</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($enrollments === []): ?>
                        <tr>
                            <td colspan="8" class="schools-empty">
                                <i class="bi bi-calendar-x" aria-hidden="true"></i>
                                <strong>Nenhuma matrícula encontrada</strong>
                                <span>Ajuste os filtros ou registe uma matrícula.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($enrollments as $enrollment): ?>
                            <tr>
                                <td>
                                    <span class="school-code">
                                        <?= str_pad((string) $enrollment['numero_processo'], 6, '0', STR_PAD_LEFT) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= $escape($enrollment['nome_completo']) ?></strong>
                                    <small><?= $escape($enrollment['codigo_central']) ?></small>
                                </td>
                                <td>
                                    <strong><?= $escape($enrollment['escola_nome']) ?></strong>
                                    <small><?= $escape($enrollment['distrito']) ?></small>
                                </td>
                                <td>
                                    <strong><?= $escape($enrollment['classe_nome']) ?> — <?= $escape($enrollment['identificacao']) ?></strong>
                                    <?php if (!empty($enrollment['turno'])): ?>
                                        <small><?= $escape($enrollment['turno']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $enrollment['ano'] ?></td>
                                <td><?= $escape(date('d/m/Y', strtotime($enrollment['data_matricula']))) ?></td>
                                <td>
                                    <span class="badge rounded-pill <?= $enrollment['estado'] === 'activa' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= $enrollment['estado'] === 'activa' ? 'Activa' : 'Cancelada' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($enrollment['estado'] === 'activa'): ?>
                                        <form
                                            action="<?= $escape($adminPath) ?>/matriculas/estado"
                                            method="post"
                                            onsubmit="return confirm('Cancelar esta matrícula?')">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $enrollment['id'] ?>">
                                            <button
                                                class="btn btn-sm btn-outline-danger"
                                                type="submit"
                                                title="Cancelar matrícula"
                                                aria-label="Cancelar matrícula de <?= $escape($enrollment['nome_completo']) ?>">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="schools-pagination" aria-label="Paginação das matrículas">
                <span>Página <?= (int) $page ?> de <?= (int) $totalPages ?></span>
                <div>
                    <?php if ($page > 1): ?>
                        <a
                            class="btn btn-sm btn-outline-secondary"
                            href="<?= $escape($adminPath) ?>/matriculas?<?= $escape($paginationQuery) ?>&pagina=<?= (int) ($page - 1) ?>">
                            Anterior
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a
                            class="btn btn-sm btn-outline-secondary"
                            href="<?= $escape($adminPath) ?>/matriculas?<?= $escape($paginationQuery) ?>&pagina=<?= (int) ($page + 1) ?>">
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
    const initializeEnrollmentForm = () => {
        const modalElement = document.getElementById('enrollmentFormModal');
        const form = document.getElementById('enrollment-form');
        const districtSelect = document.getElementById('enrollment-district');
        const schoolSelect = document.getElementById('enrollment-school');
        const studentSelect = document.getElementById('enrollment-student');
        const yearSelect = document.getElementById('enrollment-year');
        const classSelect = document.getElementById('enrollment-class');

        if (
            !modalElement ||
            !form ||
            !districtSelect ||
            !schoolSelect ||
            !studentSelect ||
            !yearSelect ||
            !classSelect
        ) {
            return;
        }

        const originalSchools = Array.from(schoolSelect.options)
            .filter((option) => option.value !== '')
            .map((option) => option.cloneNode(true));
        const originalStudents = Array.from(studentSelect.options)
            .filter((option) => option.value !== '')
            .map((option) => option.cloneNode(true));
        const originalClasses = Array.from(classSelect.options)
            .filter((option) => option.value !== '')
            .map((option) => option.cloneNode(true));
        const initialSchoolId = schoolSelect.value;
        const initialStudentId = studentSelect.value;
        const initialClassId = classSelect.value;
        const defaultYearId = yearSelect.dataset.defaultYearId || '';
        const select2Available = Boolean(window.jQuery && window.jQuery.fn.select2);

        if (select2Available) {
            const dropdownParent = window.jQuery(modalElement);
            [
                districtSelect,
                schoolSelect,
                studentSelect,
                yearSelect,
                classSelect
            ].forEach((select) => {
                window.jQuery(select).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent
                });
            });
        } else {
            const warning = document.getElementById('enrollment-select2-warning');
            if (warning) {
                warning.classList.remove('d-none');
            }
        }

        const updateSelect2 = (select) => {
            if (select2Available) {
                window.jQuery(select).trigger('change.select2');
            }
        };

        const updateSchoolOptions = (selectedSchoolId) => {
            const district = districtSelect.value;
            const matchingSchools = originalSchools.filter(
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
                (option) => option.value === selectedSchoolId
            ) ? selectedSchoolId : '';
            updateSelect2(schoolSelect);
        };

        const updateStudentOptions = (selectedStudentId) => {
            const schoolId = schoolSelect.value;
            const yearId = yearSelect.value;
            const matchingStudents = originalStudents.filter((option) => {
                const enrolledYears = option.dataset.enrolledYears
                    .split(',')
                    .filter((value) => value !== '');
                return option.dataset.schoolId === schoolId &&
                    yearId !== '' &&
                    !enrolledYears.includes(yearId);
            });

            let placeholder = 'Seleccione primeiro a escola e o ano lectivo';
            if (schoolId !== '' && yearId !== '' && matchingStudents.length === 0) {
                placeholder = 'Não há alunos sem matrícula activa neste ano';
            } else if (schoolId !== '' && yearId !== '') {
                placeholder = 'Seleccione um aluno';
            }
            studentSelect.replaceChildren(new Option(placeholder, ''));
            matchingStudents.forEach((option) => {
                studentSelect.add(option.cloneNode(true));
            });
            studentSelect.disabled =
                schoolId === '' || yearId === '' || matchingStudents.length === 0;
            studentSelect.value = matchingStudents.some(
                (option) => option.value === selectedStudentId
            ) ? selectedStudentId : '';
            updateSelect2(studentSelect);
        };

        const updateClassOptions = (selectedClassId) => {
            const selectedSchool = schoolSelect.options[schoolSelect.selectedIndex];
            const levelCode = selectedSchool ? selectedSchool.dataset.levelCode : '';
            const matchingClasses = originalClasses.filter(
                (option) => option.dataset.levelCode === levelCode
            );

            classSelect.replaceChildren(new Option(
                levelCode ? 'Seleccione a classe' : 'Seleccione primeiro a escola',
                ''
            ));
            matchingClasses.forEach((option) => {
                classSelect.add(option.cloneNode(true));
            });
            classSelect.disabled = levelCode === '' || matchingClasses.length === 0;
            classSelect.value = matchingClasses.some(
                (option) => option.value === selectedClassId
            ) ? selectedClassId : '';
            updateSelect2(classSelect);
        };

        updateSchoolOptions(initialSchoolId);
        updateStudentOptions(initialStudentId);
        updateClassOptions(initialClassId);

        const districtChanged = () => {
            updateSchoolOptions('');
            updateStudentOptions('');
            updateClassOptions('');
        };
        const schoolChanged = () => {
            updateStudentOptions('');
            updateClassOptions('');
        };
        const yearChanged = () => {
            updateStudentOptions(studentSelect.value);
        };

        if (select2Available) {
            window.jQuery(districtSelect).on('change', districtChanged);
            window.jQuery(schoolSelect).on('change', schoolChanged);
            window.jQuery(yearSelect).on('change', yearChanged);
        } else {
            districtSelect.addEventListener('change', districtChanged);
            schoolSelect.addEventListener('change', schoolChanged);
            yearSelect.addEventListener('change', yearChanged);
        }

        if (
            modalElement.dataset.openOnLoad === 'true' &&
            window.bootstrap &&
            window.bootstrap.Modal
        ) {
            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }

        modalElement.addEventListener('hidden.bs.modal', () => {
            form.reset();
            districtSelect.value = '';
            schoolSelect.value = '';
            studentSelect.value = '';
            yearSelect.value = defaultYearId;
            classSelect.value = '';
            updateSchoolOptions('');
            updateStudentOptions('');
            updateClassOptions('');
            updateSelect2(districtSelect);
            updateSelect2(yearSelect);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEnrollmentForm, { once: true });
    } else {
        initializeEnrollmentForm();
    }
})();
</script>
