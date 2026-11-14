<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$isEditing = (string) ($formValues['id'] ?? '') !== '';
$statusOptions = [
    'todas' => 'Todos os estados',
    'activa' => 'Activas',
    'inactiva' => 'Inactivas'
];
$currentProvince = (string) ($formValues['provincia'] ?? '');
$provinceOptions = ['Zambézia' => 'Zambézia'];
if ($currentProvince !== '' && !isset($provinceOptions[$currentProvince])) {
    $provinceOptions = [
        $currentProvince => $currentProvince . ' (valor actual)'
    ] + $provinceOptions;
}
$currentDistrict = (string) ($formValues['distrito'] ?? '');
$districtOptions = array_combine($districts, $districts);
if ($currentDistrict !== '' && !in_array($currentDistrict, $districtOptions, true)) {
    $districtOptions = [
        $currentDistrict => $currentDistrict . ' (valor actual)'
    ] + $districtOptions;
}
$currentLevel = (string) ($formValues['tipo'] ?? '');
$levelOptions = $schoolLevels;
if ($currentLevel !== '' && !isset($levelOptions[$currentLevel])) {
    $levelOptions[$currentLevel] = $currentLevel . ' (valor actual)';
}
$paginationQuery = http_build_query(array_filter([
    'q' => $search,
    'estado' => $statusFilter === 'todas' ? null : $statusFilter
], static fn ($value): bool => $value !== null && $value !== ''));
?>

