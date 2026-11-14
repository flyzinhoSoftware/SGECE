<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use Config\Database;

class AdminController extends Controller
{
    private const SCHOOL_PROVINCE = 'Zambézia';
    private const SCHOOL_EMAIL_DOMAIN = 'sgece.gov.mz';

    private const SCHOOL_LEVELS = [
        'Primaria' => 'Ensino Primário',
        'Secundaria' => 'Ensino Secundário'
    ];

    private const SCHOOL_LEVEL_CODES = [
        'Primaria' => 'PRI',
        'Secundaria' => 'SEC'
    ];

    private const STUDENT_SEXES = [
        'Feminino' => 'Feminino',
        'Masculino' => 'Masculino'
    ];

    private const ZAMBEZIA_DISTRICTS = [
        'Alto Molócuè',
        'Chinde',
        'Derre',
        'Gilé',
        'Gurúè',
        'Ile',
        'Inhassunge',
        'Luabo',
        'Lugela',
        'Maganja da Costa',
        'Milange',
        'Mocuba',
        'Mocubela',
        'Molumbo',
        'Mopeia',
        'Morrumbala',
        'Mulevala',
        'Namacurra',
        'Namarroi',
        'Nicoadala',
        'Pebane',
        'Quelimane'
    ];

    public function login(): void
    {
        $this->startSession();
        $this->preventCaching();

        if ($this->authenticatedAdministrator() !== null) {
            $this->redirect('/admin/dashboard');
        }

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $erro = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $submittedToken = $_POST['csrf_token'] ?? null;

            if (
                !is_string($submittedToken) ||
                !hash_equals($_SESSION['csrf_token'], $submittedToken)
            ) {
                $erro = 'A sessão expirou. Atualize a página e tente novamente.';
            } else {
                $email = $_POST['email'] ?? null;
                $password = $_POST['password'] ?? null;

                if (
                    !is_string($email) ||
                    !is_string($password) ||
                    strlen($email = trim($email)) > 150 ||
                    filter_var($email, FILTER_VALIDATE_EMAIL) === false
                ) {
                    $erro = 'E-mail ou palavra-passe inválidos.';
                } else {
                    $statement = Database::connect()->prepare(
                        "SELECT u.id, u.nome, u.email, u.palavra_passe, na.nome AS nivel
                         FROM usuarios u
                         INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
                         WHERE u.email = :email
                           AND u.estado = 'activo'
                           AND na.nome IN ('Root', 'Admin')
                         LIMIT 1"
                    );
                    $statement->execute(['email' => $email]);
                    $user = $statement->fetch();

                    if ($user && password_verify($password, $user['palavra_passe'])) {
                        $updateLastAccess = Database::connect()->prepare(
                            'UPDATE usuarios SET ultimo_acesso = CURRENT_TIMESTAMP WHERE id = :id'
                        );
                        $updateLastAccess->execute(['id' => $user['id']]);

                        session_regenerate_id(true);
                        $_SESSION['auth'] = [
                            'user_id' => $user['id'],
                            'nome' => $user['nome'],
                            'nivel' => $user['nivel']
                        ];
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        $this->redirect('/admin/dashboard');
                    }

                    $erro = 'E-mail ou palavra-passe inválidos.';
                }
            }
        }

