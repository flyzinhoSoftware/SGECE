<?php

namespace App\Services;

use PDO;

final class AcademicYearCompletion
{
    public static function hasCompleteThirdQuarter(PDO $database, int $yearId): bool
    {
        if ($yearId < 1) {
            throw new \InvalidArgumentException('Indique um ano lectivo válido.');
        }

        $statement = $database->prepare(
            "SELECT COUNT(DISTINCT m.id) AS matriculas_activas,
                    COUNT(DISTINCT CASE
                        WHEN NOT EXISTS (
                            SELECT 1
                            FROM classes_disciplinas cd
                            INNER JOIN disciplinas d
                                ON d.id = cd.disciplina_id
                               AND d.estado = 'activa'
                            WHERE cd.classe_id = m.classe_id
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM classes_disciplinas cd
                            INNER JOIN disciplinas d
                                ON d.id = cd.disciplina_id
                               AND d.estado = 'activa'
                            WHERE cd.classe_id = m.classe_id
                              AND NOT EXISTS (
                                  SELECT 1
                                  FROM notas_trimestrais n
                                  WHERE n.matricula_id = m.id
                                    AND n.disciplina_id = d.id
                                    AND n.trimestre = 3
                                    AND n.acs1 IS NOT NULL
                                    AND n.acs2 IS NOT NULL
                                    AND n.avaliacao_provincial IS NOT NULL
                              )
                        )
                        THEN m.id
                    END) AS matriculas_incompletas
             FROM matriculas m
             INNER JOIN classes c
                ON c.id = m.classe_id
               AND c.estado = 'activa'
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
             WHERE m.ano_lectivo_id = :ano_id
               AND m.estado = 'activa'"
        );
        $statement->execute(['ano_id' => $yearId]);
        $counts = $statement->fetch(PDO::FETCH_ASSOC);

        return (int) $counts['matriculas_activas'] > 0
            && (int) $counts['matriculas_incompletas'] === 0;
    }

    public static function closeIfComplete(PDO $database, int $yearId): bool
    {
        if (!self::hasCompleteThirdQuarter($database, $yearId)) {
            return false;
        }

        $statement = $database->prepare(
            "UPDATE anos_lectivos
             SET estado = 'encerrado'
             WHERE id = :id AND estado = 'activo'"
        );
        $statement->execute(['id' => $yearId]);

        return $statement->rowCount() === 1;
    }
}
