<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$statusClasses = [
    'pendente' => 'warning',
    'em_analise' => 'info',
    'aprovado' => 'primary',
    'emitido' => 'success',
    'rejeitado' => 'danger'
];
?>
<section>
    <div class="page-heading">
        <div>
            <span class="page-eyebrow">GESTÃO ESCOLAR</span>
            <h1>Pedidos de documentos escolares</h1>
            <p>Peça documentos de anos lectivos concluídos ou declarações das notas já completas no ano activo.</p>
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
            <?php if ($eligibleEnrollments === []): ?>
                <div class="alert alert-info mb-0" role="status">
                    Não existem alunos elegíveis. Para anos encerrados, são necessárias notas anuais completas e aprovação; no ano activo, pelo menos um trimestre completo.
                </div>
            <?php else: ?>
                <form method="post" action="<?= $escape($basePath) ?>/escola/pedidos-documentos">
                    <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="tipo_documento" id="tipo_documento" value="">
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label class="form-label" for="classe_id">1. Classe</label>
                            <select class="form-select" name="classe_id" id="classe_id" required>
                                <option value="">Seleccione uma classe</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= (int) $class['id'] ?>">
                                        <?= $escape($class['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="turma_id">2. Turma</label>
                            <select class="form-select" name="turma_id" id="turma_id" required disabled>
                                <option value="">Seleccione primeiro uma classe</option>
                            </select>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="matricula_id">3. Aluno</label>
                            <select class="form-select" name="matricula_id" id="matricula_id" required disabled>
                                <option value="">Seleccione primeiro uma turma</option>
                                <?php foreach ($eligibleEnrollments as $enrollment): ?>
                                    <option value="<?= (int) $enrollment['matricula_id'] ?>"
                                            data-classe-id="<?= (int) $enrollment['classe_id'] ?>"
                                            data-turma-id="<?= (int) $enrollment['turma_id'] ?>"
                                            data-turma-nome="<?= $escape($enrollment['turma_nome']) ?>"
                                            data-ano="<?= (int) $enrollment['ano'] ?>"
                                            data-tipo-documento="<?= $escape($enrollment['tipo_documento']) ?>"
                                            data-trimestres="<?= $escape(implode(',', $enrollment['trimestres_concluidos'])) ?>">
                                        <?= $escape($enrollment['nome_completo']) ?>
                                        (<?= $escape($enrollment['codigo_central']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="small text-muted">
                                Documento aplicável:
                                <strong id="document-type-label">Seleccione um aluno.</strong>
                            </div>
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
                                Para anos encerrados, a classe determina o certificado ou a declaração
                                anual e são exigidas notas completas e aprovação. Durante o ano activo,
                                pode pedir uma declaração com as notas dos trimestres completos, mesmo
                                sem aprovação. A emissão fica disponível após aprovação administrativa.
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
                            <th>Nota de emissão / resposta</th>
                            <th>Documento / recibo</th>
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
                                    <?php if (!empty($request['classe_nome'])): ?>
                                        <div class="small text-muted">
                                            <?= $escape($request['classe_nome']) ?> ·
                                            <?= $escape($request['turma_nome']) ?> ·
                                            <?= $escape($request['ano_lectivo']) ?>
                                        </div>
                                    <?php endif; ?>
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
                                    <?php if (!empty($request['resposta_admin'])): ?>
                                        <?php if ($request['estado'] === 'aprovado'): ?>
                                            <strong>Nota de emissão:</strong><br>
                                        <?php endif; ?>
                                        <?= $escape($request['resposta_admin']) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
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
                                    <?php if ($request['estado'] === 'aprovado'): ?>
                                        <form method="post"
                                              action="<?= $escape($basePath) ?>/escola/pedidos-documentos/emitir"
                                              target="_blank" class="mb-2">
                                            <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="pedido_id" value="<?= (int) $request['id'] ?>">
                                            <button class="btn btn-sm btn-primary" type="submit">
                                                <i class="bi bi-file-earmark-check me-1"></i>
                                                Emitir <?= $request['tipo_documento'] === 'certificado'
                                                    ? 'certificado'
                                                    : ($request['tipo_documento'] === 'declaracao_notas'
                                                        ? 'declaração de notas'
                                                        : 'declaração') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="<?= $escape($basePath) ?>/escola/pedidos-documentos/recibo.pdf?pedido_id=<?= (int) $request['id'] ?>"
                                       target="_blank" rel="noopener"
                                       title="Abre o recibo PDF numa nova aba">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>Abrir recibo PDF
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const classSelect = document.getElementById('classe_id');
    const classroomSelect = document.getElementById('turma_id');
    const studentSelect = document.getElementById('matricula_id');
    const documentType = document.getElementById('tipo_documento');
    const documentTypeLabel = document.getElementById('document-type-label');

    if (!classSelect || !classroomSelect || !studentSelect || !documentType || !documentTypeLabel) {
        return;
    }

    const studentOptions = Array.from(studentSelect.querySelectorAll('option[data-classe-id]'));

    function resetSelect(select, placeholder) {
        select.replaceChildren(new Option(placeholder, ''));
        select.value = '';
        select.disabled = true;
    }

    function updateDocumentType() {
        const selectedStudent = studentSelect.options[studentSelect.selectedIndex];
        documentType.value = selectedStudent && selectedStudent.value
            ? selectedStudent.dataset.tipoDocumento
            : '';
        if (documentType.value === 'declaracao_notas') {
            const quarters = (selectedStudent.dataset.trimestres || '')
                .split(',')
                .filter(Boolean)
                .map(function (quarter) {
                    return quarter + '.º trimestre';
                });
            documentTypeLabel.textContent = 'Declaração de notas · ' + quarters.join(', ');
        } else if (documentType.value === 'certificado') {
            documentTypeLabel.textContent = 'Certificado anual';
        } else if (documentType.value === 'declaracao') {
            documentTypeLabel.textContent = 'Declaração anual';
        } else {
            documentTypeLabel.textContent = 'Seleccione um aluno.';
        }
    }

    classSelect.addEventListener('change', function () {
        const classId = classSelect.value;
        resetSelect(classroomSelect, classId ? 'Seleccione uma turma' : 'Seleccione primeiro uma classe');
        resetSelect(studentSelect, 'Seleccione primeiro uma turma');
        updateDocumentType();

        if (!classId) {
            return;
        }

        const classrooms = new Map();
        studentOptions.forEach(function (option) {
            if (option.dataset.classeId === classId) {
                classrooms.set(option.dataset.turmaId, option.dataset.turmaId);
            }
        });

        classrooms.forEach(function (turmaId) {
            const student = studentOptions.find(function (option) {
                return option.dataset.classeId === classId && option.dataset.turmaId === turmaId;
            });
            if (student) {
                classroomSelect.add(new Option(
                    student.dataset.turmaNome + ' · ' + student.dataset.ano,
                    turmaId
                ));
            }
        });
        classroomSelect.disabled = classrooms.size === 0;
    });

    classroomSelect.addEventListener('change', function () {
        const classId = classSelect.value;
        const turmaId = classroomSelect.value;
        resetSelect(studentSelect, turmaId ? 'Seleccione um aluno' : 'Seleccione primeiro uma turma');

        if (!classId || !turmaId) {
            return;
        }

        studentOptions.forEach(function (option) {
            if (option.dataset.classeId === classId && option.dataset.turmaId === turmaId) {
                studentSelect.add(option.cloneNode(true));
            }
        });
        studentSelect.disabled = studentSelect.options.length <= 1;
        updateDocumentType();
    });

    studentSelect.addEventListener('change', updateDocumentType);
});
</script>