<div class="schools-page">
    <section class="page-heading">
        <div>
            <span class="page-eyebrow">GESTÃO ESCOLAR</span>
            <h1>Escolas</h1>
            <p>Registe e mantenha os dados institucionais das escolas.</p>
        </div>

        <button
            class="btn btn-primary schools-new-button"
            type="button"
            data-bs-toggle="modal"
            data-bs-target="#schoolFormModal">
            <i class="bi bi-plus-lg"></i>
            Nova escola
        </button>
    </section>

    <?php if (is_array($flash) && isset($flash['message'])): ?>
        <div class="alert alert-<?= $escape($flash['type'] ?? 'info') ?>" role="status">
            <?= $escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div
        class="school-form-modal modal fade"
        id="schoolFormModal"
        tabindex="-1"
        aria-labelledby="schoolFormModalLabel"
        aria-hidden="true"
        data-open-on-load="<?= $isEditing || $errors !== [] ? 'true' : 'false' ?>">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="schoolFormModalLabel">
                            <?= $isEditing ? 'Editar escola' : 'Registar escola' ?>
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
                        id="school-form"
                        action="<?= $escape($adminPath) ?>/escolas"
                        method="post"
                        data-school-editing="<?= $isEditing ? 'true' : 'false' ?>"
                        novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= $escape($formValues['id'] ?? '') ?>">

                        <div class="schools-form-grid">
                <div class="schools-field">
                    <label for="school-codigo" class="form-label">Código da escola</label>
                    <input
                        id="school-codigo"
                        class="form-control"
                        type="text"
                        name="codigo"
                        maxlength="120"
                        value="<?= $escape($formValues['codigo'] ?? '') ?>"
                        placeholder="Preencha o nome e o nível da escola"
                        readonly>
                    <small id="school-code-help" class="form-text">
                        Código formado pelas iniciais, nível e sufixo hexadecimal.
                    </small>
                </div>

                <?php
                $fields = [
                    'nome' => ['Nome da escola *', 180, true],
                    'localidade' => ['Localidade', 120, false],
                    'endereco' => ['Endereço *', 255, true],
                    'telefone' => ['Telefone *', 30, true]
                ];
                foreach ($fields as $name => [$label, $maximum, $required]):
                    $hasError = isset($errors[$name]);
                    ?>
                    <div class="schools-field">
                        <label for="school-<?= $escape($name) ?>" class="form-label">
                            <?= $escape($label) ?>
                        </label>
                        <input
                            id="school-<?= $escape($name) ?>"
                            class="form-control<?= $hasError ? ' is-invalid' : '' ?>"
                            type="text"
                            name="<?= $escape($name) ?>"
                            value="<?= $escape($formValues[$name] ?? '') ?>"
                            maxlength="<?= (int) $maximum ?>"
                            <?= $required ? 'required' : '' ?>>
                        <?php if ($hasError): ?>
                            <div class="invalid-feedback"><?= $escape($errors[$name]) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="schools-field">
                    <label for="school-email" class="form-label">E-mail institucional *</label>
                    <input
                        id="school-email"
                        class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                        type="email"
                        value="<?= $escape($formValues['email'] ?? '') ?>"
                        placeholder="Gerado automaticamente pelo nome e distrito"
                        required
                        readonly>
                    <small class="form-text">
                        Usa o acrónimo e o distrito; um número é acrescentado se a combinação já existir.
                    </small>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?= $escape($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="schools-field">
                    <label for="school-tipo" class="form-label">Nível da escola *</label>
                    <select
                        id="school-tipo"
                        class="form-select<?= isset($errors['tipo']) ? ' is-invalid' : '' ?>"
                        name="tipo"
                        required>
                        <option value="">Seleccione o nível</option>
                        <?php foreach ($levelOptions as $value => $label): ?>
                            <option value="<?= $escape($value) ?>"
                                <?= $currentLevel === $value ? 'selected' : '' ?>>
                                <?= $escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['tipo'])): ?>
                        <div class="invalid-feedback"><?= $escape($errors['tipo']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="schools-field">
                    <label for="school-provincia" class="form-label">Província *</label>
                    <select
                        id="school-provincia"
                        class="form-select<?= isset($errors['provincia']) ? ' is-invalid' : '' ?>"
                        name="provincia"
                        required>
                        <option value="">Seleccione a província</option>
                        <?php foreach ($provinceOptions as $value => $label): ?>
                            <option value="<?= $escape($value) ?>"
                                <?= $currentProvince === $value ? 'selected' : '' ?>>
                                <?= $escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['provincia'])): ?>
                        <div class="invalid-feedback"><?= $escape($errors['provincia']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="schools-field">
                    <label for="school-distrito" class="form-label">Distrito *</label>
                    <select
                        id="school-distrito"
                        class="form-select<?= isset($errors['distrito']) ? ' is-invalid' : '' ?>"
                        name="distrito"
                        required>
                        <option value="">Seleccione o distrito</option>
                        <?php foreach ($districtOptions as $value => $label): ?>
                            <option value="<?= $escape($value) ?>"
                                <?= $currentDistrict === $value ? 'selected' : '' ?>>
                                <?= $escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['distrito'])): ?>
                        <div class="invalid-feedback"><?= $escape($errors['distrito']) ?></div>
                    <?php endif; ?>
                </div>
                        </div>

                        <div class="modal-footer px-0 pb-0">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-check-lg"></i>
                                <?= $isEditing ? 'Guardar alterações' : 'Registar escola' ?>
                            </button>
                            <?php if ($isEditing): ?>
                                <a class="btn btn-outline-secondary" href="<?= $escape($adminPath) ?>/escolas">
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
                <h3>Escolas registadas</h3>
                <p><?= number_format($totalSchools, 0, ',', '.') ?> registo(s) encontrados</p>
            </div>
            <span class="panel-icon"><i class="bi bi-buildings"></i></span>
        </div>

        <form class="schools-filters" action="<?= $escape($adminPath) ?>/escolas" method="get">
            <label class="schools-search">
                <span class="visually-hidden">Pesquisar escolas</span>
                <i class="bi bi-search" aria-hidden="true"></i>
                <input
                    class="form-control"
                    type="search"
                    name="q"
                    value="<?= $escape($search) ?>"
                    maxlength="150"
                    placeholder="Código, escola, província ou distrito">
            </label>

            <label>
                <span class="visually-hidden">Filtrar por estado</span>
                <select class="form-select" name="estado">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= $escape($value) ?>"
                            <?= $statusFilter === $value ? 'selected' : '' ?>>
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button class="btn btn-outline-primary" type="submit">Filtrar</button>
            <?php if ($search !== '' || $statusFilter !== 'todas'): ?>
                <a class="btn btn-link" href="<?= $escape($adminPath) ?>/escolas">Limpar</a>
            <?php endif; ?>
        </form>

        <div class="table-responsive schools-table-wrap">
            <table class="table align-middle schools-table">
                <thead>
                    <tr>
                        <th scope="col">Escola</th>
                        <th scope="col">Código</th>
                        <th scope="col">Localização</th>
                        <th scope="col">Contacto</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="text-end">Acções</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($schools === []): ?>
                        <tr>
                            <td colspan="6" class="schools-empty">
                                <i class="bi bi-building" aria-hidden="true"></i>
                                <strong>Nenhuma escola encontrada</strong>
                                <span>Ajuste os filtros ou registe uma nova escola.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($schools as $school): ?>
                            <tr>
                                <td>
                                    <strong><?= $escape($school['nome']) ?></strong>
                                    <small><?= $escape($schoolLevels[$school['tipo']] ?? $school['tipo']) ?></small>
                                </td>
                                <td><span class="school-code"><?= $escape($school['codigo']) ?></span></td>
                                <td>
                                    <strong><?= $escape($school['provincia']) ?></strong>
                                    <small><?= $escape($school['distrito']) ?></small>
                                </td>
                                <td>
                                    <span><?= $escape($school['telefone'] ?? '—') ?></span>
                                    <small><?= $escape($school['email'] ?? '—') ?></small>
                                </td>
                                <td>
                                    <span class="badge rounded-pill <?= $school['estado'] === 'activa' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= $school['estado'] === 'activa' ? 'Activa' : 'Inactiva' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="schools-row-actions">
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="<?= $escape($adminPath) ?>/escolas?editar=<?= (int) $school['id'] ?>"
                                            aria-label="Editar <?= $escape($school['nome']) ?>"
                                            title="Editar escola">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="<?= $escape($adminPath) ?>/escolas/estado" method="post">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $school['id'] ?>">
                                            <input
                                                type="hidden"
                                                name="estado"
                                                value="<?= $school['estado'] === 'activa' ? 'inactiva' : 'activa' ?>">
                                            <button
                                                class="btn btn-sm <?= $school['estado'] === 'activa' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                type="submit"
                                                aria-label="<?= $school['estado'] === 'activa' ? 'Desactivar' : 'Activar' ?> <?= $escape($school['nome']) ?>"
                                                title="<?= $school['estado'] === 'activa' ? 'Desactivar escola' : 'Activar escola' ?>">
                                                <i class="bi <?= $school['estado'] === 'activa' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
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
            <nav class="schools-pagination" aria-label="Paginação das escolas">
                <span>Página <?= (int) $page ?> de <?= (int) $totalPages ?></span>
                <div>
                    <?php if ($page > 1): ?>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= $escape($adminPath) ?>/escolas?<?= $escape($paginationQuery) ?>&pagina=<?= (int) ($page - 1) ?>">
                            Anterior
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= $escape($adminPath) ?>/escolas?<?= $escape($paginationQuery) ?>&pagina=<?= (int) ($page + 1) ?>">
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
    const modalElement = document.getElementById('schoolFormModal');
    const form = document.getElementById('school-form');
    const nameInput = document.getElementById('school-nome');
    const levelInput = document.getElementById('school-tipo');
    const districtInput = document.getElementById('school-distrito');
    const codeInput = document.getElementById('school-codigo');
    const emailInput = document.getElementById('school-email');
    const helpText = document.getElementById('school-code-help');

    if (modalElement && modalElement.dataset.openOnLoad === 'true') {
        const showModal = () => {
            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showModal, { once: true });
        } else {
            showModal();
        }
    }

    if (
        !form ||
        !modalElement ||
        form.dataset.schoolEditing === 'true' ||
        !nameInput ||
        !levelInput ||
        !districtInput ||
        !codeInput ||
        !emailInput ||
        !helpText
    ) {
        return;
    }

    const levelCodes = {
        Primaria: 'PRI',
        Secundaria: 'SEC'
    };

    const getAcronym = (schoolName) => schoolName
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .split(/\s+/)
        .map((word) => word.replace(/[^A-Z]/gi, '').charAt(0))
        .filter((initial) => initial !== '')
        .join('')
        .toUpperCase();

    const updateCode = () => {
        const initials = getAcronym(nameInput.value);
        const levelCode = levelCodes[levelInput.value];

        if (!initials || !levelCode) {
            codeInput.value = '';
            return;
        }

        if (!window.crypto || !window.crypto.getRandomValues) {
            codeInput.value = '';
            helpText.textContent = 'O código será gerado pelo servidor ao guardar.';
            return;
        }

        const bytes = new Uint8Array(8);
        window.crypto.getRandomValues(bytes);
        const hexadecimal = Array.from(bytes, (byte) =>
            byte.toString(16).padStart(2, '0')
        ).join('').toUpperCase();
        const code = `${initials}-${levelCode}-${hexadecimal}`;

        codeInput.value = code;
    };

    const updateEmail = () => {
        const acronym = getAcronym(nameInput.value).toLowerCase();
        const district = districtInput.value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .replace(/[^A-Z0-9]+/gi, '-')
            .replace(/^-|-$/g, '')
            .toLowerCase();

        emailInput.value = acronym && district
            ? `${acronym}-${district}@sgece.gov.mz`
            : '';
    };

    nameInput.addEventListener('input', () => {
        updateCode();
        updateEmail();
    });
    levelInput.addEventListener('change', updateCode);
    districtInput.addEventListener('change', updateEmail);
    updateCode();
    updateEmail();

    modalElement.addEventListener('hidden.bs.modal', () => {
        if (form.dataset.schoolEditing !== 'true') {
            form.reset();
            updateCode();
            updateEmail();
        }
    });
})();
</script>
