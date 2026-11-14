<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$isEditing = (string) ($formValues['id'] ?? '') !== '';
$paginationQuery = http_build_query(array_filter(
    $filters,
    static function ($value): bool {
        return $value !== '';
    }
));
?>

<div class="users-page">
    <section class="page-heading">
        <div>
            <span class="page-eyebrow">ADMINISTRAÇÃO DO SISTEMA</span>
            <h1>Utilizadores</h1>
            <p>Crie contas, associe utilizadores às escolas e mantenha os níveis de acesso.</p>
        </div>
        <button class="btn btn-primary users-new-button" type="button"
                data-bs-toggle="modal" data-bs-target="#usersFormModal"
                data-user-new>
            <i class="bi bi-person-plus"></i>
            Novo utilizador
        </button>
    </section>

    <?php if (is_array($flash) && isset($flash['message'])): ?>
        <div class="alert alert-<?= $escape($flash['type'] ?? 'info') ?> alert-dismissible fade show"
             role="status">
            <?= $escape($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <section class="users-summary-grid" aria-label="Resumo de utilizadores">
        <article class="users-summary-card">
            <span class="users-summary-icon"><i class="bi bi-people"></i></span>
            <div><small>Total de contas</small><strong><?= (int) $summary['total'] ?></strong></div>
        </article>
        <article class="users-summary-card">
            <span class="users-summary-icon users-summary-active"><i class="bi bi-person-check"></i></span>
            <div><small>Contas activas</small><strong><?= (int) $summary['active'] ?></strong></div>
        </article>
        <article class="users-summary-card">
            <span class="users-summary-icon users-summary-inactive"><i class="bi bi-person-x"></i></span>
            <div><small>Contas inactivas</small><strong><?= (int) $summary['inactive'] ?></strong></div>
        </article>
        <article class="users-summary-card">
            <span class="users-summary-icon users-summary-schools"><i class="bi bi-building"></i></span>
            <div><small>Contas de escola</small><strong><?= (int) $summary['school_accounts'] ?></strong></div>
        </article>
    </section>

    <section class="dashboard-panel users-list-panel">
        <div class="panel-heading">
            <div>
                <h3>Contas registadas</h3>
                <p><?= (int) $totalUsers ?> resultado(s)</p>
            </div>
        </div>

        <form class="users-filters" method="get" action="<?= $escape($adminPath) ?>/utilizadores">
            <label class="users-search">
                <span>Pesquisar</span>
                <input class="form-control" type="search" name="q" maxlength="150"
                       value="<?= $escape($filters['q']) ?>" placeholder="Nome, e-mail ou telefone">
            </label>
            <label>
                <span>Distrito</span>
                <select class="form-select" name="distrito" id="users-district-filter">
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
                <select class="form-select" name="escola_id" id="users-school-filter">
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
                <span>Nível de acesso</span>
                <select class="form-select" name="nivel_acesso_id">
                    <option value="">Todos os níveis</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>"
                            <?= $filters['nivel_acesso_id'] === (string) $role['id'] ? 'selected' : '' ?>>
                            <?= $escape($role['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Estado</span>
                <select class="form-select" name="estado">
                    <option value="">Todos os estados</option>
                    <option value="activo" <?= $filters['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= $filters['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </label>
            <div class="users-filter-actions">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <a class="btn btn-link" href="<?= $escape($adminPath) ?>/utilizadores">Limpar</a>
            </div>
        </form>

        <div class="table-responsive users-table-wrap">
            <table class="table users-table">
                <thead>
                    <tr>
                        <th>Utilizador</th><th>Nível</th><th>Escola</th><th>Estado</th>
                        <th>Último acesso</th><th class="text-end">Acções</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users === []): ?>
                        <tr><td class="users-empty" colspan="6">Não foram encontrados utilizadores.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $account): ?>
                            <?php
                            $isSelf = (int) $account['id'] === (int) $currentUserId;
                            $avatar = function_exists('mb_substr')
                                ? mb_substr($account['nome'], 0, 1, 'UTF-8')
                                : substr($account['nome'], 0, 1);
                            $lastAccess = $account['ultimo_acesso'] === null
                                ? 'Nunca acedeu'
                                : date('d/m/Y H:i', strtotime($account['ultimo_acesso']));
                            ?>
                            <tr>
                                <td>
                                    <div class="users-identity">
                                        <span class="users-avatar" aria-hidden="true">
                                            <?= $escape($avatar) ?>
                                        </span>
                                        <span>
                                            <strong><?= $escape($account['nome']) ?><?= $isSelf ? ' (a sua conta)' : '' ?></strong>
                                            <small><?= $escape($account['email']) ?></small>
                                            <?php if ($account['telefone'] !== null && $account['telefone'] !== ''): ?>
                                                <small><?= $escape($account['telefone']) ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="users-role-badge users-role-<?= $escape(strtolower($account['nivel'])) ?>">
                                        <?= $escape($account['nivel']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($account['escola_nome'] !== null): ?>
                                        <strong><?= $escape($account['escola_nome']) ?></strong>
                                        <small><?= $escape($account['escola_distrito']) ?></small>
                                        <?php if ($account['escola_estado'] !== 'activa'): ?>
                                            <small class="users-school-inactive">Escola inactiva</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="users-muted">Sem escola associada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="users-status users-status-<?= $escape($account['estado']) ?>">
                                        <?= $account['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td><?= $escape($lastAccess) ?></td>
                                <td>
                                    <div class="users-row-actions">
                                        <button class="btn btn-sm btn-light users-edit-button" type="button"
                                                data-bs-toggle="modal" data-bs-target="#usersFormModal"
                                                data-user-edit
                                                data-id="<?= (int) $account['id'] ?>"
                                                data-name="<?= $escape($account['nome']) ?>"
                                                data-email="<?= $escape($account['email']) ?>"
                                                data-phone="<?= $escape($account['telefone'] ?? '') ?>"
                                                data-role="<?= (int) $account['nivel_acesso_id'] ?>"
                                                data-school="<?= $escape($account['escola_id'] ?? '') ?>"
                                                data-district="<?= $escape($account['escola_distrito'] ?? '') ?>"
                                                data-self="<?= $isSelf ? 'true' : 'false' ?>"
                                                aria-label="Editar utilizador <?= $escape($account['nome']) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($isSelf): ?>
                                            <button class="btn btn-sm btn-light users-edit-button" type="button"
                                                    disabled title="Não pode desactivar a sua própria conta"
                                                    aria-label="A sua própria conta não pode ser desactivada">
                                                <i class="bi bi-shield-lock"></i>
                                            </button>
                                        <?php else: ?>
                                            <form action="<?= $escape($adminPath) ?>/utilizadores/estado" method="post">
                                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                                                <input type="hidden" name="estado"
                                                       value="<?= $account['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                                <button class="btn btn-sm <?= $account['estado'] === 'activo'
                                                    ? 'btn-outline-danger'
                                                    : 'btn-outline-success' ?>"
                                                        type="submit"
                                                        aria-label="<?= $account['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?> utilizador">
                                                    <i class="bi <?= $account['estado'] === 'activo'
                                                        ? 'bi-person-x'
                                                        : 'bi-person-check' ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="users-pagination" aria-label="Paginação dos utilizadores">
                <span>Página <?= (int) $page ?> de <?= (int) $totalPages ?></span>
                <div>
                    <?php if ($page > 1): ?>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= $escape($adminPath) ?>/utilizadores?<?= $escape($paginationQuery) ?><?= $paginationQuery !== '' ? '&' : '' ?>pagina=<?= (int) ($page - 1) ?>">
                            Anterior
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= $escape($adminPath) ?>/utilizadores?<?= $escape($paginationQuery) ?><?= $paginationQuery !== '' ? '&' : '' ?>pagina=<?= (int) ($page + 1) ?>">
                            Seguinte
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>

    <div class="users-form-modal modal fade" id="usersFormModal" tabindex="-1"
         aria-labelledby="usersFormModalLabel" aria-hidden="true"
         data-open-on-load="<?= $errors !== [] ? 'true' : 'false' ?>"
         data-default-role="<?= $escape($defaultRoleId) ?>"
         data-current-user-id="<?= (int) $currentUserId ?>"
         data-admin-role="<?= $escape($roleIds['Admin'] ?? '') ?>"
         data-school-role="<?= $escape($roleIds['Escola'] ?? '') ?>"
         data-root-role="<?= $escape($roleIds['Root'] ?? '') ?>"
         data-can-manage-root="<?= $canManageRoot ? 'true' : 'false' ?>"
         data-email-domain="sgece.gov.mz">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="usersFormModalLabel">
                            <?= $isEditing ? 'Editar utilizador' : 'Registar utilizador' ?>
                        </h2>
                        <p class="mb-0 mt-1 text-muted">A palavra-passe nunca é apresentada nesta página.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($errors['form']) || isset($errors['id'])): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $escape($errors['form'] ?? $errors['id']) ?>
                        </div>
                    <?php endif; ?>
                    <form id="users-form" action="<?= $escape($adminPath) ?>/utilizadores" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="id" id="users-id"
                               value="<?= $escape($formValues['id']) ?>">
                        <input type="hidden" name="nivel_acesso_id" id="users-role-hidden"
                               value="<?= $escape($formValues['nivel_acesso_id']) ?>">
                        <input type="hidden" name="email_suffix" id="users-email-suffix"
                               value="<?= $escape($formValues['email_suffix']) ?>">
                        <div class="users-form-grid">
                            <label class="users-field">
                                <span>Nome completo *</span>
                                <input class="form-control<?= isset($errors['nome']) ? ' is-invalid' : '' ?>"
                                       type="text" name="nome" id="users-name" maxlength="150"
                                       value="<?= $escape($formValues['nome']) ?>" required>
                                <?php if (isset($errors['nome'])): ?>
                                    <small class="invalid-feedback"><?= $escape($errors['nome']) ?></small>
                                <?php endif; ?>
                            </label>
                            <label class="users-field">
                                <span>E-mail de acesso *</span>
                                <input class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                                       type="email" name="email" id="users-email" maxlength="150"
                                       value="<?= $escape($formValues['email']) ?>" autocomplete="email"
                                       placeholder="Gerado automaticamente" readonly required>
                                <small class="form-text text-muted" id="users-email-help">
                                    Gerado pela inicial do nome, sobrenome e quatro dígitos aleatórios.
                                </small>
                                <?php if (isset($errors['email'])): ?>
                                    <small class="invalid-feedback"><?= $escape($errors['email']) ?></small>
                                <?php endif; ?>
                            </label>
                            <label class="users-field">
                                <span>Telefone *</span>
                                <input class="form-control<?= isset($errors['telefone']) ? ' is-invalid' : '' ?>"
                                       type="text" name="telefone" id="users-phone" maxlength="30"
                                       value="<?= $escape($formValues['telefone']) ?>" required>
                                <?php if (isset($errors['telefone'])): ?>
                                    <small class="invalid-feedback"><?= $escape($errors['telefone']) ?></small>
                                <?php endif; ?>
                            </label>
                            <label class="users-field">
                                <span>Nível de acesso *</span>
                                <select class="form-select<?= isset($errors['nivel_acesso_id']) ? ' is-invalid' : '' ?>"
                                        id="users-role" <?= $canManageRoot ? '' : 'disabled' ?>>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= (int) $role['id'] ?>"
                                                data-role-name="<?= $escape($role['nome']) ?>"
                                            <?= $role['nome'] === 'Escola' ? 'disabled' : '' ?>
                                            <?= (string) $formValues['nivel_acesso_id'] === (string) $role['id']
                                                ? 'selected'
                                                : '' ?>>
                                            <?= $escape($role['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">
                                    O nível Escola é atribuído ao associar uma escola; sem escola, o nível é Admin.
                                </small>
                                <?php if (isset($errors['nivel_acesso_id'])): ?>
                                    <small class="invalid-feedback"><?= $escape($errors['nivel_acesso_id']) ?></small>
                                <?php endif; ?>
                            </label>
                            <div class="users-school-fields"
                                 id="users-school-fields">
                                <p class="users-school-fields-hint">
                                    Associação opcional. A conta será do nível Escola quando uma escola for seleccionada.
                                </p>
                                <label class="users-field">
                                    <span>Distrito *</span>
                                    <select class="form-select" name="distrito" id="users-district">
                                        <option value="">Seleccione o distrito</option>
                                        <?php foreach ($districts as $district): ?>
                                            <option value="<?= $escape($district) ?>"
                                                <?= $formValues['distrito'] === $district ? 'selected' : '' ?>>
                                                <?= $escape($district) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="users-field">
                                    <span>Escola *</span>
                                    <select class="form-select<?= isset($errors['escola_id']) ? ' is-invalid' : '' ?>"
                                            name="escola_id" id="users-school">
                                        <option value="">Seleccione a escola</option>
                                        <?php foreach ($schools as $school): ?>
                                            <option value="<?= (int) $school['id'] ?>"
                                                    data-district="<?= $escape($school['distrito']) ?>"
                                                <?= $formValues['escola_id'] === (string) $school['id']
                                                    ? 'selected'
                                                    : '' ?>>
                                                <?= $escape($school['nome']) ?><?= $school['estado'] !== 'activa'
                                                    ? ' (inactiva)'
                                                    : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['escola_id'])): ?>
                                        <small class="invalid-feedback"><?= $escape($errors['escola_id']) ?></small>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <p class="form-text text-muted users-field-wide mb-0">
                                A palavra-passe inicial usa apenas os dígitos do telefone e é actualizada
                                quando o telefone é alterado.
                            </p>
                        </div>
                        <div class="users-form-actions">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button class="btn btn-primary" type="submit">
                                <?= $isEditing ? 'Guardar alterações' : 'Registar utilizador' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $escape($publicPath) ?>/assets/js/users.js"></script>
