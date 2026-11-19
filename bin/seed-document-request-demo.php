<?php

declare(strict_types=1);

use Config\Database;
use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';
Dotenv::createImmutable($projectRoot)->load();

if (($_ENV['DB_NAME'] ?? '') !== 'sgece_db') {
    throw new RuntimeException(
        'A carga de demonstração só pode ser executada na base sgece_db.'
    );
}

$database = Database::connect();
$schoolQuery = $database->prepare(
    'SELECT id FROM escolas WHERE codigo = :codigo LIMIT 1'
);
$schoolQuery->execute(['codigo' => 'SGECE-ESC-TESTE-01']);
$schoolId = $schoolQuery->fetchColumn();
if ($schoolId === false) {
    throw new RuntimeException('A escola de teste não existe na base de dados.');
}
$schoolId = (int) $schoolId;

$applicationKey = $_ENV['APP_KEY'] ?? '';
if (strlen($applicationKey) < 32) {
    throw new RuntimeException('APP_KEY não está configurada adequadamente.');
}

$schoolLevelId = $database->query(
    "SELECT id FROM niveis_acesso WHERE nome = 'Escola' LIMIT 1"
)->fetchColumn();
if ($schoolLevelId === false) {
    throw new RuntimeException('O nível Escola não existe na base de dados.');
}
$schoolLevelId = (int) $schoolLevelId;

$classRows = $database->query(
    "SELECT c.id, c.codigo, COUNT(cd.disciplina_id) AS disciplinas
     FROM classes c
     LEFT JOIN classes_disciplinas cd ON cd.classe_id = c.id
     WHERE c.codigo IN ('SEC-08', 'SEC-09', 'SEC-10', 'SEC-11', 'SEC-12')
     GROUP BY c.id, c.codigo"
)->fetchAll(PDO::FETCH_ASSOC);
$classes = [];
foreach ($classRows as $row) {
    if ((int) $row['disciplinas'] < 1) {
        throw new RuntimeException(
            'A classe ' . $row['codigo'] . ' não tem disciplinas associadas.'
        );
    }
    $classes[$row['codigo']] = (int) $row['id'];
}

$periods = [
    2022 => ['SEC-08', 'SEC-09'],
    2023 => ['SEC-09', 'SEC-10'],
    2024 => ['SEC-10', 'SEC-11'],
    2025 => ['SEC-11', 'SEC-12']
];
foreach ($periods as $classCodes) {
    foreach ($classCodes as $classCode) {
        if (!isset($classes[$classCode])) {
            throw new RuntimeException(
                'Falta a classe necessária ao conjunto de teste: ' . $classCode
            );
        }
    }
}

