<?php

namespace App\Services;

use PDO;

final class EnrollmentPlacement
{
    public const MAX_STUDENTS_PER_CLASSROOM = 50;

    public static function findOrCreateClassroom(
        PDO $database,
        int $schoolId,
        int $classId,
        int $academicYearId,
        ?string $shift = null
    ): array {
        $statement = $database->prepare(
            "SELECT t.id, t.identificacao, t.capacidade, t.estado, t.turno,
                    COUNT(m.id) AS matriculas_activas
             FROM turmas t
             LEFT JOIN matriculas m
                ON m.turma_id = t.id
               AND m.estado = 'activa'
             WHERE t.escola_id = :escola_id
               AND t.classe_id = :classe_id
               AND t.ano_lectivo_id = :ano_lectivo_id
             GROUP BY t.id, t.identificacao, t.capacidade, t.estado, t.turno
             ORDER BY CHAR_LENGTH(t.identificacao), t.identificacao"
        );
        $statement->execute([
            'escola_id' => $schoolId,
            'classe_id' => $classId,
            'ano_lectivo_id' => $academicYearId
        ]);

        $sections = [];
        foreach ($statement->fetchAll() as $section) {
            $identifier = (string) $section['identificacao'];
            if (preg_match('/^[A-Z]+$/i', $identifier) !== 1) {
                continue;
            }

            $label = strtoupper($identifier);
            $sections[$label] = [
                'id' => (int) $section['id'],
                'identificacao' => $identifier,
                'capacidade' => $section['capacidade'],
                'estado' => $section['estado'],
                'turno' => $section['turno'],
                'matriculas_activas' => (int) $section['matriculas_activas'],
                'label_is_canonical' => $identifier === $label
            ];
        }

        for ($number = 1; ; $number++) {
            $label = self::alphabeticClassroomLabel($number);
            if (!isset($sections[$label])) {
                $create = $database->prepare(
                    "INSERT INTO turmas
                        (escola_id, classe_id, ano_lectivo_id, identificacao,
                         turno, capacidade, estado)
                     VALUES
                        (:escola_id, :classe_id, :ano_lectivo_id, :identificacao,
                         :turno, :capacidade, 'activa')"
                );
                $create->execute([
                    'escola_id' => $schoolId,
                    'classe_id' => $classId,
                    'ano_lectivo_id' => $academicYearId,
                    'identificacao' => $label,
                    'turno' => $shift,
                    'capacidade' => self::MAX_STUDENTS_PER_CLASSROOM
                ]);
                $classroomId = (int) $database->lastInsertId();
                if ($classroomId < 1) {
                    throw new \RuntimeException(
                        'Não foi possível obter o identificador da turma criada.'
                    );
                }

                return [
                    'id' => $classroomId,
                    'identificacao' => $label
                ];
            }

            $section = $sections[$label];
            if (
                $section['estado'] !== 'activa'
                || !$section['label_is_canonical']
                || ($shift !== null && $section['turno'] !== $shift)
            ) {
                continue;
            }
            $configuredCapacity = $section['capacidade'] === null
                ? self::MAX_STUDENTS_PER_CLASSROOM
                : (int) $section['capacidade'];
            $capacityLimit = min(
                self::MAX_STUDENTS_PER_CLASSROOM,
                $configuredCapacity
            );
            if ($section['matriculas_activas'] < $capacityLimit) {
                return [
                    'id' => $section['id'],
                    'identificacao' => $section['identificacao']
                ];
            }
        }
    }

    public static function nextProcessNumber(
        PDO $database,
        int $schoolId,
        int $academicYearId
    ): int {
        $ensureSequence = $database->prepare(
            'INSERT INTO sequencias_processos
                (escola_id, ano_lectivo_id, ultimo_numero)
             VALUES
                (:escola_id, :ano_lectivo_id, 0)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $ensureSequence->execute([
            'escola_id' => $schoolId,
            'ano_lectivo_id' => $academicYearId
        ]);

        $sequenceQuery = $database->prepare(
            'SELECT ultimo_numero FROM sequencias_processos
             WHERE escola_id = :escola_id
               AND ano_lectivo_id = :ano_lectivo_id
             FOR UPDATE'
        );
        $sequenceQuery->execute([
            'escola_id' => $schoolId,
            'ano_lectivo_id' => $academicYearId
        ]);
        $lastNumber = (int) $sequenceQuery->fetchColumn();

        $existingMaximum = $database->prepare(
            'SELECT COALESCE(MAX(numero_processo), 0)
             FROM matriculas
             WHERE escola_id = :escola_id
               AND ano_lectivo_id = :ano_lectivo_id'
        );
        $existingMaximum->execute([
            'escola_id' => $schoolId,
            'ano_lectivo_id' => $academicYearId
        ]);
        $lastNumber = max($lastNumber, (int) $existingMaximum->fetchColumn());
        if ($lastNumber >= 4294967295) {
            throw new \RuntimeException(
                'A sequência de números de processo atingiu o limite permitido.'
            );
        }

        $nextNumber = $lastNumber + 1;
        $updateSequence = $database->prepare(
            'UPDATE sequencias_processos
             SET ultimo_numero = :numero
             WHERE escola_id = :escola_id
               AND ano_lectivo_id = :ano_lectivo_id'
        );
        $updateSequence->execute([
            'numero' => $nextNumber,
            'escola_id' => $schoolId,
            'ano_lectivo_id' => $academicYearId
        ]);

        return $nextNumber;
    }

    private static function alphabeticClassroomLabel(int $number): string
    {
        if ($number < 1) {
            throw new \InvalidArgumentException('A sequência da turma deve ser positiva.');
        }

        $label = '';
        while ($number > 0) {
            $number--;
            $label = chr(65 + ($number % 26)) . $label;
            $number = intdiv($number, 26);
        }
        if (strlen($label) > 50) {
            throw new \RuntimeException(
                'A sequência de turmas excedeu o tamanho permitido.'
            );
        }

        return $label;
    }
}