        $this->view('admin/login', [
            'erro' => $erro,
            'csrfToken' => $_SESSION['csrf_token']
        ]);
    }

    public function index(): void
    {
        $user = $this->requireAdministrator();
        $totalSchools = (int) Database::connect()
            ->query('SELECT COUNT(*) FROM escolas')
            ->fetchColumn();

        $baseAdminPath = $this->basePath() . '/admin';
        $schoolsUrl = $baseAdminPath . '/escolas';
        $moduleUrls = [
            'students' => $baseAdminPath . '/alunos',
            'academic' => $baseAdminPath . '/gestao-academica',
            'certificates' => $baseAdminPath . '/certificados',
            'users' => $baseAdminPath . '/utilizadores',
            'audit' => $baseAdminPath . '/auditoria'
        ];

        ob_start();
        require dirname(__DIR__) . '/Views/admin/dashboard.php';
        $content = ob_get_clean();

        $this->renderAdminLayout(
            $user,
            'Painel de Controlo',
            'dashboard',
            $content
        );
    }

    public function schools(): void
    {
        $user = $this->requireAdministrator();
        $this->renderSchools($user);
    }

    public function students(): void
    {
        $user = $this->requireAdministrator();
        $this->renderStudents($user);
    }

    public function staticPage(string $slug): void
    {
        $user = $this->requireAdministrator();
        $pages = [
            'enrollment' => [
                'title' => 'Matrículas',
                'icon' => 'bi-calendar3',
                'description' => 'Área de acompanhamento das matrículas e processos escolares.',
                'items' => ['Processos de matrícula', 'Estado dos pedidos', 'Histórico por ano lectivo']
            ],
            'academic' => [
                'title' => 'Gestão académica',
                'icon' => 'bi-journal-bookmark',
                'description' => 'Área de organização dos anos lectivos, classes e turmas.',
                'items' => ['Anos lectivos', 'Classes e disciplinas', 'Turmas']
            ],
            'certificates' => [
                'title' => 'Certificados',
                'icon' => 'bi-file-earmark-text',
                'description' => 'Área de preparação e emissão de certificados escolares.',
                'items' => ['Pedidos de certificado', 'Emissão de documentos', 'Histórico de certificados']
            ],
            'declarations' => [
                'title' => 'Declarações',
                'icon' => 'bi-file-earmark-check',
                'description' => 'Área de preparação e emissão de declarações escolares.',
                'items' => ['Pedidos de declaração', 'Emissão de documentos', 'Histórico de declarações']
            ],
            'validations' => [
                'title' => 'Validações',
                'icon' => 'bi-patch-check',
                'description' => 'Área de verificação da autenticidade dos documentos emitidos.',
                'items' => ['Pesquisa por código', 'Estado de validação', 'Registo de verificações']
            ],
            'users' => [
                'title' => 'Utilizadores',
                'icon' => 'bi-person-gear',
                'description' => 'Área de gestão das contas e respectivos níveis de acesso.',
                'items' => ['Contas de utilizador', 'Associação a escolas', 'Níveis de acesso']
            ],
            'audit' => [
                'title' => 'Auditoria',
                'icon' => 'bi-shield-check',
                'description' => 'Área de consulta do histórico de actividades administrativas.',
                'items' => ['Actividades recentes', 'Utilizadores e acções', 'Datas e eventos']
            ],
            'settings' => [
                'title' => 'Configurações',
                'icon' => 'bi-gear',
                'description' => 'Área de configuração geral da plataforma SGECE.',
                'items' => ['Dados institucionais', 'Preferências da plataforma', 'Parâmetros do sistema']
            ]
        ];

        if (!isset($pages[$slug])) {
            http_response_code(404);
            echo 'Página administrativa não encontrada.';
            return;
        }

        $page = $pages[$slug];
        ob_start();
        $this->view('admin/static-page', ['page' => $page]);
        $content = ob_get_clean();

        $this->renderAdminLayout($user, $page['title'], $slug, $content);
    }

    public function saveSchool(): void
    {
        $user = $this->requireAdministrator();

        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $limits = [
            'codigo' => 120,
            'nome' => 180,
            'tipo' => 30,
            'provincia' => 100,
            'distrito' => 100,
            'localidade' => 120,
            'endereco' => 255,
            'telefone' => 30
        ];
        $idValue = $this->postedString('id');
        $values = [
            'id' => $idValue ?? '',
            'codigo' => '',
            'email' => ''
        ];
        $errors = [];
        if ($idValue === null) {
            $errors['id'] = 'Identificador de escola inválido.';
        }

        foreach ($limits as $field => $limit) {
            $value = $this->postedString($field);
            if ($value === null) {
                $errors[$field] = 'Este campo contém um valor inválido.';
                $values[$field] = '';
                continue;
            }

            $values[$field] = $value;
            $length = preg_match_all('/./us', $value);

            if ($length === false) {
                $errors[$field] = 'Use texto válido em UTF-8.';
            } elseif ($length > $limit) {
                $errors[$field] = "O limite é de {$limit} caracteres.";
            }
        }

        $id = 0;
        if ($values['id'] !== '') {
            $validatedId = filter_var(
                $values['id'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($validatedId === false) {
                $errors['id'] = 'Identificador de escola inválido.';
            } else {
                $id = $validatedId;
            }
        }

        foreach (
            ['nome', 'tipo', 'provincia', 'distrito', 'endereco', 'telefone']
            as $required
        ) {
            if ($values[$required] === '') {
                $errors[$required] = 'Este campo é obrigatório.';
            }
        }

        $database = Database::connect();
        $existingSchool = null;
        if ($id > 0) {
            $exists = $database->prepare(
                'SELECT codigo, nome, provincia, distrito, tipo
                 FROM escolas WHERE id = :id'
            );
            $exists->execute(['id' => $id]);
            $existingSchool = $exists->fetch() ?: null;
            if ($existingSchool === null) {
                $errors['form'] = 'A escola que tentou editar já não existe.';
            } else {
                $values['codigo'] = $existingSchool['codigo'];
            }
        }

        $isLegacyLocation = $existingSchool !== null
            && $values['provincia'] === $existingSchool['provincia']
            && $values['distrito'] === $existingSchool['distrito'];
        if (
            $values['provincia'] !== self::SCHOOL_PROVINCE &&
            !$isLegacyLocation
        ) {
            $errors['provincia'] = 'As novas escolas devem pertencer à Zambézia.';
        }
        if (
            !in_array($values['distrito'], self::ZAMBEZIA_DISTRICTS, true) &&
            !$isLegacyLocation
        ) {
            $errors['distrito'] = 'Seleccione um distrito da Zambézia.';
        }

        $isLegacyLevel = $existingSchool !== null
            && $values['tipo'] === $existingSchool['tipo'];
        if (!isset(self::SCHOOL_LEVELS[$values['tipo']]) && !$isLegacyLevel) {
            $errors['tipo'] = 'Seleccione um nível de ensino válido.';
        }

        if (
            $values['nome'] !== '' &&
            $values['distrito'] !== '' &&
            !isset($errors['nome']) &&
            !isset($errors['distrito'])
        ) {
            $values['email'] = $this->availableSchoolEmail(
                $database,
                $values['nome'],
                $values['distrito'],
                $id
            );
        }
        if ($values['email'] === '') {
            $errors['email'] = 'Não foi possível gerar o e-mail institucional.';
        } elseif (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'O e-mail institucional gerado não é válido.';
        }

        foreach (['email', 'telefone', 'endereco'] as $contactField) {
            $value = $values[$contactField];
            if (
                $value !== '' &&
                $this->schoolContactExists($database, $contactField, $value, $id)
            ) {
                $errors[$contactField] = $this->schoolContactDuplicateMessage($contactField);
            }
        }

        $codePrefix = '';
        if (
            $id === 0 &&
            !isset($errors['nome']) &&
            !isset($errors['tipo'])
        ) {
            $codePrefix = $this->schoolCodePrefix($values['nome'], $values['tipo']);
            if ($codePrefix === '') {
                $errors['nome'] = 'O nome deve conter pelo menos uma letra.';
            }
        }

        if ($errors !== []) {
            $this->renderSchools($user, $values, $errors);
            return;
        }

        if ($id === 0) {
            $values['codigo'] = $this->schoolCodeWithSuffix(
                $codePrefix,
                $values['codigo']
            );
        }

        $parameters = [
            'nome' => $values['nome'],
            'tipo' => $values['tipo'],
            'provincia' => $values['provincia'],
            'distrito' => $values['distrito'],
            'localidade' => $values['localidade'] === '' ? null : $values['localidade'],
            'endereco' => $values['endereco'] === '' ? null : $values['endereco'],
            'telefone' => $values['telefone'] === '' ? null : $values['telefone'],
            'email' => $values['email']
        ];

        if ($id > 0) {
            $parameters['id'] = $id;
            $statement = $database->prepare(
                'UPDATE escolas
                 SET nome = :nome,
                     tipo = :tipo,
                     provincia = :provincia,
                     distrito = :distrito,
                     localidade = :localidade,
                     endereco = :endereco,
                     telefone = :telefone,
                     email = :email
                 WHERE id = :id'
            );
            try {
                $statement->execute($parameters);
            } catch (\PDOException $exception) {
                $duplicateField = $this->schoolContactDuplicateField($exception);
                if ($duplicateField === null) {
                    throw $exception;
                }

                $this->renderSchools($user, $values, [
                    $duplicateField => $this->schoolContactDuplicateMessage($duplicateField)
                ]);
                return;
            }
            $_SESSION['_schools_flash'] = [
                'type' => 'success',
                'message' => 'Dados da escola actualizados com sucesso.'
            ];
        } else {
            $generatedCode = null;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $database->beginTransaction();
                try {
                    $attemptCode = $attempt === 0
                        ? $values['codigo']
                        : $this->schoolCodeWithSuffix($codePrefix, '');
                    $attemptEmail = $this->availableSchoolEmail(
                        $database,
                        $values['nome'],
                        $values['distrito'],
                        0
                    );
                    $temporaryCode = 'TMP-' . strtoupper(bin2hex(random_bytes(12)));
                    $insertParameters = $parameters;
                    $insertParameters['codigo'] = $temporaryCode;
                    $insertParameters['email'] = $attemptEmail;
                    $insert = $database->prepare(
                        "INSERT INTO escolas
                            (codigo, nome, tipo, provincia, distrito, localidade,
                             endereco, telefone, email, estado)
                         VALUES
                            (:codigo, :nome, :tipo, :provincia, :distrito, :localidade,
                             :endereco, :telefone, :email, 'activa')"
                    );
                    $insert->execute($insertParameters);

                    $schoolId = $database->lastInsertId();
                    $generatedCode = $attemptCode;
                    $generatedEmail = $attemptEmail;
                    $updateCode = $database->prepare(
                        'UPDATE escolas
                         SET codigo = :codigo, email = :email
                         WHERE id = :id'
                    );
                    $updateCode->execute([
                        'codigo' => $generatedCode,
                        'email' => $generatedEmail,
                        'id' => $schoolId
                    ]);

                    $database->commit();
                    break;
                } catch (\PDOException $exception) {
                    if ($database->inTransaction()) {
                        $database->rollBack();
                    }
                    $duplicateField = $this->schoolContactDuplicateField($exception);
                    if ($duplicateField === 'email') {
                        $generatedCode = null;
                        continue;
                    }
                    if ($duplicateField !== null) {
                        $errors[$duplicateField] = $this->schoolContactDuplicateMessage(
                            $duplicateField
                        );
                        $generatedCode = null;
                        break;
                    }
                    if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                        throw $exception;
                    }
                    $generatedCode = null;
                } catch (\Throwable $exception) {
                    if ($database->inTransaction()) {
                        $database->rollBack();
                    }
                    throw $exception;
                }
            }

            if ($errors !== []) {
                $this->renderSchools($user, $values, $errors);
                return;
            }

            if ($generatedCode === null) {
                $this->renderSchools($user, $values, [
                    'form' => 'Não foi possível gerar códigos e contactos únicos. Tente novamente.'
                ]);
                return;
            }

            $_SESSION['_schools_flash'] = [
                'type' => 'success',
                'message' => 'Escola registada com o código ' . $generatedCode
                    . ' e o e-mail ' . $generatedEmail . '.'
            ];
        }

        $this->redirect('/admin/escolas');
    }

    public function saveStudent(): void
    {
        $user = $this->requireAdministrator();

        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $limits = [
            'nome_completo' => 180,
            'data_nascimento' => 10,
            'sexo' => 20,
            'bi' => 30,
            'distrito' => 100,
            'escola_id' => 20,
            'nome_pai' => 150,
            'nome_mae' => 150,
            'contacto' => 30,
            'endereco' => 255
        ];
        $idValue = $this->postedString('id');
        $values = [
            'id' => $idValue ?? '',
            'codigo_central' => '',
            'nome_completo' => '',
            'data_nascimento' => '',
            'sexo' => '',
            'bi' => '',
            'distrito' => '',
            'escola_id' => '',
            'nome_pai' => '',
            'nome_mae' => '',
            'contacto' => '',
            'endereco' => ''
        ];
        $errors = [];
        if ($idValue === null) {
            $errors['id'] = 'Identificador de aluno inválido.';
        }

        foreach ($limits as $field => $limit) {
            $value = $this->postedString($field);
            if ($value === null) {
                $errors[$field] = 'Este campo contém um valor inválido.';
                continue;
            }

            $values[$field] = $value;
            $length = preg_match_all('/./us', $value);
            if ($length === false) {
                $errors[$field] = 'Use texto válido em UTF-8.';
            } elseif ($length > $limit) {
                $errors[$field] = "O limite é de {$limit} caracteres.";
            }
        }

        $id = 0;
        if ($values['id'] !== '') {
            $validatedId = filter_var(
                $values['id'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($validatedId === false) {
                $errors['id'] = 'Identificador de aluno inválido.';
            } else {
                $id = $validatedId;
            }
        }

        foreach (
            ['nome_completo', 'data_nascimento', 'sexo', 'distrito', 'escola_id']
            as $required
        ) {
            if ($values[$required] === '') {
                $errors[$required] = 'Este campo é obrigatório.';
            }
        }
        if ($id === 0 && $values['bi'] === '') {
            $errors['bi'] = 'O número do BI é obrigatório para registar o aluno.';
        }

        if ($values['data_nascimento'] !== '' && !isset($errors['data_nascimento'])) {
            $birthDate = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $values['data_nascimento']
            );
            $dateErrors = \DateTimeImmutable::getLastErrors();
            if (
                $birthDate === false ||
                ($dateErrors !== false && (
                    $dateErrors['warning_count'] > 0 ||
                    $dateErrors['error_count'] > 0
                )) ||
                $birthDate->format('Y-m-d') !== $values['data_nascimento'] ||
                $birthDate > new \DateTimeImmutable('today')
            ) {
                $errors['data_nascimento'] = 'Indique uma data de nascimento válida.';
            }
        }
        if (
            $values['sexo'] !== '' &&
            !isset(self::STUDENT_SEXES[$values['sexo']])
        ) {
            $errors['sexo'] = 'Seleccione uma opção válida.';
        }

        $database = Database::connect();
        $existingStudent = null;
        $currentAssociation = null;
        if ($id > 0) {
            $existing = $database->prepare(
                'SELECT id, codigo_central
                 FROM alunos WHERE id = :id'
            );
            $existing->execute(['id' => $id]);
            $existingStudent = $existing->fetch() ?: null;
            if ($existingStudent === null) {
                $errors['form'] = 'O aluno que tentou editar já não existe.';
            } else {
                $values['codigo_central'] = $existingStudent['codigo_central'];
                $currentAssociation = $this->activeStudentSchool($database, $id);
                if ($currentAssociation !== null) {
                    if (
                        $values['escola_id'] !== (string) $currentAssociation['escola_id'] ||
                        $values['distrito'] !== $currentAssociation['distrito']
                    ) {
                        $errors['escola_id'] =
                            'A escola associada não pode ser alterada nesta página.';
                    }
                    $values['escola_id'] = (string) $currentAssociation['escola_id'];
                    $values['distrito'] = $currentAssociation['distrito'];
                }
            }
        }

        $selectedSchoolId = filter_var(
            $values['escola_id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($selectedSchoolId === false) {
            $errors['escola_id'] = 'Seleccione uma escola válida.';
        } elseif (
            !isset($errors['escola_id']) ||
            $currentAssociation === null
        ) {
            $selectedSchool = $this->findSchool($database, $selectedSchoolId);
            if (
                $selectedSchool === null ||
                $selectedSchool['distrito'] !== $values['distrito'] ||
                (
                    $selectedSchool['estado'] !== 'activa' &&
                    (
                        $currentAssociation === null ||
                        (int) $currentAssociation['escola_id'] !== $selectedSchoolId
                    )
                )
            ) {
                $errors['escola_id'] =
                    'Seleccione uma escola activa dentro do distrito escolhido.';
            }
        }

        $biHash = null;
        if ($errors === [] && $values['bi'] !== '' && !isset($errors['bi'])) {
            $biHash = $this->studentIdentityHash($values['bi']);
            if ($this->studentIdentityExists($database, $biHash, $id)) {
                $errors['bi'] = 'Já existe um aluno registado com este BI.';
            }
        }

        if ($errors !== []) {
            $this->renderStudents($user, $values, $errors);
            return;
        }

        $parameters = [
            'nome_completo' => $values['nome_completo'],
            'data_nascimento' => $values['data_nascimento'],
            'sexo' => $values['sexo'],
            'nome_pai' => $values['nome_pai'] === '' ? null : $values['nome_pai'],
            'nome_mae' => $values['nome_mae'] === '' ? null : $values['nome_mae'],
            'contacto' => $values['contacto'] === '' ? null : $values['contacto'],
            'endereco' => $values['endereco'] === '' ? null : $values['endereco']
        ];

        if ($biHash !== null) {
            $parameters['bi_cifrado'] = $this->encryptStudentIdentity($values['bi']);
            $parameters['bi_hash'] = $biHash;
        }

        if ($id > 0) {
            $parameters['id'] = $id;
            $updateFields = [
                'nome_completo = :nome_completo',
                'data_nascimento = :data_nascimento',
                'sexo = :sexo',
                'nome_pai = :nome_pai',
                'nome_mae = :nome_mae',
                'contacto = :contacto',
                'endereco = :endereco'
            ];
            if ($biHash !== null) {
                $updateFields[] = 'bi_cifrado = :bi_cifrado';
                $updateFields[] = 'bi_hash = :bi_hash';
            }
            $statement = $database->prepare(
                'UPDATE alunos SET ' . implode(', ', $updateFields) . ' WHERE id = :id'
            );
            $database->beginTransaction();
            try {
                $lockStudent = $database->prepare(
                    'SELECT id FROM alunos WHERE id = :id FOR UPDATE'
                );
                $lockStudent->execute(['id' => $id]);
                if ($lockStudent->fetchColumn() === false) {
                    $database->rollBack();
                    $this->renderStudents($user, $values, [
                        'form' => 'O aluno que tentou editar já não existe.'
                    ]);
                    return;
                }

                $lockedAssociation = $this->activeStudentSchool($database, $id, true);
                if ($lockedAssociation !== null) {
                    if ((int) $lockedAssociation['escola_id'] !== $selectedSchoolId) {
                        $database->rollBack();
                        $this->renderStudents($user, $values, [
                            'escola_id' => 'A escola associada não pode ser alterada nesta página.'
                        ]);
                        return;
                    }
                } else {
                    $school = $this->findSchool($database, $selectedSchoolId, true);
                    if (
                        $school === null ||
                        $school['estado'] !== 'activa' ||
                        $school['distrito'] !== $values['distrito']
                    ) {
                        $database->rollBack();
                        $this->renderStudents($user, $values, [
                            'escola_id' => 'Seleccione uma escola activa dentro do distrito escolhido.'
                        ]);
                        return;
                    }
                    $this->createStudentSchoolAssociation(
                        $database,
                        $id,
                        $selectedSchoolId
                    );
                }

                $statement->execute($parameters);
                $database->commit();
            } catch (\PDOException $exception) {
                if ($database->inTransaction()) {
                    $database->rollBack();
                }
                if (
                    $biHash === null ||
                    (int) ($exception->errorInfo[1] ?? 0) !== 1062
                ) {
                    throw $exception;
                }
                $this->renderStudents($user, $values, [
                    'bi' => 'Já existe um aluno registado com este BI.'
                ]);
                return;
            } catch (\Throwable $exception) {
                if ($database->inTransaction()) {
                    $database->rollBack();
                }
                throw $exception;
            }
            $_SESSION['_students_flash'] = [
                'type' => 'success',
                'message' => 'Dados do aluno actualizados com sucesso.'
            ];
        } else {
            $saved = false;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $values['codigo_central'] = $this->generateStudentCode();
                $insertParameters = $parameters;
                $insertParameters['codigo_central'] = $values['codigo_central'];
                $database->beginTransaction();
                try {
                    $school = $this->findSchool($database, $selectedSchoolId, true);
                    if (
                        $school === null ||
                        $school['estado'] !== 'activa' ||
                        $school['distrito'] !== $values['distrito']
                    ) {
                        $database->rollBack();
                        $this->renderStudents($user, $values, [
                            'escola_id' => 'Seleccione uma escola activa dentro do distrito escolhido.'
                        ]);
                        return;
                    }

                    $insert = $database->prepare(
                        'INSERT INTO alunos
                            (codigo_central, nome_completo, data_nascimento, sexo,
                             bi_cifrado, bi_hash, nome_pai, nome_mae, contacto,
                             endereco, estado)
                         VALUES
                            (:codigo_central, :nome_completo, :data_nascimento, :sexo,
                             :bi_cifrado, :bi_hash, :nome_pai, :nome_mae, :contacto,
                             :endereco, \'activo\')'
                    );
                    $insert->execute($insertParameters);
                    $studentId = (int) $database->lastInsertId();
                    $this->createStudentSchoolAssociation(
                        $database,
                        $studentId,
                        $selectedSchoolId
                    );
                    $database->commit();
                    $saved = true;
                    break;
                } catch (\PDOException $exception) {
                    if ($database->inTransaction()) {
                        $database->rollBack();
                    }
                    if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                        throw $exception;
                    }
                    if ($biHash !== null && $this->studentIdentityExists($database, $biHash, 0)) {
                        $this->renderStudents($user, $values, [
                            'bi' => 'Já existe um aluno registado com este BI.'
                        ]);
                        return;
                    }
                } catch (\Throwable $exception) {
                    if ($database->inTransaction()) {
                        $database->rollBack();
                    }
                    throw $exception;
                }
            }

            if (!$saved) {
                $this->renderStudents($user, $values, [
                    'form' => 'Não foi possível gerar um código único para o aluno. Tente novamente.'
                ]);
                return;
            }

            $_SESSION['_students_flash'] = [
                'type' => 'success',
                'message' => 'Aluno registado com o código ' . $values['codigo_central'] . '.'
            ];
        }

        $this->redirect('/admin/alunos');
    }

    public function updateStudentStatus(): void
    {
        $this->requireAdministrator();

        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $idValue = $this->postedString('id') ?? '';
        $id = filter_var(
            $idValue,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $status = $this->postedString('estado');
        if ($id === false || !in_array($status, ['activo', 'inactivo'], true)) {
            $_SESSION['_students_flash'] = [
                'type' => 'danger',
                'message' => 'Pedido de alteração de estado inválido.'
            ];
            $this->redirect('/admin/alunos');
        }

        $database = Database::connect();
        $exists = $database->prepare('SELECT id FROM alunos WHERE id = :id');
        $exists->execute(['id' => $id]);
        if ($exists->fetchColumn() === false) {
            $_SESSION['_students_flash'] = [
                'type' => 'danger',
                'message' => 'O aluno seleccionado não foi encontrado.'
            ];
            $this->redirect('/admin/alunos');
        }

        $statement = $database->prepare(
            'UPDATE alunos SET estado = :estado WHERE id = :id'
        );
        $statement->execute(['estado' => $status, 'id' => $id]);
        $_SESSION['_students_flash'] = [
            'type' => 'success',
            'message' => $status === 'activo'
                ? 'Aluno activado com sucesso.'
                : 'Aluno desactivado com sucesso.'
        ];
        $this->redirect('/admin/alunos');
    }

    public function updateSchoolStatus(): void
    {
        $this->requireAdministrator();

        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $idValue = $this->postedString('id') ?? '';
        $id = filter_var(
            $idValue,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $status = $this->postedString('estado');

        if (
            $id === false ||
            !in_array($status, ['activa', 'inactiva'], true)
        ) {
            $_SESSION['_schools_flash'] = [
                'type' => 'danger',
                'message' => 'Pedido de alteração de estado inválido.'
            ];
            $this->redirect('/admin/escolas');
        }

        $database = Database::connect();
        $exists = $database->prepare('SELECT id FROM escolas WHERE id = :id');
        $exists->execute(['id' => $id]);

        if ($exists->fetchColumn() === false) {
            $_SESSION['_schools_flash'] = [
                'type' => 'danger',
                'message' => 'A escola seleccionada não foi encontrada.'
            ];
            $this->redirect('/admin/escolas');
        }

        $statement = $database->prepare(
            'UPDATE escolas SET estado = :estado WHERE id = :id'
        );
        $statement->execute(['estado' => $status, 'id' => $id]);

        $_SESSION['_schools_flash'] = [
            'type' => 'success',
            'message' => $status === 'activa'
                ? 'Escola activada com sucesso.'
                : 'Escola desactivada com sucesso.'
        ];
        $this->redirect('/admin/escolas');
    }

    public function logout(): void
    {
        $this->startSession();
        $this->preventCaching();

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            echo 'Método não permitido.';
            return;
        }

        $submittedToken = $_POST['csrf_token'] ?? null;

        if (
            !is_string($submittedToken) ||
            !isset($_SESSION['csrf_token']) ||
            !is_string($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $submittedToken)
        ) {
            http_response_code(403);
            echo 'A sessão expirou. Atualize a página e tente novamente.';
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Lax'
            ]);
        }

        session_destroy();
        $this->redirect('/admin');
    }

    private function authenticatedAdministrator(): ?array
    {
        $auth = $_SESSION['auth'] ?? null;

        if (!is_array($auth) || !isset($auth['user_id'])) {
            return null;
        }

        $userId = filter_var(
            $auth['user_id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($userId === false) {
            $this->invalidateAuthentication();
            return null;
        }

        $statement = Database::connect()->prepare(
            "SELECT u.id, u.nome, u.email, na.nome AS nivel
             FROM usuarios u
             INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
             WHERE u.id = :id
               AND u.estado = 'activo'
               AND na.nome IN ('Root', 'Admin')
             LIMIT 1"
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        if (!$user) {
            $this->invalidateAuthentication();
            return null;
        }

        $_SESSION['auth'] = [
            'user_id' => $user['id'],
            'nome' => $user['nome'],
            'nivel' => $user['nivel']
        ];

        return $user;
    }

    private function invalidateAuthentication(): void
    {
        unset($_SESSION['auth']);
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    private function requireAdministrator(): array
    {
        $this->startSession();
        $this->preventCaching();
        $user = $this->authenticatedAdministrator();

        if ($user === null) {
            $this->redirect('/admin');
        }

        return $user;
    }

    private function hasValidCsrfToken(): bool
    {
        $submittedToken = $_POST['csrf_token'] ?? null;
        $sessionToken = $_SESSION['csrf_token'] ?? null;

        if (
            !is_string($submittedToken) ||
            !is_string($sessionToken) ||
            !hash_equals($sessionToken, $submittedToken)
        ) {
            http_response_code(403);
            echo 'A sessão expirou. Atualize a página e tente novamente.';
            return false;
        }

        return true;
    }

    private function postedString(string $field): ?string
    {
        $value = $_POST[$field] ?? '';

        return is_string($value) ? trim($value) : null;
    }

    private function schoolEmail(string $schoolName, string $district): string
    {
        $acronym = $this->schoolAcronym($schoolName);
        $districtSlug = $this->schoolSlug($district);

        return strtolower($acronym . '-' . $districtSlug)
            . '@' . self::SCHOOL_EMAIL_DOMAIN;
    }

    private function availableSchoolEmail(
        \PDO $database,
        string $schoolName,
        string $district,
        int $excludeId
    ): string {
        $baseEmail = $this->schoolEmail($schoolName, $district);
        $atPosition = strrpos($baseEmail, '@');

        for ($suffix = 1; ; $suffix++) {
            $email = $suffix === 1
                ? $baseEmail
                : substr($baseEmail, 0, $atPosition)
                    . '-' . $suffix . substr($baseEmail, $atPosition);
            if (strlen($email) > 150) {
                throw new \RuntimeException('O e-mail institucional excede o limite permitido.');
            }
            if (!$this->schoolContactExists($database, 'email', $email, $excludeId)) {
                return $email;
            }
        }
    }

    private function schoolCodePrefix(string $schoolName, string $level): string
    {
        $initials = $this->schoolAcronym($schoolName);
        $levelCode = self::SCHOOL_LEVEL_CODES[$level] ?? '';
        if ($initials === '' || $levelCode === '') {
            return '';
        }

        return $initials . '-' . $levelCode;
    }

    private function schoolCodeWithSuffix(string $prefix, string $candidate): string
    {
        $suffix = preg_match('/-([A-Fa-f0-9]{16})$/D', $candidate, $match) === 1
            ? strtoupper($match[1])
            : strtoupper(bin2hex(random_bytes(8)));

        return $prefix . '-' . $suffix;
    }

    private function schoolAcronym(string $schoolName): string
    {
        $normalizedName = $this->asciiText($schoolName);
        $words = preg_split('/\s+/', trim($normalizedName), -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';
        foreach ($words ?: [] as $word) {
            if (preg_match('/[A-Za-z]/', $word, $match) === 1) {
                $initials .= strtoupper($match[0]);
            }
        }

        return $initials;
    }

    private function schoolSlug(string $value): string
    {
        $asciiValue = str_replace(
            ["'", '`', '^', '~', '"'],
            '',
            $this->asciiText($value)
        );
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $asciiValue);
        if ($slug === null) {
            throw new \RuntimeException('Não foi possível normalizar o distrito da escola.');
        }

        return strtolower(trim($slug, '-'));
    }

    private function schoolContactExists(
        \PDO $database,
        string $field,
        string $value,
        int $excludeId
    ): bool {
        $columns = [
            'email' => 'email',
            'telefone' => 'telefone',
            'endereco' => 'endereco'
        ];
        if (!isset($columns[$field])) {
            throw new \InvalidArgumentException('Campo de contacto inválido.');
        }

        $column = $columns[$field];
        $sql = 'SELECT id FROM escolas
                WHERE LOWER(TRIM(' . $column . ')) = LOWER(TRIM(:value))';
        $parameters = ['value' => $value];
        if ($excludeId > 0) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $excludeId;
        }

        $statement = $database->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    private function schoolContactDuplicateField(\PDOException $exception): ?string
    {
        if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
            return null;
        }

        $detail = (string) ($exception->errorInfo[2] ?? '');
        foreach ([
            'uq_escolas_email' => 'email',
            'uq_escolas_telefone' => 'telefone',
            'uq_escolas_endereco' => 'endereco'
        ] as $index => $field) {
            if (strpos($detail, $index) !== false) {
                return $field;
            }
        }

        return null;
    }

    private function schoolContactDuplicateMessage(string $field): string
    {
        $labels = [
            'email' => 'Já existe uma escola com este e-mail.',
            'telefone' => 'Já existe uma escola com este telefone.',
            'endereco' => 'Já existe uma escola com este endereço.'
        ];

        if (!isset($labels[$field])) {
            throw new \InvalidArgumentException('Campo de contacto inválido.');
        }

        return $labels[$field];
    }

    private function generateStudentCode(): string
    {
        return 'SGECE-ALU-' . strtoupper(bin2hex(random_bytes(12)));
    }

    private function studentIdentityHash(string $identityNumber): string
    {
        $normalized = preg_replace('/\s+/u', '', trim($identityNumber));
        if ($normalized === null || $normalized === '') {
            throw new \InvalidArgumentException('O BI do aluno é inválido.');
        }

        return hash_hmac(
            'sha256',
            strtoupper($normalized),
            $this->studentIdentityKey('hash')
        );
    }

    private function encryptStudentIdentity(string $identityNumber): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt(
            trim($identityNumber),
            'aes-256-gcm',
            $this->studentIdentityKey('encryption'),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );
        if ($encrypted === false) {
            throw new \RuntimeException('Não foi possível cifrar o BI do aluno.');
        }

        return $nonce . $tag . $encrypted;
    }

    private function studentIdentityKey(string $purpose): string
    {
        $applicationKey = $_ENV['APP_KEY'] ?? null;
        if (!is_string($applicationKey) || strlen($applicationKey) < 32) {
            throw new \RuntimeException(
                'Configure uma APP_KEY segura para gerir os dados de identificação dos alunos.'
            );
        }

        return hash_hmac(
            'sha256',
            'sgece:students:bi:' . $purpose . ':v1',
            $applicationKey,
            true
        );
    }

    private function studentIdentityExists(
        \PDO $database,
        string $identityHash,
        int $excludeId
    ): bool {
        $sql = 'SELECT id FROM alunos WHERE bi_hash = :hash';
        $parameters = ['hash' => $identityHash];
        if ($excludeId > 0) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $excludeId;
        }

        $statement = $database->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    private function findSchool(
        \PDO $database,
        int $schoolId,
        bool $forUpdate = false
    ): ?array {
        $statement = $database->prepare(
            'SELECT id, distrito, estado FROM escolas WHERE id = :id'
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute(['id' => $schoolId]);
        $school = $statement->fetch();

        return $school ?: null;
    }

    private function activeStudentSchool(
        \PDO $database,
        int $studentId,
        bool $forUpdate = false
    ): ?array {
        $statement = $database->prepare(
            "SELECT ae.id AS associacao_id, e.id AS escola_id, e.nome,
                    e.distrito, e.estado AS estado_escola
             FROM aluno_escolas ae
             INNER JOIN escolas e ON e.id = ae.escola_id
             WHERE ae.aluno_id = :aluno_id
               AND ae.estado = 'activo'
             ORDER BY ae.data_entrada DESC, ae.id DESC
             LIMIT 1"
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute(['aluno_id' => $studentId]);
        $association = $statement->fetch();

        return $association ?: null;
    }

    private function createStudentSchoolAssociation(
        \PDO $database,
        int $studentId,
        int $schoolId
    ): void {
        $statement = $database->prepare(
            "INSERT INTO aluno_escolas
                (aluno_id, escola_id, codigo_aluno_escola,
                 data_entrada, estado)
             VALUES
                (:aluno_id, :escola_id, :codigo, CURRENT_DATE, 'activo')"
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $statement->execute([
                    'aluno_id' => $studentId,
                    'escola_id' => $schoolId,
                    'codigo' => 'SGECE-AE-' . strtoupper(bin2hex(random_bytes(16)))
                ]);
                return;
            } catch (\PDOException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException(
            'Não foi possível gerar um código único para a associação do aluno à escola.'
        );
    }

    private function asciiText(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($normalized === false) {
            throw new \RuntimeException('Não foi possível normalizar o texto da escola.');
        }

        return $normalized;
    }

    private function renderStudents(
        array $user,
        array $formValues = [],
        array $errors = []
    ): void {
        $database = Database::connect();
        $searchValue = $_GET['q'] ?? '';
        $search = is_string($searchValue) ? trim($searchValue) : '';
        $statusValue = $_GET['estado'] ?? 'todas';
        $status = is_string($statusValue) ? $statusValue : 'todas';
        if (!in_array($status, ['todas', 'activo', 'inactivo'], true)) {
            $status = 'todas';
        }

        $pageValue = $_GET['pagina'] ?? '1';
        $page = is_string($pageValue) || is_int($pageValue)
            ? filter_var(
                $pageValue,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
            : false;
        $page = $page === false ? 1 : $page;
        $pageSize = 25;

        $conditions = [];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(a.codigo_central LIKE :codigo OR a.nome_completo LIKE :nome)';
            $pattern = '%' . $search . '%';
            $parameters['codigo'] = $pattern;
            $parameters['nome'] = $pattern;
        }
        if ($status !== 'todas') {
            $conditions[] = 'a.estado = :estado';
            $parameters['estado'] = $status;
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $count = $database->prepare('SELECT COUNT(*) FROM alunos a' . $where);
        $count->execute($parameters);
        $totalStudents = (int) $count->fetchColumn();
        $totalPages = max(1, (int) ceil($totalStudents / $pageSize));
        $page = min($page, $totalPages);

        $statement = $database->prepare(
            "SELECT a.id, a.codigo_central, a.nome_completo, a.data_nascimento,
                    a.sexo, a.estado, e.nome AS escola_nome, e.distrito
             FROM alunos a
             LEFT JOIN aluno_escolas ae ON ae.id = (
                 SELECT ae2.id
                 FROM aluno_escolas ae2
                 WHERE ae2.aluno_id = a.id
                   AND ae2.estado = 'activo'
                 ORDER BY ae2.data_entrada DESC, ae2.id DESC
                 LIMIT 1
             )
             LEFT JOIN escolas e ON e.id = ae.escola_id" . $where . '
             ORDER BY a.nome_completo, a.id
             LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, \PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $pageSize, \PDO::PARAM_INT);
        $statement->execute();

        $student = null;
        if ($formValues === []) {
            $editValue = $_GET['editar'] ?? '';
            if ($editValue !== '') {
                $editId = is_string($editValue) || is_int($editValue)
                    ? filter_var(
                        $editValue,
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]]
                    )
                    : false;
                if ($editId !== false) {
                    $edit = $database->prepare(
                        'SELECT id, codigo_central, nome_completo, data_nascimento,
                                sexo, nome_pai, nome_mae, contacto, endereco
                         FROM alunos WHERE id = :id'
                    );
                    $edit->execute(['id' => $editId]);
                    $student = $edit->fetch() ?: null;
                    if ($student !== null) {
                        $student['bi'] = '';
                        $association = $this->activeStudentSchool(
                            $database,
                            (int) $student['id']
                        );
                        $student['escola_id'] = $association['escola_id'] ?? '';
                        $student['distrito'] = $association['distrito'] ?? '';
                    }
                }

                if ($student === null) {
                    $_SESSION['_students_flash'] = [
                        'type' => 'danger',
                        'message' => 'O aluno seleccionado não foi encontrado.'
                    ];
                    $this->redirect('/admin/alunos');
                }
            }
        }

        $formValues = $formValues !== []
            ? $formValues
            : ($student ?? [
                'id' => '',
                'codigo_central' => '',
                'nome_completo' => '',
                'data_nascimento' => '',
                'sexo' => '',
                'bi' => '',
                'distrito' => '',
                'escola_id' => '',
                'nome_pai' => '',
                'nome_mae' => '',
                'contacto' => '',
                'endereco' => ''
            ]);

        $formStudentId = filter_var(
            (string) ($formValues['id'] ?? ''),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $currentAssociation = $formStudentId === false
            ? null
            : $this->activeStudentSchool($database, (int) $formStudentId);
        $schoolAssociationLocked = $currentAssociation !== null;
        if ($schoolAssociationLocked) {
            $formValues['escola_id'] = (string) $currentAssociation['escola_id'];
            $formValues['distrito'] = $currentAssociation['distrito'];
        }

        $schoolQuery = 'SELECT id, codigo, nome, distrito, tipo, estado
                        FROM escolas WHERE estado = \'activa\'';
        $schoolParameters = [];
        if ($schoolAssociationLocked && $currentAssociation['estado_escola'] !== 'activa') {
            $schoolQuery .= ' OR id = :current_school_id';
            $schoolParameters['current_school_id'] = $currentAssociation['escola_id'];
        }
        $schoolQuery .= ' ORDER BY distrito, nome, id';
        $schoolStatement = $database->prepare($schoolQuery);
        $schoolStatement->execute($schoolParameters);
        $schools = $schoolStatement->fetchAll();
        $districts = [];
        foreach ($schools as $schoolOption) {
            $districts[$schoolOption['distrito']] = $schoolOption['distrito'];
        }
        ksort($districts, SORT_NATURAL | SORT_FLAG_CASE);

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $flash = $_SESSION['_students_flash'] ?? null;
        unset($_SESSION['_students_flash']);

        $studentPageData = [
            'adminPath' => $this->basePath() . '/admin',
            'students' => $statement->fetchAll(),
            'schools' => $schools,
            'districts' => $districts,
            'schoolAssociationLocked' => $schoolAssociationLocked,
            'totalStudents' => $totalStudents,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'statusFilter' => $status,
            'csrfToken' => $_SESSION['csrf_token'],
            'sexOptions' => self::STUDENT_SEXES,
            'formValues' => $formValues,
            'errors' => $errors,
            'flash' => $flash
        ];

        ob_start();
        $this->view('admin/students/index', $studentPageData);
        $content = ob_get_clean();

        $this->renderAdminLayout($user, 'Gestão de alunos', 'students', $content);
    }

    private function renderSchools(
        array $user,
        array $formValues = [],
        array $errors = []
    ): void {
        $database = Database::connect();
        $searchValue = $_GET['q'] ?? '';
        $search = is_string($searchValue) ? trim($searchValue) : '';
        $statusValue = $_GET['estado'] ?? 'todas';
        $status = is_string($statusValue) ? $statusValue : 'todas';
        if (!in_array($status, ['todas', 'activa', 'inactiva'], true)) {
            $status = 'todas';
        }

        $pageValue = $_GET['pagina'] ?? '1';
        $page = is_string($pageValue) || is_int($pageValue)
            ? filter_var(
                $pageValue,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
            : false;
        $page = $page === false ? 1 : $page;
        $pageSize = 25;

        $conditions = [];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(codigo LIKE :codigo OR nome LIKE :nome
                OR provincia LIKE :provincia OR distrito LIKE :distrito)';
            $pattern = '%' . $search . '%';
            $parameters['codigo'] = $pattern;
            $parameters['nome'] = $pattern;
            $parameters['provincia'] = $pattern;
            $parameters['distrito'] = $pattern;
        }
        if ($status !== 'todas') {
            $conditions[] = 'estado = :estado';
            $parameters['estado'] = $status;
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $count = $database->prepare('SELECT COUNT(*) FROM escolas' . $where);
        $count->execute($parameters);
        $totalSchools = (int) $count->fetchColumn();
        $totalPages = max(1, (int) ceil($totalSchools / $pageSize));
        $page = min($page, $totalPages);

        $statement = $database->prepare(
            'SELECT id, codigo, nome, tipo, provincia, distrito, localidade,
                    telefone, email, estado
             FROM escolas' . $where . '
             ORDER BY nome, id
             LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, \PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $pageSize, \PDO::PARAM_INT);
        $statement->execute();

        $school = null;
        if ($formValues === []) {
            $editValue = $_GET['editar'] ?? '';
            if ($editValue !== '') {
                $editId = is_string($editValue) || is_int($editValue)
                    ? filter_var(
                        $editValue,
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]]
                    )
                    : false;
                if ($editId !== false) {
                    $edit = $database->prepare(
                        'SELECT id, codigo, nome, tipo, provincia, distrito,
                                localidade, endereco, telefone
                         FROM escolas WHERE id = :id'
                    );
                    $edit->execute(['id' => $editId]);
                    $school = $edit->fetch() ?: null;
                    if ($school !== null) {
                        $school['email'] = $this->availableSchoolEmail(
                            $database,
                            $school['nome'],
                            $school['distrito'],
                            (int) $school['id']
                        );
                    }
                }

                if ($school === null) {
                    $_SESSION['_schools_flash'] = [
                        'type' => 'danger',
                        'message' => 'A escola seleccionada não foi encontrada.'
                    ];
                    $this->redirect('/admin/escolas');
                }
            }
        }

        $formValues = $formValues !== []
            ? $formValues
            : ($school ?? [
                'id' => '',
                'codigo' => '',
                'nome' => '',
                'tipo' => '',
                'provincia' => self::SCHOOL_PROVINCE,
                'distrito' => '',
                'localidade' => '',
                'endereco' => '',
                'telefone' => '',
                'email' => ''
            ]);

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $flash = $_SESSION['_schools_flash'] ?? null;
        unset($_SESSION['_schools_flash']);

        $schoolPageData = [
            'adminPath' => $this->basePath() . '/admin',
            'schools' => $statement->fetchAll(),
            'totalSchools' => $totalSchools,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'statusFilter' => $status,
            'csrfToken' => $_SESSION['csrf_token'],
            'schoolLevels' => self::SCHOOL_LEVELS,
            'districts' => self::ZAMBEZIA_DISTRICTS,
            'formValues' => $formValues,
            'errors' => $errors,
            'flash' => $flash
        ];

        ob_start();
        $this->view('admin/schools/index', $schoolPageData);
        $content = ob_get_clean();

        $this->renderAdminLayout($user, 'Gestão de escolas', 'schools', $content);
    }

    private function renderAdminLayout(
        array $user,
        string $pageTitle,
        string $activeMenu,
        string $content
    ): void {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $this->view('admin/layouts/main', [
            'pageTitle' => $pageTitle,
            'activeMenu' => $activeMenu,
            'nomeUsuario' => trim((string) $user['nome']),
            'nivelUsuario' => trim((string) $user['nivel']),
            'csrfToken' => $_SESSION['csrf_token'],
            'content' => $content
        ]);
    }

    private function startSession(): void
    {
        Session::start();
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
