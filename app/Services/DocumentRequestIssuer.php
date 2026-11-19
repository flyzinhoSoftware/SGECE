<?php

namespace App\Services;

use PDO;
use Throwable;

final class DocumentRequestIssuer
{
    /**
     * @return array{pdf: string, filename: string}
     */
    public function issue(
        PDO $database,
        int $requestId,
        ?int $schoolId = null
    ): array {
        if ($requestId < 1 || ($schoolId !== null && $schoolId < 1)) {
            throw new \InvalidArgumentException('Indique um pedido válido.');
        }

        if ($database->inTransaction()) {
            throw new \LogicException('A emissão deve iniciar fora de outra transacção.');
        }

        $database->beginTransaction();
        try {
            $schoolCondition = $schoolId === null
                ? ''
                : ' AND pd.escola_id = :escola_id';
            $parameters = ['pedido_id' => $requestId];
            if ($schoolId !== null) {
                $parameters['escola_id'] = $schoolId;
            }

            $requestStatement = $database->prepare(
                'SELECT pd.id, pd.codigo, pd.tipo_documento, pd.estado,
                        pd.matricula_id, pd.escola_id,
                        a.nome_completo AS aluno_nome, a.codigo_central,
                        a.data_nascimento, a.data_emissao_bi, a.bi_cifrado, a.sexo,
                        a.naturalidade, a.nome_pai, a.nome_mae,
                        c.id AS classe_id, c.nome AS classe_nome, c.tem_exame,
                        t.identificacao AS turma_nome, t.turno AS turma_turno,
                        al.ano AS ano_lectivo, al.estado AS ano_estado,
                        e.codigo AS escola_codigo, e.nome AS escola_nome,
                        e.nome_director AS escola_director,
                        e.nome_chefe_secretaria AS escola_chefe_secretaria,
                        e.tipo AS escola_tipo,
                        e.provincia AS escola_provincia, e.distrito AS escola_distrito,
                        e.localidade AS escola_localidade, e.endereco AS escola_endereco
                 FROM pedidos_documentos pd
                 INNER JOIN aluno_escolas ae
                    ON ae.id = pd.aluno_escola_id
                   AND ae.escola_id = pd.escola_id
                 INNER JOIN alunos a ON a.id = ae.aluno_id
                 INNER JOIN escolas e ON e.id = pd.escola_id
                 LEFT JOIN matriculas m
                    ON m.id = pd.matricula_id
                   AND m.escola_id = pd.escola_id
                   AND m.aluno_escola_id = pd.aluno_escola_id
                 LEFT JOIN classes c ON c.id = m.classe_id
                 LEFT JOIN turmas t
                    ON t.id = m.turma_id
                   AND t.escola_id = m.escola_id
                   AND t.ano_lectivo_id = m.ano_lectivo_id
                   AND t.classe_id = m.classe_id
                 LEFT JOIN anos_lectivos al ON al.id = m.ano_lectivo_id
                 WHERE pd.id = :pedido_id' . $schoolCondition . '
                 LIMIT 1
                 FOR UPDATE'
            );
            $requestStatement->execute($parameters);
            $request = $requestStatement->fetch();
            if (!$request) {
                throw new \DomainException('O pedido não foi encontrado nesta área.');
            }
            if ($request['estado'] !== 'aprovado') {
                throw new \DomainException('Só pedidos aprovados podem ser emitidos.');
            }
            if (
                $request['matricula_id'] === null
                || $request['classe_id'] === null
                || $request['turma_nome'] === null
            ) {
                throw new \DomainException(
                    'O pedido não tem uma matrícula válida.'
                );
            }
            $hasDirector = trim((string) $request['escola_director']) !== '';
            $hasSecretary = trim((string) $request['escola_chefe_secretaria']) !== '';
            if ($request['tipo_documento'] === 'certificado' && !$hasDirector) {
                throw new \DomainException(
                    'Registe o nome do director da escola antes de emitir o certificado.'
                );
            }
            if (
                in_array(
                    $request['tipo_documento'],
                    ['declaracao', 'declaracao_notas'],
                    true
                )
                && !$hasDirector
                && !$hasSecretary
            ) {
                throw new \DomainException(
                    'Registe o nome do director ou do chefe da secretaria antes de emitir o documento.'
                );
            }

            $isQuarterlyStatement = $request['tipo_documento'] === 'declaracao_notas';
            $annualResults = null;
            $quarterResults = null;
            if ($isQuarterlyStatement) {
                if (!in_array($request['ano_estado'], ['activo', 'encerrado'], true)) {
                    throw new \DomainException(
                        'A declaração de notas só pode ser emitida para um ano lectivo iniciado.'
                    );
                }
                $quarterResults = $this->completedQuarterResults(
                    $database,
                    (int) $request['matricula_id'],
                    (int) $request['classe_id']
                );
            } else {
                if ($request['ano_estado'] !== 'encerrado') {
                    throw new \DomainException(
                        'Os documentos anuais só podem ser emitidos após o encerramento do ano lectivo.'
                    );
                }

                $expectedType = (int) $request['tem_exame'] === 1
                    ? 'certificado'
                    : 'declaracao';
                if ($request['tipo_documento'] !== $expectedType) {
                    throw new \DomainException(
                        'O tipo de documento do pedido não corresponde à classe do aluno.'
                    );
                }
                if (trim((string) $request['naturalidade']) === '') {
                    throw new \DomainException(
                        'Registe a naturalidade do aluno antes de emitir o documento anual.'
                    );
                }
                if (trim((string) $request['turma_turno']) === '') {
                    throw new \DomainException(
                        'Defina o turno da turma antes de emitir o documento anual.'
                    );
                }
                $annualResults = $this->annualResults(
                    $database,
                    (int) $request['matricula_id'],
                    (int) $request['classe_id']
                );
                if ($annualResults['media_final'] < 10) {
                    throw new \DomainException(
                        'O aluno já não satisfaz o aproveitamento mínimo para emissão.'
                    );
                }
            }

            $issuedAt = date('Y-m-d H:i:s');
            $validationCode = strtoupper(bin2hex(random_bytes(16)));
            if (in_array(
                $request['tipo_documento'],
                ['declaracao', 'declaracao_notas'],
                true
            )) {
                $request['bi_numero'] = StudentIdentity::decrypt(
                    (string) $request['bi_cifrado']
                );
            }
            $documentContent = $this->documentContent($request, $quarterResults);
            $storedDocumentContent = $documentContent;
            if (isset($request['bi_numero'])) {
                $identityLabel = 'Bilhete de Identidade n.º '
                    . $request['bi_numero'];
                $storedDocumentContent = str_replace(
                    $identityLabel,
                    'Bilhete de Identidade n.º ________________',
                    $documentContent,
                    $replacementCount
                );
                if ($replacementCount !== 1) {
                    throw new \RuntimeException(
                        'Não foi possível ocultar o BI no registo do documento.'
                    );
                }
            }
            $pdf = (new SchoolPdfGenerator())->issuedDocument([
                'tipo_documento' => $request['tipo_documento'],
                'codigo' => $request['codigo'],
                'codigo_validacao' => $validationCode,
                'aluno_nome' => $request['aluno_nome'],
                'codigo_central' => $request['codigo_central'],
                'data_nascimento' => $request['data_nascimento'],
                'nome_pai' => $request['nome_pai'],
                'nome_mae' => $request['nome_mae'],
                'escola_codigo' => $request['escola_codigo'],
                'escola_nome' => $request['escola_nome'],
                'escola_director' => $request['escola_director'],
                'escola_chefe_secretaria' => $request['escola_chefe_secretaria'],
                'escola_tipo' => $request['escola_tipo'],
                'escola_provincia' => $request['escola_provincia'],
                'escola_distrito' => $request['escola_distrito'],
                'escola_localidade' => $request['escola_localidade'],
                'escola_endereco' => $request['escola_endereco'],
                'classe_nome' => $request['classe_nome'],
                'turma_nome' => $request['turma_nome'],
                'ano_lectivo' => $request['ano_lectivo'],
                'conteudo_documento' => $documentContent,
                'annual_grades' => $annualResults['disciplinas'] ?? [],
                'media_final' => $annualResults['media_final'] ?? null,
                'quarter_grades' => $quarterResults['disciplinas'] ?? [],
                'emitido_em' => $issuedAt
            ]);

            $updateCondition = $schoolId === null
                ? ''
                : ' AND escola_id = :escola_id';
            $updateParameters = [
                'conteudo_documento' => $storedDocumentContent,
                'codigo_validacao' => $validationCode,
                'documento_pdf' => $pdf,
                'emitido_em' => $issuedAt,
                'concluido_em' => $issuedAt,
                'id' => $requestId
            ];
            if ($schoolId !== null) {
                $updateParameters['escola_id'] = $schoolId;
            }
            $update = $database->prepare(
                "UPDATE pedidos_documentos
                 SET estado = 'emitido',
                     conteudo_documento = :conteudo_documento,
                     codigo_validacao = :codigo_validacao,
                     documento_pdf = :documento_pdf,
                     emitido_em = :emitido_em,
                     concluido_em = :concluido_em
                 WHERE id = :id
                   AND estado = 'aprovado'" . $updateCondition
            );
            $update->execute($updateParameters);
            if ($update->rowCount() !== 1) {
                throw new \DomainException(
                    'O pedido deixou de estar aprovado antes da emissão.'
                );
            }

            $database->commit();

            return [
                'pdf' => $pdf,
                'filename' => match ($request['tipo_documento']) {
                    'certificado' => 'certificado-habilitacoes-'
                        . $requestId . '.pdf',
                    'declaracao_notas' => 'declaracao-notas-'
                        . $requestId . '.pdf',
                    default => 'declaracao-escolar-' . $requestId . '.pdf'
                }
            ];
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function annualResults(PDO $database, int $enrollmentId, int $classId): array
    {
        $statement = $database->prepare(
            'SELECT d.nome,
                    CASE WHEN n1.acs1 IS NOT NULL AND n1.acs2 IS NOT NULL
                               AND n1.avaliacao_provincial IS NOT NULL
                         THEN COALESCE(
                             n1.nota,
                             (n1.acs1 + n1.acs2 + n1.avaliacao_provincial) / 3
                         ) END AS trimestre1,
                    CASE WHEN n2.acs1 IS NOT NULL AND n2.acs2 IS NOT NULL
                               AND n2.avaliacao_provincial IS NOT NULL
                         THEN COALESCE(
                             n2.nota,
                             (n2.acs1 + n2.acs2 + n2.avaliacao_provincial) / 3
                         ) END AS trimestre2,
                    CASE WHEN n3.acs1 IS NOT NULL AND n3.acs2 IS NOT NULL
                               AND n3.avaliacao_provincial IS NOT NULL
                         THEN COALESCE(
                             n3.nota,
                             (n3.acs1 + n3.acs2 + n3.avaliacao_provincial) / 3
                         ) END AS trimestre3
             FROM classes_disciplinas cd
             INNER JOIN disciplinas d
                ON d.id = cd.disciplina_id
               AND d.estado = \'activa\'
             LEFT JOIN notas_trimestrais n1
                ON n1.matricula_id = :matricula_1
               AND n1.disciplina_id = cd.disciplina_id
               AND n1.trimestre = 1
             LEFT JOIN notas_trimestrais n2
                ON n2.matricula_id = :matricula_2
               AND n2.disciplina_id = cd.disciplina_id
               AND n2.trimestre = 2
             LEFT JOIN notas_trimestrais n3
                ON n3.matricula_id = :matricula_3
               AND n3.disciplina_id = cd.disciplina_id
               AND n3.trimestre = 3
             WHERE cd.classe_id = :classe_id
             ORDER BY d.nome'
        );
        $statement->execute([
            'matricula_1' => $enrollmentId,
            'matricula_2' => $enrollmentId,
            'matricula_3' => $enrollmentId,
            'classe_id' => $classId
        ]);
        $rows = $statement->fetchAll();
        if ($rows === []) {
            throw new \DomainException('A classe não tem disciplinas para o certificado.');
        }

        $subjects = [];
        $sum = 0.0;
        foreach ($rows as $row) {
            $quarters = [
                $row['trimestre1'],
                $row['trimestre2'],
                $row['trimestre3']
            ];
            if (in_array(null, $quarters, true)) {
                throw new \DomainException(
                    'Há notas anuais em falta; não é possível emitir este documento.'
                );
            }
            $average = round(
                array_sum(array_map('floatval', $quarters)) / 3,
                2
            );
            $subjects[] = [
                'disciplina' => $row['nome'],
                'media_anual' => $average
            ];
            $sum += $average;
        }

        return [
            'disciplinas' => $subjects,
            'media_final' => round($sum / count($subjects), 2)
        ];
    }

    private function completedQuarterResults(
        PDO $database,
        int $enrollmentId,
        int $classId
    ): array {
        $statement = $database->prepare(
            'SELECT d.nome,
                    CASE WHEN n1.acs1 IS NOT NULL AND n1.acs2 IS NOT NULL
                               AND n1.avaliacao_provincial IS NOT NULL
                         THEN COALESCE(
                             n1.nota,
                             (n1.acs1 + n1.acs2 + n1.avaliacao_provincial) / 3
                         ) END AS trimestre1,
                    CASE WHEN n2.acs1 IS NOT NULL AND n2.acs2 IS NOT NULL
                               AND n2.avaliacao_provincial IS NOT NULL
                         THEN COALESCE(
                             n2.nota,
                             (n2.acs1 + n2.acs2 + n2.avaliacao_provincial) / 3
                         ) END AS trimestre2,
                    CASE WHEN n3.acs1 IS NOT NULL AND n3.acs2 IS NOT NULL
                               AND n3.avaliacao_provincial IS NOT NULL
                         THEN COALESCE(
                             n3.nota,
                             (n3.acs1 + n3.acs2 + n3.avaliacao_provincial) / 3
                         ) END AS trimestre3
             FROM classes_disciplinas cd
             INNER JOIN disciplinas d
                ON d.id = cd.disciplina_id
               AND d.estado = \'activa\'
             LEFT JOIN notas_trimestrais n1
                ON n1.matricula_id = :matricula_1
               AND n1.disciplina_id = cd.disciplina_id
               AND n1.trimestre = 1
             LEFT JOIN notas_trimestrais n2
                ON n2.matricula_id = :matricula_2
               AND n2.disciplina_id = cd.disciplina_id
               AND n2.trimestre = 2
             LEFT JOIN notas_trimestrais n3
                ON n3.matricula_id = :matricula_3
               AND n3.disciplina_id = cd.disciplina_id
               AND n3.trimestre = 3
             WHERE cd.classe_id = :classe_id
             ORDER BY d.nome'
        );
        $statement->execute([
            'matricula_1' => $enrollmentId,
            'matricula_2' => $enrollmentId,
            'matricula_3' => $enrollmentId,
            'classe_id' => $classId
        ]);
        $rows = $statement->fetchAll();
        if ($rows === []) {
            throw new \DomainException(
                'A classe não tem disciplinas para a declaração de notas.'
            );
        }

        $completedQuarters = [];
        $grades = [];
        for ($quarter = 1; $quarter <= 3; $quarter++) {
            $key = 'trimestre' . $quarter;
            $quarterGrades = [];
            foreach ($rows as $row) {
                if ($row[$key] === null) {
                    $quarterGrades = [];
                    break;
                }
                $quarterGrades[] = [
                    'disciplina' => $row['nome'],
                    'trimestre' => $quarter,
                    'media' => round((float) $row[$key], 2)
                ];
            }
            if ($quarterGrades !== []) {
                $completedQuarters[] = $quarter;
                array_push($grades, ...$quarterGrades);
            }
        }
        if ($completedQuarters === []) {
            throw new \DomainException(
                'O aluno ainda não tem trimestres completos para emitir a declaração.'
            );
        }

        return [
            'trimestres' => $completedQuarters,
            'disciplinas' => $grades
        ];
    }

    private function documentContent(
        array $request,
        ?array $quarterResults = null
    ): string
    {
        if ($request['tipo_documento'] === 'declaracao_notas') {
            $quarterLabels = array_map(
                static fn(int $quarter): string => $quarter . '.º trimestre',
                $quarterResults['trimestres'] ?? []
            );
            $academicProgress = $request['ano_estado'] === 'encerrado'
                ? 'Frequentou e concluiu a '
                    . $request['classe_nome']
                    . ' do Sistema Nacional de Educação'
                : 'Frequentou a '
                    . $request['classe_nome']
                    . ' do Sistema Nacional de Educação';

            return $this->declarationIdentityPrefix($request)
                . $academicProgress
                . ', no turno ' . $this->declarationShift($request['turma_turno'])
                . ', na turma ' . $request['turma_nome']
                . ', no ano lectivo de ' . $request['ano_lectivo']
                . ($request['ano_estado'] === 'encerrado'
                    ? ', com as classificações dos trimestres concluídos de '
                        . implode(', ', $quarterLabels) . ':'
                    : '. O ano lectivo ainda decorre; esta declaração apresenta '
                        . 'as classificações dos trimestres concluídos de '
                        . implode(', ', $quarterLabels) . ':');
        }

        if ($request['tipo_documento'] === 'declaracao') {
            return $this->declarationIdentityPrefix($request)
                . 'Frequentou e concluiu a ' . $request['classe_nome']
                . ' do Sistema Nacional de Educação, no turno '
                . $this->declarationShift($request['turma_turno'])
                . ', na turma ' . $request['turma_nome']
                . ', no ano lectivo de ' . $request['ano_lectivo']
                . ', com as seguintes classificações:';
        }

        if ($request['tipo_documento'] === 'certificado') {
            $father = trim((string) ($request['nome_pai'] ?? ''));
            $mother = trim((string) ($request['nome_mae'] ?? ''));
            $father = $father === '' ? '________________' : $father;
            $mother = $mother === '' ? '________________' : $mother;
            $turn = trim((string) ($request['turma_turno'] ?? ''));
            $turnDescription = match ($turn) {
                'Manha', 'manha', 'Manhã', 'manhã' => 'da manhã',
                'Tarde', 'tarde' => 'da tarde',
                'Noite', 'noite' => 'da noite',
                '' => '________________',
                default => 'de ' . $turn
            };
            $declarationVerb = $request['tipo_documento'] === 'certificado'
                ? 'Certifico'
                : 'Declaro';

            return trim((string) $request['escola_director'])
                . ', Docente N1 e director da ' . $request['escola_nome']
                . '. ' . $declarationVerb
                . ', em cumprimento do despacho exarado em requerimento '
                . 'que fica arquivado nesta Secretaria, que '
                . $request['aluno_nome'] . ', natural de '
                . trim((string) $request['naturalidade'])
                . ', Filho(a) de ' . $father . ' e de ' . $mother
                . ', nascido(a) a '
                . $this->formatPortugueseDate($request['data_nascimento'])
                . '. Frequentou e concluiu a ' . $request['classe_nome']
                . ' do Sistema Nacional de Educação, no turno '
                . $turnDescription . ', na turma ' . $request['turma_nome']
                . ' e no ano lectivo de ' . $request['ano_lectivo']
                . ', com as seguintes classificações:';
        }

        $academicDetails = $request['classe_nome']
            . ', turma ' . $request['turma_nome']
            . ', no ano lectivo de ' . $request['ano_lectivo'];

        return 'Declara-se, para os devidos efeitos, que ' . $request['aluno_nome']
            . ' (' . $request['codigo_central'] . ') concluiu com aproveitamento a '
            . $academicDetails . ', nesta escola.';
    }

    private function declarationIdentityPrefix(array $request): string
    {
        $signatory = trim((string) ($request['escola_director'] ?? ''));
        $role = 'Director da Escola';
        if ($signatory === '') {
            $signatory = trim((string) ($request['escola_chefe_secretaria'] ?? ''));
            $role = 'Chefe da Secretaria';
        }

        $father = trim((string) ($request['nome_pai'] ?? ''));
        $mother = trim((string) ($request['nome_mae'] ?? ''));
        $father = $father === '' ? '________________' : $father;
        $mother = $mother === '' ? '________________' : $mother;
        $identityNumber = trim((string) ($request['bi_numero'] ?? ''));
        $identityNumber = $identityNumber === '' ? '________________' : $identityNumber;
        $identityIssueDate = empty($request['data_emissao_bi'])
            ? '___ de __________ de ______'
            : $this->formatPortugueseDate($request['data_emissao_bi']);
        $childTerm = match ($request['sexo'] ?? '') {
            'Feminino' => 'filha',
            'Masculino' => 'filho',
            default => 'filho(a)'
        };

        return $signatory . ', ' . $role . ' da ' . $request['escola_nome']
            . ', certifica em cumprimento do despacho exarado em requerimento '
            . 'que fica arquivado nesta Secretaria, que '
            . $request['aluno_nome'] . ', natural de '
            . $this->formatNaturalidade((string) ($request['naturalidade'] ?? ''))
            . ', ' . $childTerm . ' de ' . $father . ' e de ' . $mother
            . ', nascido(a) aos '
            . $this->formatPortugueseDate($request['data_nascimento'])
            . ', portador do Bilhete de Identidade n.º ' . $identityNumber
            . ', emitido aos ' . $identityIssueDate . '. ';
    }

    private function declarationShift(?string $shift): string
    {
        return match (trim((string) $shift)) {
            'Manha', 'manha', 'Manhã', 'manhã', 'Tarde', 'tarde' => 'diurno',
            'Noite', 'noite' => 'noturno',
            default => '________________'
        };
    }

    private function formatNaturalidade(string $naturalidade): string
    {
        $parts = preg_split('/\s*[,;]\s*/u', trim($naturalidade), 2);
        $locality = trim((string) ($parts[0] ?? ''));
        $province = trim((string) ($parts[1] ?? ''));
        $province = preg_replace(
            '/^prov[ií]ncia(?:\s+de)?\s+/iu',
            '',
            $province
        ) ?? $province;
        $locality = $locality === '' ? '________________' : $locality;
        $province = trim($province);
        $province = $province === '' ? '________________' : $province;

        return $locality . ', Província de ' . $province;
    }

    private function formatPortugueseDate(string $date): string
    {
        $parsedDate = new \DateTimeImmutable($date);
        $months = [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro'
        ];

        return $parsedDate->format('d') . ' de '
            . $months[(int) $parsedDate->format('n')] . ' de '
            . $parsedDate->format('Y');
    }
}