$database->beginTransaction();
try {
    $accountEmail = 'teste.documentos@sgece.local';
    $accountQuery = $database->prepare(
        'SELECT id, escola_id, nivel_acesso_id
         FROM usuarios
         WHERE email = :email
         LIMIT 1'
    );
    $accountQuery->execute(['email' => $accountEmail]);
    $account = $accountQuery->fetch(PDO::FETCH_ASSOC);
    $newPassword = null;

    if ($account) {
        if (
            (int) $account['escola_id'] !== $schoolId
            || (int) $account['nivel_acesso_id'] !== $schoolLevelId
        ) {
            throw new RuntimeException(
                'O e-mail de teste já existe associado a outra conta; não foi alterado.'
            );
        }
        $schoolUserId = (int) $account['id'];
    } else {
        $newPassword = bin2hex(random_bytes(16));
        $createAccount = $database->prepare(
            "INSERT INTO usuarios
                (escola_id, nivel_acesso_id, nome, email, palavra_passe, estado)
             VALUES
                (:escola_id, :nivel_id, 'Utilizador de Teste - Documentos',
                 :email, :password, 'activo')"
        );
        $createAccount->execute([
            'escola_id' => $schoolId,
            'nivel_id' => $schoolLevelId,
            'email' => $accountEmail,
            'password' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
        $schoolUserId = (int) $database->lastInsertId();
    }

    $yearIds = [];
    $findYear = $database->prepare(
        'SELECT id, estado FROM anos_lectivos WHERE ano = :ano LIMIT 1'
    );
    $addYear = $database->prepare(
        "INSERT INTO anos_lectivos (ano, data_inicio, data_fim, estado)
         VALUES (:ano, :inicio, :fim, 'encerrado')"
    );
    foreach (array_keys($periods) as $year) {
        $findYear->execute(['ano' => $year]);
        $existingYear = $findYear->fetch(PDO::FETCH_ASSOC);
        if (!$existingYear) {
            $addYear->execute([
                'ano' => $year,
                'inicio' => $year . '-01-01',
                'fim' => $year . '-12-31'
            ]);
            $yearIds[$year] = (int) $database->lastInsertId();
            continue;
        }

        if ($existingYear['estado'] !== 'encerrado') {
            throw new RuntimeException(
                'O ano ' . $year . ' existe e não está encerrado; não foi alterado.'
            );
        }
        $yearIds[$year] = (int) $existingYear['id'];
    }

    $classSubjects = [];
    $subjectQuery = $database->prepare(
        'SELECT disciplina_id
         FROM classes_disciplinas
         WHERE classe_id = :classe_id
         ORDER BY disciplina_id'
    );
    foreach ($classes as $classCode => $classId) {
        $subjectQuery->execute(['classe_id' => $classId]);
        $classSubjects[$classCode] = array_map(
            'intval',
            $subjectQuery->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    $addClassroom = $database->prepare(
        "INSERT INTO turmas
            (escola_id, classe_id, ano_lectivo_id, identificacao,
             turno, capacidade, estado)
         VALUES
            (:escola_id, :classe_id, :ano_id, :nome, 'Manha', 40, 'activa')
         ON DUPLICATE KEY UPDATE
            turno = VALUES(turno),
            capacidade = VALUES(capacidade),
            estado = 'activa'"
    );
    $findClassroom = $database->prepare(
        'SELECT id FROM turmas
         WHERE escola_id = :escola_id
           AND ano_lectivo_id = :ano_id
           AND classe_id = :classe_id
           AND identificacao = :nome
         LIMIT 1'
    );
    $addStudent = $database->prepare(
        "INSERT IGNORE INTO alunos
            (codigo_central, nome_completo, data_nascimento, naturalidade, sexo,
             bi_cifrado, bi_hash, nome_pai, nome_mae, contacto, endereco, estado)
         VALUES
            (:codigo, :nome, :nascimento, :naturalidade, :sexo,
             :bi_cifrado, :bi_hash,
             'Encarregado Ficticio', 'Encarregada Ficticia', NULL,
             'Endereco ficticio para testes', :estado)"
    );
    $updateStudentNaturalidade = $database->prepare(
        'UPDATE alunos
         SET naturalidade = :naturalidade
         WHERE codigo_central = :codigo
           AND naturalidade IS NULL'
    );
    $findStudent = $database->prepare(
        'SELECT id, bi_hash FROM alunos WHERE codigo_central = :codigo LIMIT 1'
    );
    $addAssociation = $database->prepare(
        'INSERT IGNORE INTO aluno_escolas
            (aluno_id, escola_id, codigo_aluno_escola, data_entrada,
             data_saida, estado)
         VALUES
            (:aluno_id, :escola_id, :codigo, :entrada, :saida, :estado)'
    );
    $findAssociation = $database->prepare(
        'SELECT id, aluno_id, escola_id
         FROM aluno_escolas
         WHERE codigo_aluno_escola = :codigo
         LIMIT 1'
    );
    $addEnrollment = $database->prepare(
        'INSERT IGNORE INTO matriculas
            (aluno_escola_id, escola_id, classe_id, turma_id,
             ano_lectivo_id, numero_processo, data_matricula, estado)
         VALUES
            (:associacao_id, :escola_id, :classe_id, :turma_id,
             :ano_id, :processo, :data, :estado)'
    );
    $findEnrollment = $database->prepare(
        'SELECT id, classe_id, turma_id
         FROM matriculas
         WHERE aluno_escola_id = :associacao_id
           AND ano_lectivo_id = :ano_id
         LIMIT 1'
    );
    $saveGrade = $database->prepare(
        'INSERT INTO notas_trimestrais
            (matricula_id, disciplina_id, trimestre, acs1, acs2,
             avaliacao_provincial, nota, criado_por)
         VALUES
            (:matricula_id, :disciplina_id, :trimestre, :acs1, :acs2,
             :provincial, NULL, :criado_por)
         ON DUPLICATE KEY UPDATE
            acs1 = VALUES(acs1),
            acs2 = VALUES(acs2),
            avaliacao_provincial = VALUES(avaliacao_provincial),
            nota = NULL'
    );
    $scores = [
        1 => [15, 16, 17],
        2 => [10, 11, 12],
        3 => [7, 8, 7]
    ];
    $hashKey = hash_hmac(
        'sha256',
        'sgece:students:bi:hash:v1',
        $applicationKey,
        true
    );
    $encryptionKey = hash_hmac(
        'sha256',
        'sgece:students:bi:encryption:v1',
        $applicationKey,
        true
    );

    foreach ($periods as $year => $classCodes) {
        foreach ($classCodes as $classIndex => $classCode) {
            $classId = $classes[$classCode];
            $classroomName = 'DOC-' . $year . '-' . str_replace('-', '', $classCode);
            $addClassroom->execute([
                'escola_id' => $schoolId,
                'classe_id' => $classId,
                'ano_id' => $yearIds[$year],
                'nome' => $classroomName
            ]);
            $findClassroom->execute([
                'escola_id' => $schoolId,
                'ano_id' => $yearIds[$year],
                'classe_id' => $classId,
                'nome' => $classroomName
            ]);
            $classroomId = $findClassroom->fetchColumn();
            if ($classroomId === false) {
                throw new RuntimeException(
                    'Não foi possível criar a turma fictícia ' . $classroomName . '.'
                );
            }

            foreach ([1, 2, 3] as $studentNumber) {
                $classShort = str_replace('-', '', $classCode);
                $studentCode = 'SGECE-DOC-' . $year . '-'
                    . $classShort . '-' . sprintf('%02d', $studentNumber);
                $identity = 'TESTE-DOC-' . $year . '-'
                    . $classShort . '-' . sprintf('%02d', $studentNumber);
                $identityHash = hash_hmac(
                    'sha256',
                    strtoupper($identity),
                    $hashKey
                );
                $nonce = random_bytes(12);
                $tag = '';
                $encryptedIdentity = openssl_encrypt(
                    $identity,
                    'aes-256-gcm',
                    $encryptionKey,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $tag,
                    '',
                    16
                );
                if ($encryptedIdentity === false) {
                    throw new RuntimeException(
                        'Não foi possível cifrar o identificador fictício.'
                    );
                }

                $isAlumni = $studentNumber === 1;
                $studentState = $isAlumni ? 'inactivo' : 'activo';
                $addStudent->execute([
                    'codigo' => $studentCode,
                    'nome' => 'Aluno Ficticio ' . $year . ' ' . $classShort
                        . ' ' . sprintf('%02d', $studentNumber),
                    'nascimento' => ($year - 15) . '-05-10',
                    'naturalidade' => 'Maputo, Província de Maputo',
                    'sexo' => $studentNumber === 2 ? 'Masculino' : 'Feminino',
                    'bi_cifrado' => $nonce . $tag . $encryptedIdentity,
                    'bi_hash' => $identityHash,
                    'estado' => $studentState
                ]);
                $findStudent->execute(['codigo' => $studentCode]);
                $student = $findStudent->fetch(PDO::FETCH_ASSOC);
                if (
                    !$student
                    || !hash_equals($identityHash, $student['bi_hash'])
                ) {
                    throw new RuntimeException(
                        'Conflito no código fictício do aluno ' . $studentCode . '.'
                    );
                }
                $updateStudentNaturalidade->execute([
                    'naturalidade' => 'Maputo, Província de Maputo',
                    'codigo' => $studentCode
                ]);

                $addAssociation->execute([
                    'aluno_id' => (int) $student['id'],
                    'escola_id' => $schoolId,
                    'codigo' => $studentCode,
                    'entrada' => $year . '-02-01',
                    'saida' => $isAlumni ? $year . '-12-31' : null,
                    'estado' => $studentState
                ]);
                $findAssociation->execute(['codigo' => $studentCode]);
                $association = $findAssociation->fetch(PDO::FETCH_ASSOC);
                if (
                    !$association
                    || (int) $association['aluno_id'] !== (int) $student['id']
                    || (int) $association['escola_id'] !== $schoolId
                ) {
                    throw new RuntimeException(
                        'Conflito na associação fictícia do aluno ' . $studentCode . '.'
                    );
                }

                $addEnrollment->execute([
                    'associacao_id' => (int) $association['id'],
                    'escola_id' => $schoolId,
                    'classe_id' => $classId,
                    'turma_id' => (int) $classroomId,
                    'ano_id' => $yearIds[$year],
                    'processo' => 90000 + (($year - 2022) * 100)
                        + (($classIndex + 1) * 10) + $studentNumber,
                    'data' => $year . '-02-01',
                    'estado' => $isAlumni ? 'cancelada' : 'activa'
                ]);
                $findEnrollment->execute([
                    'associacao_id' => (int) $association['id'],
                    'ano_id' => $yearIds[$year]
                ]);
                $enrollment = $findEnrollment->fetch(PDO::FETCH_ASSOC);
                if (
                    !$enrollment
                    || (int) $enrollment['classe_id'] !== $classId
                    || (int) $enrollment['turma_id'] !== (int) $classroomId
                ) {
                    throw new RuntimeException(
                        'Conflito na matrícula fictícia do aluno ' . $studentCode . '.'
                    );
                }

                foreach ($classSubjects[$classCode] as $subjectId) {
                    foreach ($scores[$studentNumber] as $index => $grade) {
                        $saveGrade->execute([
                            'matricula_id' => (int) $enrollment['id'],
                            'disciplina_id' => $subjectId,
                            'trimestre' => $index + 1,
                            'acs1' => $grade,
                            'acs2' => $grade,
                            'provincial' => $grade,
                            'criado_por' => $schoolUserId
                        ]);
                    }
                }
            }
        }
    }

    $eligibleQuery = $database->prepare(
        "SELECT ano, COUNT(*) AS alunos
         FROM (
             SELECT m.id, al.ano
             FROM matriculas m
             INNER JOIN anos_lectivos al ON al.id = m.ano_lectivo_id
             INNER JOIN aluno_escolas ae ON ae.id = m.aluno_escola_id
             INNER JOIN alunos a ON a.id = ae.aluno_id
             INNER JOIN classes_disciplinas cd ON cd.classe_id = m.classe_id
             INNER JOIN disciplinas d ON d.id = cd.disciplina_id
             LEFT JOIN notas_trimestrais n
                ON n.matricula_id = m.id
               AND n.disciplina_id = d.id
               AND n.trimestre BETWEEN 1 AND 3
             WHERE m.escola_id = :escola_id
               AND a.codigo_central LIKE 'SGECE-DOC-%'
               AND al.estado = 'encerrado'
             GROUP BY m.id, al.ano
             HAVING COUNT(DISTINCT d.id) > 0
                AND COUNT(DISTINCT CASE
                    WHEN n.trimestre BETWEEN 1 AND 3
                     AND n.acs1 IS NOT NULL
                     AND n.acs2 IS NOT NULL
                     AND n.avaliacao_provincial IS NOT NULL
                    THEN CONCAT(d.id, ':', n.trimestre)
                END) = COUNT(DISTINCT d.id) * 3
                AND ROUND(AVG(COALESCE(
                    n.nota,
                    (n.acs1 + n.acs2 + n.avaliacao_provincial) / 3
                )), 2) >= 10
         ) elegiveis
         GROUP BY ano
         ORDER BY ano"
    );
    $eligibleQuery->execute(['escola_id' => $schoolId]);
    $eligibleCounts = [];
    foreach ($eligibleQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $eligibleCounts[(int) $row['ano']] = (int) $row['alunos'];
    }
    $expectedCounts = [2022 => 4, 2023 => 4, 2024 => 4, 2025 => 4];
    if ($eligibleCounts !== $expectedCounts) {
        throw new RuntimeException(
            'A verificação de elegibilidade falhou: '
            . json_encode($eligibleCounts, JSON_UNESCAPED_UNICODE)
        );
    }

    $database->commit();
    echo json_encode([
        'base' => $_ENV['DB_NAME'],
        'escola_id' => $schoolId,
        'anos_encerrados' => array_keys($periods),
        'turmas' => 8,
        'alunos' => 24,
        'elegiveis_por_ano' => $eligibleCounts,
        'conta_escolar' => $accountEmail,
        'palavra_passe_nova' => $newPassword
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
