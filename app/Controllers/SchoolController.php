<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Services\SchoolPdfGenerator;
use Config\Database;

class SchoolController extends Controller
{
    private const PASSING_GRADE = 10.0;

    public function dashboard(): void
    {
        $user = $this->requireSchoolUser();
        $database = Database::connect();
        $summary = [
            'alunos' => 0,
            'matriculas' => 0,
            'notas' => 0,
            'pedidos' => 0
        ];
        $schoolId = (int) $user['escola_id'];
        $summary['alunos'] = $this->countQuery(
            $database,
            "SELECT COUNT(DISTINCT ae.aluno_id)
             FROM aluno_escolas ae
             INNER JOIN alunos a ON a.id = ae.aluno_id
             WHERE ae.escola_id = :escola_id
               AND ae.estado = 'activo'
               AND a.estado = 'activo'",
            $schoolId
        );
        $summary['matriculas'] = $this->countQuery(
            $database,
            "SELECT COUNT(*) FROM matriculas
             WHERE escola_id = :escola_id AND estado = 'activa'",
            $schoolId
        );
        $summary['notas'] = $this->countQuery(
            $database,
            'SELECT COUNT(*)
             FROM notas_trimestrais n
             INNER JOIN matriculas m ON m.id = n.matricula_id
             WHERE m.escola_id = :escola_id
               AND (
                    n.acs1 IS NOT NULL
                    OR n.acs2 IS NOT NULL
                    OR n.avaliacao_provincial IS NOT NULL
                    OR n.nota IS NOT NULL
               )',
            $schoolId
        );
        $summary['pedidos'] = $this->countQuery(
            $database,
            "SELECT COUNT(*) FROM pedidos_documentos
             WHERE escola_id = :escola_id AND estado = 'pendente'",
            $schoolId
        );

        $this->renderSchoolPage(
            $user,
            'Painel da Escola',
            'school-dashboard',
            'school/dashboard',
            ['summary' => $summary]
        );
    }

    public function grades(): void
    {
        $user = $this->requireSchoolUser();
        $database = Database::connect();
        $year = $this->activeAcademicYear($database);
        $turmas = $year === null
            ? []
            : $this->schoolTurmasForYear(
                $database,
                (int) $user['escola_id'],
                (int) $year['id']
            );
        $turmaId = $this->selectedId('turma_id', $turmas);
        $turma = $this->findById($turmas, $turmaId);
        $disciplines = $year === null
            ? []
            : (
                $turma === null
                    ? $this->schoolDisciplinesForYear(
                        $database,
                        (int) $user['escola_id'],
                        (int) $year['id']
                    )
                    : $this->disciplinesForClass(
                        $database,
                        (int) $turma['classe_id']
                    )
            );
        $disciplineId = $this->selectedId('disciplina_id', $disciplines);
        $reports = $year === null
            ? []
            : $this->existingGradeReports(
                $database,
                (int) $user['escola_id'],
                (int) $year['id'],
                $turmaId,
                $disciplineId
            );
        $periodFilter = $this->selectedReportPeriod();
        $generalReports = $year === null
            ? []
            : $this->existingGeneralGradeReports(
                $database,
                (int) $user['escola_id'],
                (int) $year['id'],
                $turmaId
            );
        $annualReports = [];
        foreach ($generalReports as $report) {
            $classroomKey = (string) $report['turma_id'];
            if (!isset($annualReports[$classroomKey])) {
                $annualReports[$classroomKey] = [
                    'classe_nome' => $report['classe_nome'],
                    'turma_nome' => $report['turma_nome'],
                    'turma_id' => $report['turma_id'],
                    'total_alunos' => $report['total_alunos'],
                    'total_disciplinas' => $report['total_disciplinas']
                ];
            }
        }
        if ($periodFilter === 'anual') {
            $reports = [];
            $generalReports = [];
        } elseif ($periodFilter !== 'todos') {
            $reports = array_values(array_filter(
                $reports,
                static function (array $report) use ($periodFilter): bool {
                    return (int) $report['trimestre'] === (int) $periodFilter;
                }
            ));
            $generalReports = array_values(array_filter(
                $generalReports,
                static function (array $report) use ($periodFilter): bool {
                    return (int) $report['trimestre'] === (int) $periodFilter;
                }
            ));
            $annualReports = [];
        }
        $reportGroups = [];
        foreach ($reports as $report) {
            $classKey = (string) $report['classe_id'];
            if (!isset($reportGroups[$classKey])) {
                $reportGroups[$classKey] = [
                    'nome' => $report['classe_nome'],
                    'pautas' => []
                ];
            }
            $reportGroups[$classKey]['pautas'][] = $report;
        }

        $this->renderSchoolPage(
            $user,
            'Pautas existentes',
            'school-grades',
            'school/grade-reports',
            [
                'year' => $year,
                'turmas' => $turmas,
                'turmaId' => $turmaId,
                'disciplines' => $disciplines,
                'disciplineId' => $disciplineId,
                'periodFilter' => $periodFilter,
                'generalReports' => $generalReports,
                'annualReports' => array_values($annualReports),
                'reportGroups' => $reportGroups
            ]
        );
    }

    public function launchGrades(): void
    {
        $this->renderGradeLaunchPage();
    }

    private function renderGradeLaunchPage(): void
    {
        $user = $this->requireSchoolUser();
        $database = Database::connect();
        $year = $this->activeAcademicYear($database);
        $yearId = $year === null ? null : (int) $year['id'];
        $classes = $year === null
            ? []
            : $this->schoolClassesForYear(
                $database,
                (int) $user['escola_id'],
                (int) $yearId
            );
        $classId = $this->selectedId('classe_id', $classes);
        $class = $this->findById($classes, $classId);
        $quarter = $this->selectedQuarter();
        $turmas = $year === null || $class === null
            ? []
            : $this->schoolTurmasForClassAndYear(
                $database,
                (int) $user['escola_id'],
                (int) $yearId,
                (int) $classId
            );
        $turmaId = $this->selectedId('turma_id', $turmas);
        $turma = $this->findById($turmas, $turmaId);
        $disciplines = $turma === null
            ? []
            : $this->disciplinesForClass($database, (int) $classId);
        $disciplineId = $this->selectedId('disciplina_id', $disciplines);
        $discipline = $this->findById($disciplines, $disciplineId);
        $students = [];
        if ($year !== null && $class !== null && $turma !== null && $discipline !== null) {
            $students = $this->studentsForGrades(
                $database,
                (int) $user['escola_id'],
                (int) $yearId,
                (int) $classId,
                (int) $turmaId,
                (int) $disciplineId,
                $quarter
            );
        }
        $reportIsComplete = $students !== [];
        $hasExistingGrades = false;
        foreach ($students as $student) {
            if (
                $student['acs1'] !== null
                || $student['acs2'] !== null
                || $student['avaliacao_provincial'] !== null
            ) {
                $hasExistingGrades = true;
            }
            if (
                $student['acs1'] === null
                || $student['acs2'] === null
                || $student['avaliacao_provincial'] === null
            ) {
                $reportIsComplete = false;
            }
        }

        $this->renderSchoolPage(
            $user,
            'Lançar pauta',
            'school-grade-launch',
            'school/grades',
            [
                'year' => $year,
                'yearId' => $yearId,
                'classes' => $classes,
                'class' => $class,
                'classId' => $classId,
                'turmas' => $turmas,
                'turma' => $turma,
                'turmaId' => $turmaId,
                'disciplines' => $disciplines,
                'discipline' => $discipline,
                'disciplineId' => $disciplineId,
                'quarter' => $quarter,
                'students' => $students,
                'reportIsComplete' => $reportIsComplete,
                'hasExistingGrades' => $hasExistingGrades
            ]
        );
    }

