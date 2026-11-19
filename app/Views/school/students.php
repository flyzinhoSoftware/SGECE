<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<section>
    <div class="page-heading">
        <div>
            <span class="page-eyebrow">GESTÃO ESCOLAR</span>
            <h1>Alunos</h1>
            <p>Registe alunos e associe-os directamente à sua escola.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="<?= $escape($basePath) ?>/escola/matriculas">
                <i class="bi bi-calendar-plus me-1"></i>Matricular alunos
            </a>
            <button
                class="btn btn-primary"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#schoolStudentFormModal">
                <i class="bi bi-person-plus me-1"></i>Novo aluno
            </button>
        </div>
    </div>

    <?php if ($flash !== null): ?>
        <div class="alert alert-<?= $escape($flash['type'] ?? 'info') ?>" role="status">
            <?= $escape($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <div
        class="school-student-form-modal modal fade"
        id="schoolStudentFormModal"
        tabindex="-1"
        aria-labelledby="schoolStudentFormModalLabel"
        aria-hidden="true"
        data-open-on-load="<?= $errors !== [] ? 'true' : 'false' ?>">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-md-down">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="schoolStudentFormModalLabel">Registar aluno</h2>
                        <p class="mb-0 mt-1 text-muted">Os campos assinalados com * são obrigatórios.</p>
                    </div>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Fechar"></button>
                </div>
                <form action="<?= $escape($basePath) ?>/escola/alunos" method="post">
                    <div class="modal-body">
                        <?php if (isset($errors['form'])): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= $escape($errors['form']) ?>
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="school-student-name">Nome completo *</label>
                                <input class="form-control<?= isset($errors['nome_completo']) ? ' is-invalid' : '' ?>"
                                       id="school-student-name" name="nome_completo" maxlength="180"
                                       value="<?= $escape($formValues['nome_completo']) ?>" required>
                                <?php if (isset($errors['nome_completo'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['nome_completo']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="school-student-birth-date">Data de nascimento *</label>
                                <input class="form-control<?= isset($errors['data_nascimento']) ? ' is-invalid' : '' ?>"
                                       type="date" id="school-student-birth-date" name="data_nascimento"
                                       max="<?= date('Y-m-d') ?>"
                                       value="<?= $escape($formValues['data_nascimento']) ?>" required>
                                <?php if (isset($errors['data_nascimento'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['data_nascimento']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="school-student-sex">Sexo *</label>
                                <select class="form-select<?= isset($errors['sexo']) ? ' is-invalid' : '' ?>"
                                        id="school-student-sex" name="sexo" required>
                                    <option value="">Seleccione</option>
                                    <?php foreach ($sexOptions as $sex): ?>
                                        <option value="<?= $escape($sex) ?>"
                                            <?= $formValues['sexo'] === $sex ? 'selected' : '' ?>>
                                            <?= $escape($sex) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['sexo'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['sexo']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="school-student-nativity">Naturalidade (localidade e província) *</label>
                                <input class="form-control<?= isset($errors['naturalidade']) ? ' is-invalid' : '' ?>"
                                       id="school-student-nativity" name="naturalidade" maxlength="180"
                                       value="<?= $escape($formValues['naturalidade']) ?>" required>
                                <?php if (isset($errors['naturalidade'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['naturalidade']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="school-student-bi">Número do BI *</label>
                                <input class="form-control<?= isset($errors['bi']) ? ' is-invalid' : '' ?>"
                                       id="school-student-bi" name="bi" maxlength="30" autocomplete="off"
                                       value="<?= $escape($formValues['bi']) ?>" required>
                                <small class="form-text">O BI é cifrado e usado para evitar duplicados.</small>
                                <?php if (isset($errors['bi'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['bi']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="school-student-bi-date">Data de emissão do BI</label>
                                <input class="form-control<?= isset($errors['data_emissao_bi']) ? ' is-invalid' : '' ?>"
                                       type="date" id="school-student-bi-date" name="data_emissao_bi"
                                       max="<?= date('Y-m-d') ?>"
                                       value="<?= $escape($formValues['data_emissao_bi']) ?>">
                                <?php if (isset($errors['data_emissao_bi'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['data_emissao_bi']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="school-student-father">Nome do pai</label>
                                <input class="form-control<?= isset($errors['nome_pai']) ? ' is-invalid' : '' ?>"
                                       id="school-student-father" name="nome_pai" maxlength="150"
                                       value="<?= $escape($formValues['nome_pai']) ?>">
                                <?php if (isset($errors['nome_pai'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['nome_pai']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="school-student-mother">Nome da mãe</label>
                                <input class="form-control<?= isset($errors['nome_mae']) ? ' is-invalid' : '' ?>"
                                       id="school-student-mother" name="nome_mae" maxlength="150"
                                       value="<?= $escape($formValues['nome_mae']) ?>">
                                <?php if (isset($errors['nome_mae'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['nome_mae']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="school-student-contact">Contacto</label>
                                <input class="form-control<?= isset($errors['contacto']) ? ' is-invalid' : '' ?>"
                                       id="school-student-contact" name="contacto" maxlength="30"
                                       value="<?= $escape($formValues['contacto']) ?>">
                                <?php if (isset($errors['contacto'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['contacto']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="school-student-address">Endereço</label>
                                <input class="form-control<?= isset($errors['endereco']) ? ' is-invalid' : '' ?>"
                                       id="school-student-address" name="endereco" maxlength="255"
                                       value="<?= $escape($formValues['endereco']) ?>">
                                <?php if (isset($errors['endereco'])): ?>
                                    <div class="invalid-feedback"><?= $escape($errors['endereco']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-lg me-1"></i>Registar aluno
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Alunos associados à escola</h2>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Nascimento</th>
                            <th>Naturalidade</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students === []): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                Ainda não há alunos associados a esta escola.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <strong><?= $escape($student['nome_completo']) ?></strong>
                                        <div class="small text-muted"><?= $escape($student['codigo_central']) ?></div>
                                    </td>
                                    <td><?= $escape(date('d/m/Y', strtotime($student['data_nascimento']))) ?></td>
                                    <td><?= $escape($student['naturalidade'] ?? '—') ?></td>
                                    <td><?= $student['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?></td>
                                    <td class="text-end">
                                        <?php if ($student['estado'] === 'activo'): ?>
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="<?= $escape($basePath) ?>/escola/matriculas?aluno_id=<?= (int) $student['id'] ?>">
                                                Matricular
                                            </a>
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
            <?php if (count($students) === 100): ?>
                <p class="form-text mt-2">A lista apresenta os primeiros 100 alunos associados.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
(() => {
    const showRegistrationModal = () => {
        const modalElement = document.getElementById('schoolStudentFormModal');
        if (!modalElement) {
            return;
        }

        modalElement.addEventListener('show.bs.modal', () => {
            document.body.classList.add('school-student-modal-open');
        });
        modalElement.addEventListener('hidden.bs.modal', () => {
            document.body.classList.remove('school-student-modal-open');
        });

        if (
            modalElement.dataset.openOnLoad === 'true' &&
            window.bootstrap &&
            window.bootstrap.Modal
        ) {
            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showRegistrationModal, { once: true });
    } else {
        showRegistrationModal();
    }
})();
</script>
