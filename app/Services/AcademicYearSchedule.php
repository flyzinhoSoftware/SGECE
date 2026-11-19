<?php

namespace App\Services;

use PDO;
use PDOException;

final class AcademicYearSchedule
{
    private const TIME_ZONE = 'Africa/Maputo';
    private const START_MONTH_DAY = '02-14';
    private const END_MONTH_DAY = '12-20';

    public static function activateIfDue(
        PDO $database,
        ?\DateTimeImmutable $today = null
    ): bool {
        $today ??= new \DateTimeImmutable(
            'today',
            new \DateTimeZone(self::TIME_ZONE)
        );
        $year = (int) $today->format('Y');
        $date = $today->format('Y-m-d');
        if (
            $date < $year . '-' . self::START_MONTH_DAY
            || $date > $year . '-' . self::END_MONTH_DAY
        ) {
            return false;
        }

        $currentYearQuery = $database->prepare(
            'SELECT id, estado FROM anos_lectivos WHERE ano = :ano LIMIT 1'
        );
        $currentYearQuery->execute(['ano' => $year]);
        $currentYear = $currentYearQuery->fetch(PDO::FETCH_ASSOC);
        if ($currentYear && $currentYear['estado'] !== 'planeado') {
            return false;
        }

        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }

        try {
            $years = $database->query(
                'SELECT id, ano, estado
                 FROM anos_lectivos
                 ORDER BY ano, id
                 FOR UPDATE'
            )->fetchAll(PDO::FETCH_ASSOC);
            $currentYear = null;
            $activeYears = [];
            foreach ($years as $academicYear) {
                if ((int) $academicYear['ano'] === $year) {
                    $currentYear = $academicYear;
                }
                if ($academicYear['estado'] === 'activo') {
                    $activeYears[] = $academicYear;
                }
            }

            if ($currentYear && $currentYear['estado'] !== 'planeado') {
                if ($ownsTransaction) {
                    $database->commit();
                }
                return false;
            }

            foreach ($activeYears as $activeYear) {
                if (!AcademicYearCompletion::hasCompleteThirdQuarter(
                    $database,
                    (int) $activeYear['id']
                )) {
                    if ($ownsTransaction) {
                        $database->commit();
                    }
                    return false;
                }
            }

            if ($activeYears !== []) {
                $closeYears = $database->prepare(
                    "UPDATE anos_lectivos
                     SET estado = 'encerrado'
                     WHERE estado = 'activo'"
                );
                $closeYears->execute();
            }

            $startDate = $year . '-' . self::START_MONTH_DAY;
            $endDate = $year . '-' . self::END_MONTH_DAY;
            if ($currentYear) {
                $activate = $database->prepare(
                    "UPDATE anos_lectivos
                     SET data_inicio = :data_inicio,
                         data_fim = :data_fim,
                         estado = 'activo'
                     WHERE id = :id AND estado = 'planeado'"
                );
                $activate->execute([
                    'data_inicio' => $startDate,
                    'data_fim' => $endDate,
                    'id' => $currentYear['id']
                ]);
                if ($activate->rowCount() !== 1) {
                    throw new \RuntimeException(
                        'Não foi possível activar o ano lectivo previsto.'
                    );
                }
            } else {
                $create = $database->prepare(
                    "INSERT INTO anos_lectivos
                        (ano, data_inicio, data_fim, estado)
                     VALUES
                        (:ano, :data_inicio, :data_fim, 'activo')"
                );
                $create->execute([
                    'ano' => $year,
                    'data_inicio' => $startDate,
                    'data_fim' => $endDate
                ]);
            }

            if ($ownsTransaction) {
                $database->commit();
            }
            return true;
        } catch (PDOException $exception) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            if (
                $ownsTransaction
                && (int) ($exception->errorInfo[1] ?? 0) === 1062
            ) {
                $currentYearQuery->execute(['ano' => $year]);
                $currentYear = $currentYearQuery->fetch(PDO::FETCH_ASSOC);
                if ($currentYear && $currentYear['estado'] === 'activo') {
                    return false;
                }
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }
}