    public function saveGrades(): void
    {
        $user = $this->requireSchoolUser();
        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $yearId = $this->postedId('ano_id');
        $classId = $this->postedId('classe_id');
        $turmaId = $this->postedId('turma_id');
        $quarter = $this->postedId('trimestre');
        $disciplineId = $this->postedId('disciplina_id');
        $grades = $_POST['notas'] ?? null;
        $filters = [
            'classe_id' => $classId,
            'turma_id' => $turmaId,
            'trimestre' => $quarter,
            'disciplina_id' => $disciplineId
        ];
        if (
            $yearId === false
            || $classId === false
            || $turmaId === false
            || $quarter === false
            || !in_array($quarter, [1, 2, 3], true)
            || $disciplineId === false
            || !is_array($grades)
        ) {
            $this->setFlash('danger', 'Seleccione a classe, a turma, o trimestre e a disciplina antes de guardar.');
            $this->redirectToGrades($filters, true);
        }

        $database = Database::connect();
        $database->beginTransaction();
        try {
            $yearStatement = $database->prepare(
                "SELECT id FROM anos_lectivos
                 WHERE id = :id AND estado = 'activo'
                 FOR UPDATE"
            );
            $yearStatement->execute(['id' => $yearId]);
            if ($yearStatement->fetchColumn() === false) {
                $database->rollBack();
                $this->setFlash('danger', 'O ano lectivo activo foi alterado. Actualize a página e tente novamente.');
                $this->redirectToGrades($filters, true);
            }

            $disciplineStatement = $database->prepare(
                "SELECT cd.id
                 FROM classes_disciplinas cd
                 INNER JOIN classes c ON c.id = cd.classe_id
                 INNER JOIN disciplinas d ON d.id = cd.disciplina_id
                 WHERE cd.classe_id = :classe_id
                   AND cd.disciplina_id = :disciplina_id
                   AND c.estado = 'activa'
                   AND d.estado = 'activa'
                   AND EXISTS (
                       SELECT 1 FROM matriculas m
                       INNER JOIN turmas t ON t.id = m.turma_id
                       WHERE m.escola_id = :escola_id
                         AND m.ano_lectivo_id = :ano_id
                         AND m.classe_id = c.id
                         AND m.turma_id = :turma_id
                         AND m.estado = 'activa'
                         AND t.escola_id = m.escola_id
                         AND t.ano_lectivo_id = m.ano_lectivo_id
                         AND t.classe_id = m.classe_id
                         AND t.estado = 'activa'
                   )
                 LIMIT 1
                 FOR UPDATE"
            );
            $disciplineStatement->execute([
                'classe_id' => $classId,
                'disciplina_id' => $disciplineId,
                'escola_id' => (int) $user['escola_id'],
                'ano_id' => $yearId,
                'turma_id' => $turmaId
            ]);
            if ($disciplineStatement->fetchColumn() === false) {
                $database->rollBack();
                $this->setFlash('danger', 'A turma ou disciplina não pertence à classe seleccionada.');
                $this->redirectToGrades($filters, true);
            }

            $enrollmentStatement = $database->prepare(
                "SELECT m.id
                 FROM matriculas m
                 INNER JOIN aluno_escolas ae ON ae.id = m.aluno_escola_id
                 INNER JOIN alunos a ON a.id = ae.aluno_id
                 WHERE m.escola_id = :escola_id
                   AND m.ano_lectivo_id = :ano_id
                   AND m.classe_id = :classe_id
                   AND m.turma_id = :turma_id
                   AND m.estado = 'activa'
                   AND ae.estado = 'activo'
                   AND a.estado = 'activo'
                 FOR UPDATE"
            );
            $enrollmentStatement->execute([
                'escola_id' => (int) $user['escola_id'],
                'ano_id' => $yearId,
                'classe_id' => $classId,
                'turma_id' => $turmaId
            ]);
            $allowedEnrollments = array_fill_keys(
                array_map('strval', $enrollmentStatement->fetchAll(\PDO::FETCH_COLUMN)),
                true
            );
            $valuesToSave = $this->validateGradeInput($grades, $allowedEnrollments);
            if ($valuesToSave === false) {
                $database->rollBack();
                $this->setFlash(
                    'danger',
                    'Confirme que os alunos pertencem à turma e que cada avaliação está entre 0 e 20, com no máximo duas casas decimais.'
                );
                $this->redirectToGrades($filters, true);
            }

            if ($valuesToSave === []) {
                $database->rollBack();
                $this->setFlash('warning', 'Não foram introduzidas notas para guardar.');
                $this->redirectToGrades($filters, true);
            }

            $saveGrade = $database->prepare(
                'INSERT INTO notas_trimestrais
                    (matricula_id, disciplina_id, trimestre, acs1, acs2,
                     avaliacao_provincial, nota, criado_por, actualizado_por)
                 VALUES
                    (:matricula_id, :disciplina_id, :trimestre, :acs1, :acs2,
                     :avaliacao_provincial, :nota, :criado_por, :actualizado_por)
                 ON DUPLICATE KEY UPDATE
                    acs1 = VALUES(acs1),
                    acs2 = VALUES(acs2),
                    avaliacao_provincial = VALUES(avaliacao_provincial),
                    nota = VALUES(nota),
                    actualizado_por = VALUES(actualizado_por),
                    actualizado_em = CURRENT_TIMESTAMP'
            );
            $deleteGrade = $database->prepare(
                'DELETE FROM notas_trimestrais
                 WHERE matricula_id = :matricula_id
                   AND disciplina_id = :disciplina_id
                   AND trimestre = :trimestre'
            );
            foreach ($valuesToSave as $grade) {
                $gradeParameters = [
                    'matricula_id' => $grade['matricula_id'],
                    'disciplina_id' => $disciplineId,
                    'trimestre' => $quarter
                ];
                if ($grade['acs1'] === null && $grade['acs2'] === null && $grade['avaliacao_provincial'] === null) {
                    $deleteGrade->execute($gradeParameters);
                    continue;
                }
                $saveGrade->execute(array_merge($gradeParameters, [
                    'acs1' => $grade['acs1'],
                    'acs2' => $grade['acs2'],
                    'avaliacao_provincial' => $grade['avaliacao_provincial'],
                    'nota' => $grade['media_trimestre'],
                    'criado_por' => (int) $user['id'],
                    'actualizado_por' => (int) $user['id']
                ]));
            }
            $database->commit();
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        $this->setFlash('success', 'Notas guardadas com sucesso.');
        $this->redirectToGrades([
            'turma_id' => $turmaId,
            'disciplina_id' => $disciplineId
        ]);
    }

    public function downloadGradeReport(): void
    {
        $user = $this->requireSchoolUser();
        $database = Database::connect();
        $year = $this->activeAcademicYear($database);
        $classId = $this->queryId('classe_id');
        $turmaId = $this->queryId('turma_id');
        $disciplineId = $this->queryId('disciplina_id');
        $quarter = $this->queryId('trimestre');
        if (
            $year === null
            || $classId === false
            || $turmaId === false
            || $disciplineId === false
            || $quarter === false
            || !in_array($quarter, [1, 2, 3], true)
        ) {
            http_response_code(400);
            echo 'Seleccione uma classe, turma, disciplina e trimestre válidos.';
            return;
        }

        $classes = $this->schoolClassesForYear(
            $database,
            (int) $user['escola_id'],
            (int) $year['id']
        );
        $class = $this->findById($classes, $classId);
        $turmas = $class === null
            ? null
            : $this->schoolTurmasForClassAndYear(
                $database,
                (int) $user['escola_id'],
                (int) $year['id'],
                $classId
            );
        $turma = $turmas === null ? null : $this->findById($turmas, $turmaId);
        $discipline = $turma === null
            ? null
            : $this->findById($this->disciplinesForClass($database, $classId), $disciplineId);
        if ($class === null || $turma === null || $discipline === null) {
            http_response_code(404);
            echo 'A classe, turma ou disciplina não pertence a esta escola.';
            return;
        }

        $students = $this->studentsForGrades(
            $database,
            (int) $user['escola_id'],
            (int) $year['id'],
            $classId,
            $turmaId,
            $disciplineId,
            $quarter
        );
        $hasExistingGrades = false;
        if ($students !== []) {
            foreach ($students as $student) {
                if (
                    $student['acs1'] !== null
                    || $student['acs2'] !== null
                    || $student['avaliacao_provincial'] !== null
                ) {
                    $hasExistingGrades = true;
                    break;
                }
            }
        }
        if ($students === [] || !$hasExistingGrades) {
            http_response_code(404);
            echo 'Não há notas lançadas para emitir esta pauta.';
            return;
        }
        $complete = true;
        foreach ($students as $student) {
            if (
                $student['acs1'] === null
                || $student['acs2'] === null
                || $student['avaliacao_provincial'] === null
            ) {
                $complete = false;
                break;
            }
        }

        $pdf = (new SchoolPdfGenerator())->subjectReport([
            'school_name' => $user['escola_nome'],
            'school_district' => $user['escola_distrito'],
            'class_name' => $class['nome'],
            'classroom_name' => $turma['identificacao'],
            'discipline_name' => $discipline['nome'],
            'year' => $year['ano'],
            'term' => $quarter,
            'students' => $students,
            'completa' => $complete,
            'issued_at' => date('d/m/Y H:i')
        ]);
        $this->sendPdf(
            $pdf,
            'pauta-classe-' . $classId . '-turma-' . $turmaId
                . '-disciplina-' . $disciplineId . '-t' . $quarter . '.pdf'
        );
    }

    public function downloadGeneralGradeReport(): void
    {
        $user = $this->requireSchoolUser();
        $database = Database::connect();
        $year = $this->activeAcademicYear($database);
        $turmaId = $this->queryId('turma_id');
        $quarter = $this->queryId('trimestre');
        if (
            $year === null
            || $turmaId === false
            || $quarter === false
            || !in_array($quarter, [1, 2, 3], true)
        ) {
            http_response_code(400);
            echo 'Seleccione uma turma e um trimestre válidos.';
            return;
        }

        $turmas = $this->schoolTurmasForYear(
            $database,
            (int) $user['escola_id'],
            (int) $year['id']
        );
        $turma = $this->findById($turmas, $turmaId);
        if ($turma === null) {
            http_response_code(404);
            echo 'A turma não pertence a esta escola.';
            return;
        }

        $classId = (int) $turma['classe_id'];
        $disciplines = $this->disciplinesForClass($database, $classId);
        if ($disciplines === []) {
            http_response_code(404);
            echo 'A classe não tem disciplinas activas para emitir a pauta.';
            return;
        }

        $statement = $database->prepare(
            "SELECT m.id AS matricula_id, a.nome_completo, a.codigo_central,
                    d.id AS disciplina_id, d.nome AS disciplina_nome,
                    n.acs1, n.acs2, n.avaliacao_provincial, n.nota
             FROM matriculas m
             INNER JOIN turmas t
                ON t.id = m.turma_id
               AND t.escola_id = m.escola_id
               AND t.ano_lectivo_id = m.ano_lectivo_id
               AND t.classe_id = m.classe_id
             INNER JOIN aluno_escolas ae
                ON ae.id = m.aluno_escola_id
               AND ae.escola_id = m.escola_id
             INNER JOIN alunos a ON a.id = ae.aluno_id
             INNER JOIN classes_disciplinas cd
                ON cd.classe_id = m.classe_id
             INNER JOIN disciplinas d
                ON d.id = cd.disciplina_id
               AND d.estado = 'activa'
             LEFT JOIN notas_trimestrais n
                ON n.matricula_id = m.id
               AND n.disciplina_id = d.id
               AND n.trimestre = :trimestre
             WHERE m.escola_id = :escola_id
               AND m.ano_lectivo_id = :ano_id
               AND m.classe_id = :classe_id
               AND m.turma_id = :turma_id
               AND m.estado = 'activa'
               AND t.estado = 'activa'
               AND ae.estado = 'activo'
               AND a.estado = 'activo'
             ORDER BY a.nome_completo, a.codigo_central, d.nome"
        );
        $statement->execute([
            'trimestre' => $quarter,
            'escola_id' => (int) $user['escola_id'],
            'ano_id' => (int) $year['id'],
            'classe_id' => $classId,
            'turma_id' => $turmaId
        ]);

        $students = [];
        $hasExistingGrades = false;
        $complete = true;
        foreach ($statement->fetchAll() as $row) {
            $enrollmentId = (int) $row['matricula_id'];
            $disciplineId = (int) $row['disciplina_id'];
            if (!isset($students[$enrollmentId])) {
                $students[$enrollmentId] = [
                    'nome_completo' => $row['nome_completo'],
                    'codigo_central' => $row['codigo_central'],
                    'grades' => []
                ];
            }

            $hasStudentGrade = $row['acs1'] !== null
                || $row['acs2'] !== null
                || $row['avaliacao_provincial'] !== null;
            $hasExistingGrades = $hasExistingGrades || $hasStudentGrade;
            $gradeIsComplete = $row['acs1'] !== null
                && $row['acs2'] !== null
                && $row['avaliacao_provincial'] !== null;
            $complete = $complete && $gradeIsComplete;
            $average = $gradeIsComplete ? $row['nota'] : null;
            if (
                $average === null
                && $gradeIsComplete
            ) {
                $average = round(
                    (
                        (float) $row['acs1']
                        + (float) $row['acs2']
                        + (float) $row['avaliacao_provincial']
                    ) / 3,
                    2
                );
            }
            $students[$enrollmentId]['grades'][$disciplineId] = [
                'acs1' => $row['acs1'],
                'acs2' => $row['acs2'],
                'avaliacao_provincial' => $row['avaliacao_provincial'],
                'nota' => $average
            ];
        }

        if ($students === [] || !$hasExistingGrades) {
            http_response_code(404);
            echo 'Não há notas lançadas nesta turma para emitir a pauta geral.';
            return;
        }

        foreach ($students as &$student) {
            $disciplineAverages = [];
            foreach ($disciplines as $discipline) {
                $grade = $student['grades'][(int) $discipline['id']] ?? null;
                if ($grade === null || $grade['nota'] === null) {
                    $disciplineAverages = [];
                    break;
                }
                $disciplineAverages[] = (float) $grade['nota'];
            }

            $finalAverage = count($disciplineAverages) === count($disciplines)
                ? round(array_sum($disciplineAverages) / count($disciplines), 2)
                : null;
            $student['media_final'] = $finalAverage;
            $student['resultado'] = $finalAverage === null
                ? 'Pendente'
                : (
                    $finalAverage >= self::PASSING_GRADE
                        ? 'Aprovado'
                        : 'Reprovado'
                );
        }
        unset($student);

        $pdf = (new SchoolPdfGenerator())->generalClassReport([
            'school_name' => $user['escola_nome'],
            'school_district' => $user['escola_distrito'],
            'class_name' => $turma['classe_nome'],
            'classroom_name' => $turma['identificacao'],
            'year' => $year['ano'],
            'term' => $quarter,
            'disciplines' => $disciplines,
            'students' => array_values($students),
            'completa' => $complete,
            'passing_grade' => self::PASSING_GRADE,
            'issued_at' => date('d/m/Y H:i')
        ]);
        $this->sendPdf(
            $pdf,
            'pauta-geral-classe-' . $classId . '-turma-' . $turmaId
                . '-t' . $quarter . '.pdf'
        );
    }

    public function downloadAnnualGradeReport(): void
    {
        $user = $this->requireSchoolUser();
        $database = Database::connect();
        $year = $this->activeAcademicYear($database);
        $turmaId = $this->queryId('turma_id');
        if ($year === null || $turmaId === false) {
            http_response_code(400);
            echo 'Seleccione uma turma válida.';
            return;
        }

        $turmas = $this->schoolTurmasForYear(
            $database,
            (int) $user['escola_id'],
            (int) $year['id']
        );
        $turma = $this->findById($turmas, $turmaId);
        if ($turma === null) {
            http_response_code(404);
            echo 'A turma não pertence a esta escola.';
            return;
        }

        $classId = (int) $turma['classe_id'];
        $disciplines = $this->disciplinesForClass($database, $classId);
        if ($disciplines === []) {
            http_response_code(404);
            echo 'A classe não tem disciplinas activas para emitir a pauta anual.';
            return;
        }

        $statement = $database->prepare(
            "SELECT m.id AS matricula_id, a.nome_completo, a.codigo_central,
                    d.id AS disciplina_id, n.trimestre,
                    n.acs1, n.acs2, n.avaliacao_provincial, n.nota
             FROM matriculas m
             INNER JOIN turmas t
                ON t.id = m.turma_id
               AND t.escola_id = m.escola_id
               AND t.ano_lectivo_id = m.ano_lectivo_id
               AND t.classe_id = m.classe_id
             INNER JOIN aluno_escolas ae
                ON ae.id = m.aluno_escola_id
               AND ae.escola_id = m.escola_id
             INNER JOIN alunos a ON a.id = ae.aluno_id
             INNER JOIN classes_disciplinas cd
                ON cd.classe_id = m.classe_id
             INNER JOIN disciplinas d
                ON d.id = cd.disciplina_id
               AND d.estado = 'activa'
             LEFT JOIN notas_trimestrais n
                ON n.matricula_id = m.id
               AND n.disciplina_id = d.id
               AND n.trimestre BETWEEN 1 AND 3
             WHERE m.escola_id = :escola_id
               AND m.ano_lectivo_id = :ano_id
               AND m.classe_id = :classe_id
               AND m.turma_id = :turma_id
               AND m.estado = 'activa'
               AND t.estado = 'activa'
               AND ae.estado = 'activo'
               AND a.estado = 'activo'
             ORDER BY a.nome_completo, a.codigo_central, d.nome, n.trimestre"
        );
        $statement->execute([
            'escola_id' => (int) $user['escola_id'],
            'ano_id' => (int) $year['id'],
            'classe_id' => $classId,
            'turma_id' => $turmaId
        ]);

        $students = [];
        $hasExistingGrades = false;
        foreach ($statement->fetchAll() as $row) {
            $enrollmentId = (int) $row['matricula_id'];
            $disciplineId = (int) $row['disciplina_id'];
            if (!isset($students[$enrollmentId])) {
                $students[$enrollmentId] = [
                    'nome_completo' => $row['nome_completo'],
                    'codigo_central' => $row['codigo_central'],
                    'grades' => []
                ];
                foreach ($disciplines as $discipline) {
                    $students[$enrollmentId]['grades'][(int) $discipline['id']] = [
                        1 => null,
                        2 => null,
                        3 => null,
                        'media_anual' => null
                    ];
                }
            }

            if ($row['trimestre'] === null) {
                continue;
            }

            $quarter = (int) $row['trimestre'];
            $hasStudentGrade = $row['acs1'] !== null
                || $row['acs2'] !== null
                || $row['avaliacao_provincial'] !== null;
            $hasExistingGrades = $hasExistingGrades || $hasStudentGrade;
            $gradeIsComplete = $row['acs1'] !== null
                && $row['acs2'] !== null
                && $row['avaliacao_provincial'] !== null;
            $average = $gradeIsComplete ? $row['nota'] : null;
            if ($average === null && $gradeIsComplete) {
                $average = round(
                    (
                        (float) $row['acs1']
                        + (float) $row['acs2']
                        + (float) $row['avaliacao_provincial']
                    ) / 3,
                    2
                );
            }
            $students[$enrollmentId]['grades'][$disciplineId][$quarter] = [
                'acs1' => $row['acs1'],
                'acs2' => $row['acs2'],
                'avaliacao_provincial' => $row['avaliacao_provincial'],
                'nota' => $average
            ];
        }

        if ($students === [] || !$hasExistingGrades) {
            http_response_code(404);
            echo 'Não há notas lançadas nesta turma para emitir a pauta anual.';
            return;
        }

        $complete = true;
        foreach ($students as &$student) {
            $disciplineAnnualAverages = [];
            foreach ($disciplines as $discipline) {
                $disciplineId = (int) $discipline['id'];
                $quarterAverages = [];
                for ($quarter = 1; $quarter <= 3; $quarter++) {
                    $quarterGrade = $student['grades'][$disciplineId][$quarter];
                    if ($quarterGrade === null || $quarterGrade['nota'] === null) {
                        $complete = false;
                        $quarterAverages = [];
                        break;
                    }
                    $quarterAverages[] = (float) $quarterGrade['nota'];
                }

                $annualAverage = count($quarterAverages) === 3
                    ? round(array_sum($quarterAverages) / 3, 2)
                    : null;
                $student['grades'][$disciplineId]['media_anual'] = $annualAverage;
                if ($annualAverage === null) {
                    $disciplineAnnualAverages = [];
                    break;
                }
                $disciplineAnnualAverages[] = $annualAverage;
            }

            $finalAverage = count($disciplineAnnualAverages) === count($disciplines)
                ? round(
                    array_sum($disciplineAnnualAverages)
                        / count($disciplineAnnualAverages),
                    2
                )
                : null;
            $student['media_final'] = $finalAverage;
            $student['resultado'] = $finalAverage === null
                ? 'Pendente'
                : (
                    $finalAverage >= self::PASSING_GRADE
                        ? 'Aprovado'
                        : 'Reprovado'
                );
        }
        unset($student);

        $pdf = (new SchoolPdfGenerator())->annualClassReport([
            'school_name' => $user['escola_nome'],
            'school_district' => $user['escola_distrito'],
            'class_name' => $turma['classe_nome'],
            'classroom_name' => $turma['identificacao'],
            'year' => $year['ano'],
            'disciplines' => $disciplines,
            'students' => array_values($students),
            'completa' => $complete,
            'passing_grade' => self::PASSING_GRADE,
            'issued_at' => date('d/m/Y H:i')
        ]);
        $this->sendPdf(
            $pdf,
            'pauta-anual-classe-' . $classId . '-turma-' . $turmaId . '.pdf'
        );
    }

    public function documentRequests(): void
    {
        $user = $this->requireSchoolUser();
        $database = Database::connect();
        $schoolId = (int) $user['escola_id'];

        $students = $database->prepare(
            'SELECT ae.id AS aluno_escola_id, ae.estado AS associacao_estado,
                    a.nome_completo, a.codigo_central
             FROM aluno_escolas ae
             INNER JOIN alunos a ON a.id = ae.aluno_id
             WHERE ae.escola_id = :escola_id
             ORDER BY a.nome_completo, ae.id'
        );
        $students->execute(['escola_id' => $schoolId]);

        $requests = $database->prepare(
            'SELECT pd.id, pd.codigo, pd.tipo_documento, pd.motivo, pd.estado,
                    pd.resposta_admin, pd.codigo_validacao, pd.emitido_em,
                    pd.solicitado_em, pd.concluido_em,
                    a.nome_completo, a.codigo_central
             FROM pedidos_documentos pd
             INNER JOIN aluno_escolas ae ON ae.id = pd.aluno_escola_id
             INNER JOIN alunos a ON a.id = ae.aluno_id
             WHERE pd.escola_id = :escola_id
             ORDER BY pd.solicitado_em DESC, pd.id DESC'
        );
        $requests->execute(['escola_id' => $schoolId]);

        $this->renderSchoolPage(
            $user,
            'Pedidos de documentos',
            'school-documents',
            'school/document-requests',
            [
                'students' => $students->fetchAll(),
                'requests' => $requests->fetchAll(),
                'documentTypes' => [
                    'certificado' => 'Certificado',
                    'declaracao' => 'Declaração'
                ],
                'statusLabels' => $this->documentStatusLabels()
            ]
        );
    }

    public function downloadDocumentRequestReceipt(): void
    {
        $user = $this->requireSchoolUser();
        $requestId = $this->queryId('pedido_id');
        if ($requestId === false) {
            http_response_code(400);
            echo 'Seleccione um pedido válido.';
            return;
        }

        $statement = Database::connect()->prepare(
            'SELECT pd.id, pd.codigo, pd.tipo_documento, pd.estado, pd.motivo,
                    pd.resposta_admin, pd.codigo_validacao, pd.solicitado_em,
                    pd.emitido_em, pd.concluido_em,
                    a.nome_completo AS aluno_nome, a.codigo_central,
                    e.nome AS escola_nome, e.distrito AS escola_distrito,
                    e.provincia AS escola_provincia
             FROM pedidos_documentos pd
             INNER JOIN aluno_escolas ae
                ON ae.id = pd.aluno_escola_id
               AND ae.escola_id = pd.escola_id
             INNER JOIN alunos a ON a.id = ae.aluno_id
             INNER JOIN escolas e ON e.id = pd.escola_id
             WHERE pd.id = :pedido_id
               AND pd.escola_id = :escola_id
             LIMIT 1'
        );
        $statement->execute([
            'pedido_id' => $requestId,
            'escola_id' => (int) $user['escola_id']
        ]);
        $request = $statement->fetch();
        if (!$request) {
            http_response_code(404);
            echo 'O pedido não foi encontrado nesta escola.';
            return;
        }

        $pdf = (new SchoolPdfGenerator())->documentRequestReceipt([
            'request_code' => $request['codigo'],
            'tipo_documento' => $request['tipo_documento'],
            'estado' => $request['estado'],
            'motivo' => $request['motivo'],
            'resposta_admin' => $request['resposta_admin'],
            'codigo_validacao' => $request['codigo_validacao'],
            'solicitado_em' => $request['solicitado_em'],
            'emitido_em' => $request['emitido_em'],
            'concluido_em' => $request['concluido_em'],
            'aluno_nome' => $request['aluno_nome'],
            'codigo_central' => $request['codigo_central'],
            'escola_nome' => $request['escola_nome'],
            'escola_distrito' => $request['escola_distrito'],
            'escola_provincia' => $request['escola_provincia'],
            'verification_path' => $this->basePath() . '/validar-documento'
        ]);
        $this->sendPdf($pdf, 'recibo-pedido-' . (int) $request['id'] . '.pdf', true);
    }

    public function saveDocumentRequest(): void
    {
        $user = $this->requireSchoolUser();
        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $studentSchoolId = $this->postedId('aluno_escola_id');
        $type = $this->postedString('tipo_documento');
        $reason = $this->postedString('motivo');
        $reasonLength = preg_match_all('/./us', $reason);
        if (
            $studentSchoolId === false
            || !in_array($type, ['certificado', 'declaracao'], true)
            || $reasonLength === false
            || $reasonLength < 5
            || $reasonLength > 500
        ) {
            $this->setFlash('danger', 'Seleccione o aluno, o tipo de documento e indique o motivo (5 a 500 caracteres).');
            $this->redirect('/escola/pedidos-documentos');
        }

        $database = Database::connect();
        $association = $database->prepare(
            'SELECT id FROM aluno_escolas
             WHERE id = :id AND escola_id = :escola_id
             LIMIT 1'
        );
        $association->execute([
            'id' => $studentSchoolId,
            'escola_id' => (int) $user['escola_id']
        ]);
        if ($association->fetchColumn() === false) {
            $this->setFlash('danger', 'O aluno seleccionado não pertence a esta escola.');
            $this->redirect('/escola/pedidos-documentos');
        }

        $saveRequest = $database->prepare(
            'INSERT INTO pedidos_documentos
                (codigo, escola_id, aluno_escola_id, tipo_documento, motivo, solicitado_por)
             VALUES
                (:codigo, :escola_id, :aluno_escola_id, :tipo_documento, :motivo, :solicitado_por)'
        );
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $saveRequest->execute([
                    'codigo' => 'SGECE-PED-' . strtoupper(bin2hex(random_bytes(6))),
                    'escola_id' => (int) $user['escola_id'],
                    'aluno_escola_id' => $studentSchoolId,
                    'tipo_documento' => $type,
                    'motivo' => $reason,
                    'solicitado_por' => (int) $user['id']
                ]);
                $this->setFlash('success', 'Pedido registado. O recibo está disponível no histórico abaixo.');
                $this->redirect('/escola/pedidos-documentos');
            } catch (\PDOException $exception) {
                if ($exception->getCode() !== '23000' || $attempt === 4) {
                    throw $exception;
                }
            }
        }
    }

    private function requireSchoolUser(): array
    {
        Session::start();
        $this->preventCaching();

        $auth = $_SESSION['auth'] ?? null;
        $userId = is_array($auth)
            ? filter_var($auth['user_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1]
            ])
            : false;
        if ($userId === false) {
            $this->redirect('/admin');
        }

        $statement = Database::connect()->prepare(
            'SELECT u.id, u.nome, u.email, u.escola_id, na.nome AS nivel,
                    e.nome AS escola_nome, e.tipo AS escola_tipo,
                    e.provincia AS escola_provincia, e.distrito AS escola_distrito,
                    e.localidade AS escola_localidade, e.endereco AS escola_endereco,
                    e.telefone AS escola_telefone, e.email AS escola_email,
                    e.estado AS escola_estado
             FROM usuarios u
             INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
             LEFT JOIN escolas e ON e.id = u.escola_id
             WHERE u.id = :id AND u.estado = \'activo\'
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        if (!$user) {
            $this->invalidateAuthentication();
            $this->redirect('/admin');
        }

        if (in_array($user['nivel'], ['Root', 'Admin'], true)) {
            $this->redirect('/admin/dashboard');
        }
        if (
            $user['nivel'] !== 'Escola'
            || $user['escola_id'] === null
            || $user['escola_estado'] !== 'activa'
        ) {
            $this->invalidateAuthentication();
            $this->redirect('/admin');
        }

        $_SESSION['auth'] = [
            'user_id' => $user['id'],
            'nome' => $user['nome'],
            'nivel' => $user['nivel']
        ];
        if (
            !isset($_SESSION['csrf_token'])
            || !is_string($_SESSION['csrf_token'])
            || preg_match('/^[a-f0-9]{64}$/i', $_SESSION['csrf_token']) !== 1
        ) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $user;
    }

    private function renderSchoolPage(
        array $user,
        string $title,
        string $activeMenu,
        string $viewName,
        array $data
    ): void {
        $flash = $_SESSION['_school_flash'] ?? null;
        unset($_SESSION['_school_flash']);
        ob_start();
        $this->view($viewName, array_merge($data, [
            'user' => $user,
            'csrfToken' => $_SESSION['csrf_token'],
            'basePath' => $this->basePath(),
            'flash' => is_array($flash) ? $flash : null
        ]));
        $content = ob_get_clean();

        $this->view('admin/layouts/main', [
            'pageTitle' => $title,
            'activeMenu' => $activeMenu,
            'nomeUsuario' => $user['nome'],
            'nivelUsuario' => $user['nivel'],
            'csrfToken' => $_SESSION['csrf_token'],
            'content' => $content
        ]);
    }

    private function countQuery(\PDO $database, string $sql, int $schoolId): int
    {
        $statement = $database->prepare($sql);
        $statement->execute(['escola_id' => $schoolId]);
        return (int) $statement->fetchColumn();
    }

    private function activeAcademicYear(\PDO $database): ?array
    {
        $statement = $database->query(
            "SELECT id, ano, estado
             FROM anos_lectivos
             WHERE estado = 'activo'
             ORDER BY ano DESC, id DESC
             LIMIT 1"
        );
        $year = $statement->fetch();
        return $year === false ? null : $year;
    }

    private function schoolClassesForYear(
        \PDO $database,
        int $schoolId,
        int $yearId
    ): array {
        $statement = $database->prepare(
            "SELECT DISTINCT c.id, c.nome, c.ordem
             FROM matriculas m
             INNER JOIN turmas t
                ON t.id = m.turma_id
               AND t.escola_id = m.escola_id
               AND t.ano_lectivo_id = m.ano_lectivo_id
               AND t.classe_id = m.classe_id
               AND t.estado = 'activa'
             INNER JOIN classes c ON c.id = m.classe_id
             WHERE m.escola_id = :escola_id
               AND m.ano_lectivo_id = :ano_id
               AND m.estado = 'activa'
               AND c.estado = 'activa'
             ORDER BY c.ordem, c.nome"
        );
        $statement->execute([
            'escola_id' => $schoolId,
            'ano_id' => $yearId
        ]);
        return $statement->fetchAll();
    }

    private function schoolTurmasForYear(
        \PDO $database,
        int $schoolId,
        int $yearId
    ): array {
        $statement = $database->prepare(
            "SELECT t.id, t.identificacao, c.id AS classe_id,
                    c.nome AS classe_nome, c.ordem
             FROM turmas t
             INNER JOIN classes c
                ON c.id = t.classe_id
               AND c.estado = 'activa'
             WHERE t.escola_id = :escola_id
               AND t.ano_lectivo_id = :ano_id
               AND t.estado = 'activa'
               AND EXISTS (
                   SELECT 1 FROM matriculas m
                   INNER JOIN aluno_escolas ae
                       ON ae.id = m.aluno_escola_id
                      AND ae.escola_id = m.escola_id
                      AND ae.estado = 'activo'
                   INNER JOIN alunos a
                       ON a.id = ae.aluno_id
                      AND a.estado = 'activo'
                   WHERE m.turma_id = t.id
                     AND m.escola_id = t.escola_id
                     AND m.ano_lectivo_id = t.ano_lectivo_id
                     AND m.classe_id = t.classe_id
                     AND m.estado = 'activa'
               )
             ORDER BY c.ordem, c.nome,
                      CHAR_LENGTH(t.identificacao), t.identificacao"
        );
        $statement->execute([
            'escola_id' => $schoolId,
            'ano_id' => $yearId
        ]);
        return $statement->fetchAll();
    }

    private function schoolTurmasForClassAndYear(
        \PDO $database,
        int $schoolId,
        int $yearId,
        int $classId
    ): array {
        $statement = $database->prepare(
            "SELECT t.id, t.identificacao
             FROM turmas t
             WHERE t.escola_id = :escola_id
               AND t.ano_lectivo_id = :ano_id
               AND t.classe_id = :classe_id
               AND t.estado = 'activa'
               AND EXISTS (
                   SELECT 1 FROM matriculas m
                   WHERE m.turma_id = t.id
                     AND m.escola_id = t.escola_id
                     AND m.ano_lectivo_id = t.ano_lectivo_id
                     AND m.classe_id = t.classe_id
                     AND m.estado = 'activa'
               )
             ORDER BY CHAR_LENGTH(t.identificacao), t.identificacao"
        );
        $statement->execute([
            'escola_id' => $schoolId,
            'ano_id' => $yearId,
            'classe_id' => $classId
        ]);
        return $statement->fetchAll();
    }

    private function disciplinesForClass(\PDO $database, int $classId): array
    {
        $statement = $database->prepare(
            'SELECT d.id, d.nome
             FROM classes_disciplinas cd
             INNER JOIN disciplinas d ON d.id = cd.disciplina_id
             WHERE cd.classe_id = :classe_id
               AND d.estado = \'activa\'
             ORDER BY d.nome'
        );
        $statement->execute(['classe_id' => $classId]);
        return $statement->fetchAll();
    }

    private function schoolDisciplinesForYear(
        \PDO $database,
        int $schoolId,
        int $yearId
    ): array {
        $statement = $database->prepare(
            "SELECT DISTINCT d.id, d.nome
             FROM classes_disciplinas cd
             INNER JOIN classes c
                ON c.id = cd.classe_id
               AND c.estado = 'activa'
             INNER JOIN disciplinas d
                ON d.id = cd.disciplina_id
               AND d.estado = 'activa'
             WHERE EXISTS (
                 SELECT 1 FROM matriculas m
                 INNER JOIN turmas t
                    ON t.id = m.turma_id
                   AND t.escola_id = m.escola_id
                   AND t.ano_lectivo_id = m.ano_lectivo_id
                   AND t.classe_id = m.classe_id
                   AND t.estado = 'activa'
                 INNER JOIN aluno_escolas ae
                    ON ae.id = m.aluno_escola_id
                   AND ae.escola_id = m.escola_id
                   AND ae.estado = 'activo'
                 INNER JOIN alunos a
                    ON a.id = ae.aluno_id
                   AND a.estado = 'activo'
                 WHERE m.escola_id = :escola_id
                   AND m.ano_lectivo_id = :ano_id
                   AND m.classe_id = c.id
                   AND m.estado = 'activa'
             )
             ORDER BY d.nome"
        );
        $statement->execute([
            'escola_id' => $schoolId,
            'ano_id' => $yearId
        ]);
        return $statement->fetchAll();
    }

    private function existingGradeReports(
        \PDO $database,
        int $schoolId,
        int $yearId,
        ?int $turmaId,
        ?int $disciplineId
    ): array {
        $conditions = [
            'm.escola_id = :escola_id',
            'm.ano_lectivo_id = :ano_id',
            "m.estado = 'activa'",
            "ae.estado = 'activo'",
            "a.estado = 'activo'",
            "t.estado = 'activa'",
            "c.estado = 'activa'",
            "d.estado = 'activa'"
        ];
        $parameters = [
            'escola_id' => $schoolId,
            'ano_id' => $yearId
        ];
        if ($turmaId !== null) {
            $conditions[] = 't.id = :turma_id';
            $parameters['turma_id'] = $turmaId;
        }
        if ($disciplineId !== null) {
            $conditions[] = 'd.id = :disciplina_id';
            $parameters['disciplina_id'] = $disciplineId;
        }

        $statement = $database->prepare(
            'SELECT c.id AS classe_id, c.nome AS classe_nome,
                    c.ordem AS classe_ordem, t.id AS turma_id,
                    t.identificacao AS turma_nome,
                    d.id AS disciplina_id, d.nome AS disciplina_nome,
                    n.trimestre,
                    COUNT(DISTINCT m.id) AS total_alunos,
                    COUNT(DISTINCT CASE
                        WHEN n.acs1 IS NOT NULL
                          OR n.acs2 IS NOT NULL
                          OR n.avaliacao_provincial IS NOT NULL
                        THEN m.id
                    END) AS alunos_com_notas,
                    COUNT(DISTINCT CASE
                        WHEN n.acs1 IS NOT NULL
                         AND n.acs2 IS NOT NULL
                         AND n.avaliacao_provincial IS NOT NULL
                        THEN m.id
                    END) AS alunos_completos
             FROM matriculas m
             INNER JOIN turmas t
                ON t.id = m.turma_id
               AND t.escola_id = m.escola_id
               AND t.ano_lectivo_id = m.ano_lectivo_id
               AND t.classe_id = m.classe_id
             INNER JOIN classes c ON c.id = m.classe_id
             INNER JOIN aluno_escolas ae
                ON ae.id = m.aluno_escola_id
               AND ae.escola_id = m.escola_id
             INNER JOIN alunos a ON a.id = ae.aluno_id
             INNER JOIN classes_disciplinas cd
                ON cd.classe_id = m.classe_id
             INNER JOIN disciplinas d
                ON d.id = cd.disciplina_id
             LEFT JOIN notas_trimestrais n
                ON n.matricula_id = m.id
               AND n.disciplina_id = d.id
               AND n.trimestre BETWEEN 1 AND 3
             WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY c.id, c.nome, c.ordem, t.id, t.identificacao,
                      d.id, d.nome, n.trimestre
             HAVING alunos_com_notas > 0
             ORDER BY c.ordem, c.nome,
                      CHAR_LENGTH(t.identificacao), t.identificacao,
                      d.nome, n.trimestre'
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private function existingGeneralGradeReports(
        \PDO $database,
        int $schoolId,
        int $yearId,
        ?int $turmaId
    ): array {
        $conditions = [
            'm.escola_id = :escola_id',
            'm.ano_lectivo_id = :ano_id',
            "m.estado = 'activa'",
            "ae.estado = 'activo'",
            "a.estado = 'activo'",
            "t.estado = 'activa'",
            "c.estado = 'activa'",
            "d.estado = 'activa'"
        ];
        $parameters = [
            'escola_id' => $schoolId,
            'ano_id' => $yearId
        ];
        if ($turmaId !== null) {
            $conditions[] = 't.id = :turma_id';
            $parameters['turma_id'] = $turmaId;
        }

        $statement = $database->prepare(
            'SELECT c.id AS classe_id, c.nome AS classe_nome,
                    c.ordem AS classe_ordem, t.id AS turma_id,
                    t.identificacao AS turma_nome, n.trimestre,
                    COUNT(DISTINCT m.id) AS total_alunos,
                    COUNT(DISTINCT d.id) AS total_disciplinas,
                    COUNT(DISTINCT CASE
                        WHEN n.acs1 IS NOT NULL
                          OR n.acs2 IS NOT NULL
                          OR n.avaliacao_provincial IS NOT NULL
                        THEN CONCAT(m.id, \':\', d.id)
                    END) AS pares_com_notas,
                    COUNT(DISTINCT CASE
                        WHEN n.acs1 IS NOT NULL
                         AND n.acs2 IS NOT NULL
                         AND n.avaliacao_provincial IS NOT NULL
                        THEN CONCAT(m.id, \':\', d.id)
                    END) AS pares_completos
             FROM matriculas m
             INNER JOIN turmas t
                ON t.id = m.turma_id
               AND t.escola_id = m.escola_id
               AND t.ano_lectivo_id = m.ano_lectivo_id
               AND t.classe_id = m.classe_id
             INNER JOIN classes c ON c.id = m.classe_id
             INNER JOIN aluno_escolas ae
                ON ae.id = m.aluno_escola_id
               AND ae.escola_id = m.escola_id
             INNER JOIN alunos a ON a.id = ae.aluno_id
             INNER JOIN classes_disciplinas cd
                ON cd.classe_id = m.classe_id
             INNER JOIN disciplinas d
                ON d.id = cd.disciplina_id
             LEFT JOIN notas_trimestrais n
                ON n.matricula_id = m.id
               AND n.disciplina_id = d.id
               AND n.trimestre BETWEEN 1 AND 3
             WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY c.id, c.nome, c.ordem, t.id, t.identificacao, n.trimestre
             HAVING n.trimestre IS NOT NULL AND pares_com_notas > 0
             ORDER BY c.ordem, c.nome,
                      CHAR_LENGTH(t.identificacao), t.identificacao, n.trimestre'
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private function studentsForGrades(
        \PDO $database,
        int $schoolId,
        int $yearId,
        int $classId,
        int $turmaId,
        int $disciplineId,
        int $quarter
    ): array {
        $statement = $database->prepare(
            "SELECT m.id AS matricula_id, a.nome_completo, a.codigo_central,
                    actual.acs1, actual.acs2, actual.avaliacao_provincial,
                    actual.nota AS media_trimestre,
                    n1.nota AS media_trimestre1,
                    n2.nota AS media_trimestre2,
                    n3.nota AS media_trimestre3
             FROM matriculas m
             INNER JOIN aluno_escolas ae ON ae.id = m.aluno_escola_id
             INNER JOIN alunos a ON a.id = ae.aluno_id
             LEFT JOIN notas_trimestrais actual
                ON actual.matricula_id = m.id
               AND actual.disciplina_id = :disciplina_actual
               AND actual.trimestre = :trimestre
             LEFT JOIN notas_trimestrais n1
                ON n1.matricula_id = m.id
               AND n1.disciplina_id = :disciplina1
               AND n1.trimestre = 1
             LEFT JOIN notas_trimestrais n2
                ON n2.matricula_id = m.id
               AND n2.disciplina_id = :disciplina2
               AND n2.trimestre = 2
             LEFT JOIN notas_trimestrais n3
                ON n3.matricula_id = m.id
               AND n3.disciplina_id = :disciplina3
               AND n3.trimestre = 3
             WHERE m.escola_id = :escola_id
               AND m.ano_lectivo_id = :ano_id
               AND m.classe_id = :classe_id
               AND m.turma_id = :turma_id
               AND m.estado = 'activa'
               AND ae.estado = 'activo'
               AND a.estado = 'activo'
             ORDER BY a.nome_completo, a.codigo_central"
        );
        $statement->execute([
            'disciplina_actual' => $disciplineId,
            'trimestre' => $quarter,
            'disciplina1' => $disciplineId,
            'disciplina2' => $disciplineId,
            'disciplina3' => $disciplineId,
            'escola_id' => $schoolId,
            'ano_id' => $yearId,
            'classe_id' => $classId,
            'turma_id' => $turmaId
        ]);
        $students = $statement->fetchAll();
        foreach ($students as &$student) {
            $quarters = [
                $student['media_trimestre1'],
                $student['media_trimestre2'],
                $student['media_trimestre3']
            ];
            $student['media_anual'] = in_array(null, $quarters, true)
                ? null
                : number_format(array_sum(array_map('floatval', $quarters)) / 3, 2, '.', '');
            foreach (['acs1', 'acs2', 'avaliacao_provincial', 'media_trimestre'] as $field) {
                if ($student[$field] !== null) {
                    $student[$field] = number_format((float) $student[$field], 2, '.', '');
                }
            }
        }
        unset($student);
        return $students;
    }

    private function selectedId(string $field, array $items): ?int
    {
        $value = $_GET[$field] ?? null;
        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        if ($id !== false && $this->findById($items, (int) $id) !== null) {
            return (int) $id;
        }

        return null;
    }

    private function findById(array $items, ?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        foreach ($items as $item) {
            if ((int) $item['id'] === $id) {
                return $item;
            }
        }
        return null;
    }

    private function postedId(string $field): int|false
    {
        $value = $_POST[$field] ?? null;
        return filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
    }

    private function postedString(string $field): string
    {
        $value = $_POST[$field] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    private function queryId(string $field): int|false
    {
        $value = $_GET[$field] ?? null;
        return filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
    }

    private function selectedQuarter(): int
    {
        $quarter = $this->queryId('trimestre');
        return $quarter !== false && in_array($quarter, [1, 2, 3], true)
            ? $quarter
            : 1;
    }

    private function selectedReportPeriod(): string
    {
        $period = $_GET['periodo'] ?? 'todos';
        return is_string($period)
            && in_array($period, ['todos', '1', '2', '3', 'anual'], true)
                ? $period
                : 'todos';
    }

    private function validateGradeInput(array $grades, array $allowedEnrollments): array|false
    {
        $result = [];
        foreach ($grades as $enrollmentKey => $assessments) {
            $enrollmentId = filter_var((string) $enrollmentKey, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1]
            ]);
            if (
                $enrollmentId === false
                || !isset($allowedEnrollments[(string) $enrollmentId])
                || !is_array($assessments)
            ) {
                return false;
            }
            $values = [];
            foreach ([
                'acs1',
                'acs2',
                'avaliacao_provincial'
            ] as $field) {
                if (!array_key_exists($field, $assessments) || !is_string($assessments[$field])) {
                    return false;
                }
                $value = trim($assessments[$field]);
                if ($value === '') {
                    $values[$field] = null;
                    continue;
                }
                if (preg_match('/^\d{1,2}(?:\.\d{1,2})?$/', $value) !== 1 || (float) $value > 20) {
                    return false;
                }
                $values[$field] = $value;
            }
            $media = in_array(null, $values, true)
                ? null
                : number_format(
                    (
                        (float) $values['acs1']
                        + (float) $values['acs2']
                        + (float) $values['avaliacao_provincial']
                    ) / 3,
                    2,
                    '.',
                    ''
                );
            $result[] = array_merge(
                ['matricula_id' => (int) $enrollmentId],
                $values,
                ['media_trimestre' => $media]
            );
        }
        return $result;
    }

    private function hasValidCsrfToken(): bool
    {
        $token = $this->postedString('_csrf_token');
        if (
            $token !== ''
            && isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token)
        ) {
            return true;
        }

        http_response_code(419);
        echo 'A sessão expirou. Actualize a página e tente novamente.';
        return false;
    }

    private function setFlash(string $type, string $message): void
    {
        $_SESSION['_school_flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }

    private function redirectToGrades(array $filters, bool $launch = false): never
    {
        $query = [];
        foreach ($filters as $key => $value) {
            if (is_int($value) && $value > 0) {
                $query[$key] = $value;
            }
        }
        $suffix = $query === [] ? '' : '?' . http_build_query($query);
        $path = $launch ? '/escola/notas/lancar' : '/escola/notas';
        $this->redirect($path . $suffix);
    }

    private function sendPdf(string $pdf, string $filename, bool $inline = false): void
    {
        header('Content-Type: application/pdf');
        header(
            'Content-Disposition: '
            . ($inline ? 'inline' : 'attachment')
            . '; filename="' . $filename . '"'
        );
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $pdf;
    }

    private function documentStatusLabels(): array
    {
        return [
            'pendente' => 'Pendente',
            'em_analise' => 'Em análise',
            'emitido' => 'Emitido',
            'rejeitado' => 'Rejeitado'
        ];
    }

    private function invalidateAuthentication(): void
    {
        unset($_SESSION['auth']);
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    private function preventCaching(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $this->basePath() . $path);
        exit;
    }

    private function basePath(): string
    {
        $scriptDir = str_replace(
            '\\',
            '/',
            dirname($_SERVER['SCRIPT_NAME'] ?? '/')
        );
        $basePath = rtrim(
            (string) preg_replace('#/public$#', '', $scriptDir),
            '/'
        );

        return $basePath;
    }
}
