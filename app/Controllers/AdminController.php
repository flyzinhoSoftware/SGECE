<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use Config\Database;

class AdminController extends Controller
{
    private const SCHOOL_PROVINCE = 'Zambézia';
    private const SCHOOL_EMAIL_DOMAIN = 'sgece.gov.mz';
    private const MAX_STUDENTS_PER_TURMA = 50;

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

    public function users(): void
    {
        $user = $this->requireAdministrator();
        $this->renderUsers($user);
    }

    public function saveUser(): void
    {
        $actor = $this->requireAdministrator();
        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $postedId = $this->postedString('id');
        $idValue = $postedId ?? '';
        $id = $idValue === ''
            ? 0
            : filter_var($idValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $name = trim($this->postedString('nome') ?? '');
        $phone = $this->postedString('telefone') ?? '';
        $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
        $postedRoleId = filter_var(
            $this->postedString('nivel_acesso_id') ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $district = $this->postedString('distrito') ?? '';
        $schoolIdValue = $this->postedString('escola_id') ?? '';
        $hasSchoolSelection = $schoolIdValue !== '';
        $schoolId = $schoolIdValue === ''
            ? false
            : filter_var($schoolIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $formValues = [
            'id' => $idValue,
            'nome' => $name,
            'email' => '',
            'email_suffix' => '',
            'telefone' => $phone,
            'nivel_acesso_id' => '',
            'distrito' => $district,
            'escola_id' => $schoolId === false ? '' : (string) $schoolId
        ];
        $errors = [];
        $nameLength = function_exists('mb_strlen')
            ? mb_strlen($name, 'UTF-8')
            : iconv_strlen($name, 'UTF-8');
        if ($nameLength === false) {
            $nameLength = strlen($name);
        }

        if ($postedId === null || $id === false) {
            $errors['id'] = 'O utilizador seleccionado é inválido.';
        }
        if ($name === '' || $nameLength > 150) {
            $errors['nome'] = 'Introduza um nome com até 150 caracteres.';
        }
        if (
            $phone === '' ||
            strlen($phone) > 30 ||
            preg_match('/^[+()\d\s.-]+$/', $phone) !== 1 ||
            strlen($phoneDigits) < 8
        ) {
            $errors['telefone'] = 'Introduza um telefone válido com pelo menos 8 dígitos.';
        }
        if ($hasSchoolSelection && $schoolId === false) {
            $errors['escola_id'] = 'Seleccione uma escola válida.';
        }

        $database = Database::connect();
        $roleIds = [];
        $roleNames = [];
        foreach ($database->query('SELECT id, nome FROM niveis_acesso')->fetchAll() as $accessLevel) {
            $roleIds[$accessLevel['nome']] = (int) $accessLevel['id'];
            $roleNames[(string) $accessLevel['id']] = $accessLevel['nome'];
        }

        if ($hasSchoolSelection) {
            $roleId = $roleIds['Escola'] ?? false;
        } elseif ($actor['nivel'] === 'Root' && $postedRoleId === ($roleIds['Root'] ?? null)) {
            $roleId = $roleIds['Root'] ?? false;
        } else {
            $roleId = $roleIds['Admin'] ?? false;
            if (
                $actor['nivel'] === 'Root' &&
                $postedRoleId !== false &&
                !in_array($postedRoleId, [
                    $roleIds['Admin'] ?? 0,
                    $roleIds['Root'] ?? 0,
                    $roleIds['Escola'] ?? 0
                ], true)
            ) {
                $errors['nivel_acesso_id'] = 'Seleccione um nível de acesso válido.';
            }
        }
        $roleName = $roleId === false ? null : ($roleNames[(string) $roleId] ?? null);
        if ($roleName === null) {
            $errors['nivel_acesso_id'] = 'Não foi possível determinar o nível de acesso.';
        } elseif ($roleName === 'Root' && $actor['nivel'] !== 'Root') {
            $errors['nivel_acesso_id'] = 'Não tem permissão para atribuir o nível Root.';
        }
        $formValues['nivel_acesso_id'] = $roleId === false ? '' : (string) $roleId;

        $existingUser = null;
        if ($id > 0) {
            $existingStatement = $database->prepare(
                'SELECT u.id, u.nome, u.email, u.nivel_acesso_id,
                        u.escola_id, u.estado, na.nome AS nivel
                 FROM usuarios u
                 INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
                 WHERE u.id = :id'
            );
            $existingStatement->execute(['id' => $id]);
            $existingUser = $existingStatement->fetch() ?: null;
            if ($existingUser === null) {
                $errors['id'] = 'O utilizador seleccionado já não existe.';
            } elseif (
                $existingUser['nivel'] === 'Root' &&
                $actor['nivel'] !== 'Root'
            ) {
                $errors['id'] = 'Não tem permissão para alterar uma conta Root.';
            } elseif (
                (int) $id === (int) $actor['id'] &&
                (int) $roleId !== (int) $existingUser['nivel_acesso_id']
            ) {
                $errors['nivel_acesso_id'] = 'Não pode alterar o seu próprio nível de acesso.';
            }
        }

        if ($hasSchoolSelection) {
            if ($schoolId === false || $district === '') {
                $errors['escola_id'] = 'Seleccione o distrito e a escola do utilizador.';
            } else {
                $schoolStatement = $database->prepare(
                    'SELECT id, distrito, estado FROM escolas WHERE id = :id'
                );
                $schoolStatement->execute(['id' => $schoolId]);
                $school = $schoolStatement->fetch() ?: null;
                $keepCurrentInactiveSchool = $existingUser !== null &&
                    (int) ($existingUser['escola_id'] ?? 0) === (int) $schoolId;
                if (
                    $school === null ||
                    $school['distrito'] !== $district ||
                    (
                        $school['estado'] !== 'activa' &&
                        !$keepCurrentInactiveSchool
                    )
                ) {
                    $errors['escola_id'] =
                        'Seleccione uma escola activa dentro do distrito escolhido.';
                }
            }
            $formValues['distrito'] = $district;
            $formValues['escola_id'] = $schoolId === false ? '' : (string) $schoolId;
        } else {
            $formValues['distrito'] = '';
            $formValues['escola_id'] = '';
        }

        $isEditing = $id > 0;
        $emailChanged = $id === 0 ||
            ($existingUser !== null && $name !== $existingUser['nome']);
        $email = '';
        $emailSuffix = null;
        $nameParts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (
            $emailChanged &&
            $name !== '' &&
            !isset($errors['nome']) &&
            (!is_array($nameParts) || count($nameParts) < 2)
        ) {
            $errors['nome'] = 'Introduza o nome próprio e pelo menos um sobrenome.';
        }
        if ($existingUser !== null && !$emailChanged) {
            $email = $existingUser['email'];
            $emailSuffix = null;
        } elseif (
            $emailChanged &&
            $name !== '' &&
            !isset($errors['nome']) &&
            $id !== false
        ) {
            $generatedEmail = $this->availableUserEmail(
                $database,
                $name,
                $id > 0 ? (int) $id : 0
            );
            if ($generatedEmail === null) {
                $errors['email'] = 'Não foi possível gerar um e-mail único para este nome.';
            } else {
                $email = $generatedEmail['email'];
                $emailSuffix = $generatedEmail['suffix'];
            }
        }
        $formValues['email'] = $email;
        $formValues['email_suffix'] = $emailSuffix ?? '';
        if (
            $email !== '' &&
            (
                strlen($email) > 150 ||
                filter_var($email, FILTER_VALIDATE_EMAIL) === false
            )
        ) {
            $errors['email'] = 'Não foi possível gerar um e-mail válido.';
        }

        if ($errors !== []) {
            $this->renderUsers($actor, $formValues, $errors);
            return;
        }

        $schoolIdToSave = $hasSchoolSelection ? (int) $schoolId : null;
        $passwordUpdated = !$isEditing;
        $database->beginTransaction();
        try {
            if ($id > 0) {
                $lockUser = $database->prepare(
                    'SELECT u.nivel_acesso_id, u.escola_id, u.telefone, u.estado,
                            na.nome AS nivel
                     FROM usuarios u
                     INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
                     WHERE u.id = :id FOR UPDATE'
                );
                $lockUser->execute(['id' => $id]);
                $lockedUser = $lockUser->fetch() ?: null;
                if ($lockedUser === null) {
                    $database->rollBack();
                    $this->renderUsers($actor, $formValues, [
                        'id' => 'O utilizador seleccionado já não existe.'
                    ]);
                    return;
                }
                if (
                    $lockedUser['nivel'] === 'Root' &&
                    $actor['nivel'] !== 'Root'
                ) {
                    $database->rollBack();
                    $this->renderUsers($actor, $formValues, [
                        'id' => 'Não tem permissão para alterar uma conta Root.'
                    ]);
                    return;
                }
                if (
                    (int) $id === (int) $actor['id'] &&
                    (int) $roleId !== (int) $lockedUser['nivel_acesso_id']
                ) {
                    $database->rollBack();
                    $this->renderUsers($actor, $formValues, [
                        'nivel_acesso_id' => 'Não pode alterar o seu próprio nível de acesso.'
                    ]);
                    return;
                }
                if (
                    $lockedUser['nivel'] === 'Root' &&
                    $roleName !== 'Root' &&
                    $lockedUser['estado'] === 'activo'
                ) {
                    $activeRoots = $database->query(
                        "SELECT u.id
                         FROM usuarios u
                         INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
                         WHERE na.nome = 'Root' AND u.estado = 'activo'
                         FOR UPDATE"
                    )->fetchAll(\PDO::FETCH_COLUMN);
                    if (count($activeRoots) <= 1) {
                        $database->rollBack();
                        $this->renderUsers($actor, $formValues, [
                            'nivel_acesso_id' => 'Não é possível remover o último utilizador Root activo.'
                        ]);
                        return;
                    }
                }

                if ($schoolIdToSave !== null) {
                    $lockedSchool = $database->prepare(
                        'SELECT distrito, estado FROM escolas WHERE id = :id FOR UPDATE'
                    );
                    $lockedSchool->execute(['id' => $schoolIdToSave]);
                    $currentSchool = $lockedSchool->fetch() ?: null;
                    $keepCurrentInactiveSchool =
                        (int) ($lockedUser['escola_id'] ?? 0) === $schoolIdToSave;
                    if (
                        $currentSchool === null ||
                        $currentSchool['distrito'] !== $district ||
                        (
                            $currentSchool['estado'] !== 'activa' &&
                            !$keepCurrentInactiveSchool
                        )
                    ) {
                        $database->rollBack();
                        $this->renderUsers($actor, $formValues, [
                            'escola_id' => 'Seleccione uma escola activa dentro do distrito escolhido.'
                        ]);
                        return;
                    }
                }

                $parameters = [
                    'nome' => $name,
                    'email' => $email,
                    'telefone' => $phone === '' ? null : $phone,
                    'nivel_acesso_id' => (int) $roleId,
                    'escola_id' => $schoolIdToSave,
                    'id' => (int) $id
                ];
                $updates = [
                    'nome = :nome',
                    'email = :email',
                    'telefone = :telefone',
                    'nivel_acesso_id = :nivel_acesso_id',
                    'escola_id = :escola_id'
                ];
                $currentPhoneDigits = preg_replace(
                    '/\D+/',
                    '',
                    (string) ($lockedUser['telefone'] ?? '')
                ) ?? '';
                if ($currentPhoneDigits !== $phoneDigits) {
                    $parameters['palavra_passe'] = password_hash(
                        $phoneDigits,
                        PASSWORD_DEFAULT
                    );
                    $updates[] = 'palavra_passe = :palavra_passe';
                    $passwordUpdated = true;
                }
                $updateUser = $database->prepare(
                    'UPDATE usuarios SET ' . implode(', ', $updates) . ' WHERE id = :id'
                );
                $updateUser->execute($parameters);
            } else {
                if ($schoolIdToSave !== null) {
                    $lockedSchool = $database->prepare(
                        'SELECT distrito, estado FROM escolas WHERE id = :id FOR UPDATE'
                    );
                    $lockedSchool->execute(['id' => $schoolIdToSave]);
                    $currentSchool = $lockedSchool->fetch() ?: null;
                    if (
                        $currentSchool === null ||
                        $currentSchool['distrito'] !== $district ||
                        $currentSchool['estado'] !== 'activa'
                    ) {
                        $database->rollBack();
                        $this->renderUsers($actor, $formValues, [
                            'escola_id' => 'Seleccione uma escola activa dentro do distrito escolhido.'
                        ]);
                        return;
                    }
                }
                $insertUser = $database->prepare(
                    'INSERT INTO usuarios
                        (escola_id, nivel_acesso_id, nome, email, telefone,
                         palavra_passe, estado)
                     VALUES
                        (:escola_id, :nivel_acesso_id, :nome, :email, :telefone,
                         :palavra_passe, \'activo\')'
                );
                $insertUser->execute([
                    'escola_id' => $schoolIdToSave,
                    'nivel_acesso_id' => (int) $roleId,
                    'nome' => $name,
                    'email' => $email,
                    'telefone' => $phone === '' ? null : $phone,
                    'palavra_passe' => password_hash($phoneDigits, PASSWORD_DEFAULT)
                ]);
            }
            $database->commit();
        } catch (\PDOException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }
            $alternateEmail = $this->availableUserEmail(
                $database,
                $name,
                $id > 0 ? (int) $id : 0
            );
            if ($alternateEmail !== null) {
                $formValues['email'] = $alternateEmail['email'];
                $formValues['email_suffix'] = $alternateEmail['suffix'];
            }
            $this->renderUsers($actor, $formValues, [
                'email' => $alternateEmail === null
                    ? 'Não foi possível gerar outro e-mail único.'
                    : 'O e-mail gerado já foi utilizado; foi sugerida outra combinação. Submeta novamente.'
            ]);
            return;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        $_SESSION['_users_flash'] = [
            'type' => 'success',
            'message' => ($isEditing
                ? 'Utilizador actualizado com sucesso.'
                : 'Utilizador registado com sucesso.')
                . ($emailChanged ? ' E-mail de acesso: ' . $email . '.' : '')
                . ($passwordUpdated
                    ? ' Palavra-passe definida com os dígitos do telefone.'
                    : '')
        ];
        $this->redirect('/admin/utilizadores');
    }

    public function updateUserStatus(): void
    {
        $actor = $this->requireAdministrator();
        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $id = filter_var(
            $this->postedString('id') ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $status = $this->postedString('estado');
        if ($id === false || !in_array($status, ['activo', 'inactivo'], true)) {
            $_SESSION['_users_flash'] = [
                'type' => 'danger',
                'message' => 'Pedido de alteração de estado inválido.'
            ];
            $this->redirect('/admin/utilizadores');
        }
        if ((int) $id === (int) $actor['id'] && $status === 'inactivo') {
            $_SESSION['_users_flash'] = [
                'type' => 'danger',
                'message' => 'Não pode desactivar a sua própria conta.'
            ];
            $this->redirect('/admin/utilizadores');
        }

        $database = Database::connect();
        $database->beginTransaction();
        try {
            $lockUser = $database->prepare(
                'SELECT u.estado, na.nome AS nivel
                 FROM usuarios u
                 INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
                 WHERE u.id = :id FOR UPDATE'
            );
            $lockUser->execute(['id' => $id]);
            $target = $lockUser->fetch() ?: null;
            if ($target === null) {
                $database->rollBack();
                $_SESSION['_users_flash'] = [
                    'type' => 'danger',
                    'message' => 'O utilizador seleccionado não foi encontrado.'
                ];
                $this->redirect('/admin/utilizadores');
            }
            if ($target['nivel'] === 'Root' && $actor['nivel'] !== 'Root') {
                $database->rollBack();
                $_SESSION['_users_flash'] = [
                    'type' => 'danger',
                    'message' => 'Não tem permissão para alterar uma conta Root.'
                ];
                $this->redirect('/admin/utilizadores');
            }
            if (
                $target['nivel'] === 'Root' &&
                $target['estado'] === 'activo' &&
                $status === 'inactivo'
            ) {
                $activeRoots = $database->query(
                    "SELECT u.id
                     FROM usuarios u
                     INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
                     WHERE na.nome = 'Root' AND u.estado = 'activo'
                     FOR UPDATE"
                )->fetchAll(\PDO::FETCH_COLUMN);
                if (count($activeRoots) <= 1) {
                    $database->rollBack();
                    $_SESSION['_users_flash'] = [
                        'type' => 'danger',
                        'message' => 'Não é possível desactivar o último utilizador Root activo.'
                    ];
                    $this->redirect('/admin/utilizadores');
                }
            }

            $update = $database->prepare(
                'UPDATE usuarios SET estado = :estado WHERE id = :id'
            );
            $update->execute(['estado' => $status, 'id' => $id]);
            $database->commit();
        } catch (\PDOException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        $_SESSION['_users_flash'] = [
            'type' => 'success',
            'message' => $status === 'activo'
                ? 'Utilizador activado com sucesso.'
                : 'Utilizador desactivado com sucesso.'
        ];
        $this->redirect('/admin/utilizadores');
    }

    public function enrollment(): void
    {
        $user = $this->requireAdministrator();
        $this->renderEnrollments($user);
    }

    public function academic(): void
    {
        $user = $this->requireAdministrator();
        $this->renderAcademic($user);
    }

    public function saveAcademicYear(): void
    {
        $this->requireAdministrator();
        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $idValue = $this->postedString('id') ?? '';
        $id = $idValue === ''
            ? 0
            : filter_var($idValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $yearValue = $this->postedString('ano') ?? '';
        $year = filter_var(
            $yearValue,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1900, 'max_range' => 2200]]
        );
        $startDate = $this->postedString('data_inicio') ?? '';
        $endDate = $this->postedString('data_fim') ?? '';
        $status = $this->postedString('estado') ?? '';

        if (
            $id === false ||
            $year === false ||
            !$this->isValidAcademicDate($startDate) ||
            !$this->isValidAcademicDate($endDate) ||
            ($startDate !== '' && $endDate !== '' && $startDate > $endDate) ||
            !in_array($status, ['planeado', 'activo', 'encerrado'], true)
        ) {
            $_SESSION['_academic_flash'] = [
                'type' => 'danger',
                'message' => 'Verifique o ano lectivo, as datas e o estado seleccionados.'
            ];
            $this->redirect('/admin/gestao-academica#anos');
        }

        $database = Database::connect();
        $database->beginTransaction();
        try {
            if ($id > 0) {
                $existing = $database->prepare(
                    'SELECT ano FROM anos_lectivos WHERE id = :id FOR UPDATE'
                );
                $existing->execute(['id' => $id]);
                $currentYear = $existing->fetchColumn();
                if ($currentYear === false) {
                    $database->rollBack();
                    $_SESSION['_academic_flash'] = [
                        'type' => 'danger',
                        'message' => 'O ano lectivo seleccionado já não existe.'
                    ];
                    $this->redirect('/admin/gestao-academica#anos');
                }

                if ((int) $currentYear !== (int) $year) {
                    $references = $database->prepare(
                        'SELECT COUNT(*) FROM turmas WHERE ano_lectivo_id = :id'
                    );
                    $references->execute(['id' => $id]);
                    if ((int) $references->fetchColumn() > 0) {
                        $database->rollBack();
                        $_SESSION['_academic_flash'] = [
                            'type' => 'danger',
                            'message' => 'Não é possível alterar o ano porque já existem turmas associadas.'
                        ];
                        $this->redirect('/admin/gestao-academica#anos');
                    }
                }
            }

            if ($status === 'activo') {
                $closeOtherYears = $database->prepare(
                    "UPDATE anos_lectivos
                     SET estado = 'encerrado'
                     WHERE estado = 'activo' AND id <> :id"
                );
                $closeOtherYears->execute(['id' => $id]);
            }

            $parameters = [
                'ano' => (int) $year,
                'data_inicio' => $startDate === '' ? null : $startDate,
                'data_fim' => $endDate === '' ? null : $endDate,
                'estado' => $status
            ];
            if ($id > 0) {
                $parameters['id'] = $id;
                $statement = $database->prepare(
                    'UPDATE anos_lectivos
                     SET ano = :ano, data_inicio = :data_inicio,
                         data_fim = :data_fim, estado = :estado
                     WHERE id = :id'
                );
            } else {
                $statement = $database->prepare(
                    'INSERT INTO anos_lectivos (ano, data_inicio, data_fim, estado)
                     VALUES (:ano, :data_inicio, :data_fim, :estado)'
                );
            }
            $statement->execute($parameters);
            $database->commit();
        } catch (\PDOException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }
            $_SESSION['_academic_flash'] = [
                'type' => 'danger',
                'message' => 'Já existe um registo para esse ano lectivo.'
            ];
            $this->redirect('/admin/gestao-academica#anos');
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        $_SESSION['_academic_flash'] = [
            'type' => 'success',
            'message' => $id > 0
                ? 'Ano lectivo actualizado com sucesso.'
                : 'Ano lectivo registado com sucesso.'
        ];
        $this->redirect('/admin/gestao-academica#anos');
    }

    public function saveAcademicClass(): void
    {
        $this->requireAdministrator();
        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $idValue = $this->postedString('id') ?? '';
        $id = $idValue === ''
            ? 0
            : filter_var($idValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $levelIdValue = $this->postedString('nivel_ensino_id') ?? '';
        $levelId = filter_var(
            $levelIdValue,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $code = strtoupper($this->postedString('codigo') ?? '');
        $name = $this->postedString('nome') ?? '';
        $order = filter_var(
            $this->postedString('ordem') ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 100]]
        );
        $status = $this->postedString('estado') ?? '';
        $rawDisciplineIds = $_POST['disciplinas'] ?? [];
        $disciplineIds = [];
        $invalidDisciplineIds = !is_array($rawDisciplineIds) || count($rawDisciplineIds) > 100;
        if (is_array($rawDisciplineIds)) {
            foreach ($rawDisciplineIds as $value) {
                if (!is_string($value) && !is_int($value)) {
                    $invalidDisciplineIds = true;
                    break;
                }
                $disciplineId = filter_var(
                    (string) $value,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );
                if ($disciplineId === false) {
                    $invalidDisciplineIds = true;
                    break;
                }
                $disciplineIds[] = (int) $disciplineId;
            }
        }
        $disciplineIds = array_values(array_unique($disciplineIds));
        sort($disciplineIds, SORT_NUMERIC);

        if (
            $id === false ||
            $levelId === false ||
            $code === '' ||
            strlen($code) > 30 ||
            $name === '' ||
            strlen($name) > 100 ||
            $order === false ||
            !in_array($status, ['activa', 'inactiva'], true) ||
            $invalidDisciplineIds
        ) {
            $_SESSION['_academic_flash'] = [
                'type' => 'danger',
                'message' => 'Verifique os dados da classe e as disciplinas seleccionadas.'
            ];
            $this->redirect('/admin/gestao-academica#classes');
        }

        $database = Database::connect();
        $levelExists = $database->prepare('SELECT id FROM niveis_ensino WHERE id = :id');
        $levelExists->execute(['id' => $levelId]);
        if ($levelExists->fetchColumn() === false) {
            $_SESSION['_academic_flash'] = [
                'type' => 'danger',
                'message' => 'Seleccione um nível de ensino válido.'
            ];
            $this->redirect('/admin/gestao-academica#classes');
        }

        $database->beginTransaction();
        try {
            if ($id > 0) {
                $existing = $database->prepare(
                    'SELECT nivel_ensino_id FROM classes WHERE id = :id FOR UPDATE'
                );
                $existing->execute(['id' => $id]);
                $currentLevelId = $existing->fetchColumn();
                if ($currentLevelId === false) {
                    $database->rollBack();
                    $_SESSION['_academic_flash'] = [
                        'type' => 'danger',
                        'message' => 'A classe seleccionada já não existe.'
                    ];
                    $this->redirect('/admin/gestao-academica#classes');
                }
                if ((int) $currentLevelId !== (int) $levelId) {
                    $references = $database->prepare(
                        'SELECT COUNT(*) FROM turmas WHERE classe_id = :id'
                    );
                    $references->execute(['id' => $id]);
                    if ((int) $references->fetchColumn() > 0) {
                        $database->rollBack();
                        $_SESSION['_academic_flash'] = [
                            'type' => 'danger',
                            'message' => 'Não é possível mudar o nível de uma classe já usada por turmas.'
                        ];
                        $this->redirect('/admin/gestao-academica#classes');
                    }
                }
            }

            if ($disciplineIds !== []) {
                $placeholders = implode(',', array_fill(0, count($disciplineIds), '?'));
                $lockDisciplines = $database->prepare(
                    'SELECT id, nivel_ensino_id FROM disciplinas
                     WHERE id IN (' . $placeholders . ') FOR UPDATE'
                );
                $lockDisciplines->execute($disciplineIds);
                $lockedDisciplines = $lockDisciplines->fetchAll();
                if (
                    count($lockedDisciplines) !== count($disciplineIds) ||
                    count(array_filter(
                        $lockedDisciplines,
                        static function (array $discipline) use ($levelId): bool {
                            return (int) $discipline['nivel_ensino_id'] !== (int) $levelId;
                        }
                    )) > 0
                ) {
                    $database->rollBack();
                    $_SESSION['_academic_flash'] = [
                        'type' => 'danger',
                        'message' => 'As disciplinas devem pertencer ao nível de ensino da classe.'
                    ];
                    $this->redirect('/admin/gestao-academica#classes');
                }
            }

            $parameters = [
                'nivel_ensino_id' => (int) $levelId,
                'codigo' => $code,
                'nome' => $name,
                'ordem' => (int) $order,
                'estado' => $status
            ];
            if ($id > 0) {
                $parameters['id'] = $id;
                $statement = $database->prepare(
                    'UPDATE classes
                     SET nivel_ensino_id = :nivel_ensino_id, codigo = :codigo,
                         nome = :nome, ordem = :ordem, estado = :estado
                     WHERE id = :id'
                );
                $statement->execute($parameters);
                $classId = (int) $id;
            } else {
                $statement = $database->prepare(
                    'INSERT INTO classes
                        (nivel_ensino_id, codigo, nome, ordem, estado)
                     VALUES
                        (:nivel_ensino_id, :codigo, :nome, :ordem, :estado)'
                );
                $statement->execute($parameters);
                $classId = (int) $database->lastInsertId();
            }

            $removeMappings = $database->prepare(
                'DELETE FROM classes_disciplinas WHERE classe_id = :classe_id'
            );
            $removeMappings->execute(['classe_id' => $classId]);
            if ($disciplineIds !== []) {
                $addMapping = $database->prepare(
                    'INSERT INTO classes_disciplinas (classe_id, disciplina_id)
                     VALUES (:classe_id, :disciplina_id)'
                );
                foreach ($disciplineIds as $disciplineId) {
                    $addMapping->execute([
                        'classe_id' => $classId,
                        'disciplina_id' => $disciplineId
                    ]);
                }
            }
            $database->commit();
        } catch (\PDOException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }
            $_SESSION['_academic_flash'] = [
                'type' => 'danger',
                'message' => 'Já existe uma classe com esse código nesse nível de ensino.'
            ];
            $this->redirect('/admin/gestao-academica#classes');
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        $_SESSION['_academic_flash'] = [
            'type' => 'success',
            'message' => $id > 0
                ? 'Classe e disciplinas actualizadas com sucesso.'
                : 'Classe registada com sucesso.'
        ];
        $this->redirect('/admin/gestao-academica#classes');
    }

    public function saveAcademicDiscipline(): void
    {
        $this->requireAdministrator();
        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $idValue = $this->postedString('id') ?? '';
        $id = $idValue === ''
            ? 0
            : filter_var($idValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $levelId = filter_var(
            $this->postedString('nivel_ensino_id') ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $code = strtoupper($this->postedString('codigo') ?? '');
        $name = $this->postedString('nome') ?? '';
        $status = $this->postedString('estado') ?? '';

        if (
            $id === false ||
            $levelId === false ||
            $code === '' ||
            strlen($code) > 30 ||
            $name === '' ||
            strlen($name) > 120 ||
            !in_array($status, ['activa', 'inactiva'], true)
        ) {
            $_SESSION['_academic_flash'] = [
                'type' => 'danger',
                'message' => 'Verifique os dados da disciplina seleccionada.'
            ];
            $this->redirect('/admin/gestao-academica#disciplinas');
        }

        $database = Database::connect();
        $levelExists = $database->prepare('SELECT id FROM niveis_ensino WHERE id = :id');
        $levelExists->execute(['id' => $levelId]);
        if ($levelExists->fetchColumn() === false) {
            $_SESSION['_academic_flash'] = [
                'type' => 'danger',
                'message' => 'Seleccione um nível de ensino válido.'
            ];
            $this->redirect('/admin/gestao-academica#disciplinas');
        }

        $parameters = [
            'nivel_ensino_id' => (int) $levelId,
            'codigo' => $code,
            'nome' => $name,
            'estado' => $status
        ];
        $database->beginTransaction();
        try {
            if ($id > 0) {
                $existing = $database->prepare(
                    'SELECT nivel_ensino_id FROM disciplinas WHERE id = :id FOR UPDATE'
                );
                $existing->execute(['id' => $id]);
                $currentLevelId = $existing->fetchColumn();
                if ($currentLevelId === false) {
                    $database->rollBack();
                    $_SESSION['_academic_flash'] = [
                        'type' => 'danger',
                        'message' => 'A disciplina seleccionada já não existe.'
                    ];
                    $this->redirect('/admin/gestao-academica#disciplinas');
                }
                if ((int) $currentLevelId !== (int) $levelId) {
                    $mappings = $database->prepare(
                        'SELECT COUNT(*) FROM classes_disciplinas WHERE disciplina_id = :id'
                    );
                    $mappings->execute(['id' => $id]);
                    if ((int) $mappings->fetchColumn() > 0) {
                        $database->rollBack();
                        $_SESSION['_academic_flash'] = [
                            'type' => 'danger',
                            'message' => 'Remova a disciplina das classes antes de alterar o nível de ensino.'
                        ];
                        $this->redirect('/admin/gestao-academica#disciplinas');
                    }
                }
                $parameters['id'] = $id;
                $statement = $database->prepare(
                    'UPDATE disciplinas
                     SET nivel_ensino_id = :nivel_ensino_id, codigo = :codigo,
                         nome = :nome, estado = :estado
                     WHERE id = :id'
                );
            } else {
                $statement = $database->prepare(
                    'INSERT INTO disciplinas (nivel_ensino_id, codigo, nome, estado)
                     VALUES (:nivel_ensino_id, :codigo, :nome, :estado)'
                );
            }
            $statement->execute($parameters);
            $database->commit();
        } catch (\PDOException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }
            $_SESSION['_academic_flash'] = [
                'type' => 'danger',
                'message' => 'Já existe uma disciplina com esse código nesse nível de ensino.'
            ];
            $this->redirect('/admin/gestao-academica#disciplinas');
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        $_SESSION['_academic_flash'] = [
            'type' => 'success',
            'message' => $id > 0
                ? 'Disciplina actualizada com sucesso.'
                : 'Disciplina registada com sucesso.'
        ];
        $this->redirect('/admin/gestao-academica#disciplinas');
    }

    public function staticPage(string $slug): void
    {
        $user = $this->requireAdministrator();
        $pages = [
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

    public function saveEnrollment(): void
    {
        $user = $this->requireAdministrator();

        if (!$this->hasValidCsrfToken()) {
            return;
        }

        $values = [
            'distrito' => trim($this->postedString('distrito') ?? ''),
            'escola_id' => $this->postedString('escola_id') ?? '',
            'aluno_id' => $this->postedString('aluno_id') ?? '',
            'ano_lectivo_id' => $this->postedString('ano_lectivo_id') ?? '',
            'classe_id' => $this->postedString('classe_id') ?? ''
        ];
        $errors = [];
        if ($values['distrito'] === '') {
            $errors['distrito'] = 'Seleccione um distrito.';
        }
        if ($values['escola_id'] === '') {
            $errors['escola_id'] = 'Seleccione uma escola.';
        } elseif (filter_var(
            $values['escola_id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) === false) {
            $errors['escola_id'] = 'Seleccione uma escola válida.';
        }
        foreach (['aluno_id', 'ano_lectivo_id', 'classe_id'] as $field) {
            $value = $values[$field];
            if ($value === '') {
                $errors[$field] = 'Este campo é obrigatório.';
                continue;
            }

            if (filter_var(
                $value,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) === false) {
                $errors[$field] = 'Seleccione uma opção válida.';
            }
        }

        if ($errors !== []) {
            $this->renderEnrollments($user, $values, $errors);
            return;
        }

        $studentId = (int) $values['aluno_id'];
        $academicYearId = (int) $values['ano_lectivo_id'];
        $classId = (int) $values['classe_id'];
        $database = Database::connect();
        $database->beginTransaction();

        try {
            $studentQuery = $database->prepare(
                "SELECT a.id, a.nome_completo, a.codigo_central,
                        ae.id AS aluno_escola_id, ae.escola_id,
                        e.nome AS escola_nome, e.distrito, e.tipo AS escola_tipo,
                        e.estado AS escola_estado
                 FROM alunos a
                 INNER JOIN aluno_escolas ae
                    ON ae.aluno_id = a.id AND ae.estado = 'activo'
                 INNER JOIN escolas e ON e.id = ae.escola_id
                 WHERE a.id = :aluno_id AND a.estado = 'activo'
                 ORDER BY ae.data_entrada DESC, ae.id DESC
                 LIMIT 1
                 FOR UPDATE"
            );
            $studentQuery->execute(['aluno_id' => $studentId]);
            $student = $studentQuery->fetch() ?: null;
            if ($student === null || $student['escola_estado'] !== 'activa') {
                $database->rollBack();
                $this->renderEnrollments($user, $values, [
                    'aluno_id' => 'Seleccione um aluno activo associado a uma escola activa.'
                ]);
                return;
            }

            if (
                $student['distrito'] !== $values['distrito'] ||
                (int) $student['escola_id'] !== (int) $values['escola_id']
            ) {
                $database->rollBack();
                $this->renderEnrollments($user, $values, [
                    'aluno_id' => 'Seleccione um aluno pertencente à escola escolhida.'
                ]);
                return;
            }

            $yearQuery = $database->prepare(
                "SELECT id, ano FROM anos_lectivos
                 WHERE id = :id AND estado = 'activo'
                 FOR UPDATE"
            );
            $yearQuery->execute(['id' => $academicYearId]);
            $academicYear = $yearQuery->fetch() ?: null;
            if ($academicYear === null) {
                $database->rollBack();
                $this->renderEnrollments($user, $values, [
                    'ano_lectivo_id' => 'Seleccione um ano lectivo activo.'
                ]);
                return;
            }

            $classQuery = $database->prepare(
                "SELECT c.id, c.estado AS classe_estado,
                        ne.codigo AS nivel_codigo
                 FROM classes c
                 INNER JOIN niveis_ensino ne ON ne.id = c.nivel_ensino_id
                 WHERE c.id = :id
                 FOR UPDATE"
            );
            $classQuery->execute(['id' => $classId]);
            $class = $classQuery->fetch() ?: null;
            $requiredLevel = self::SCHOOL_LEVEL_CODES[$student['escola_tipo']] ?? null;
            if (
                $class === null ||
                $class['classe_estado'] !== 'activa' ||
                $class['nivel_codigo'] !== $requiredLevel
            ) {
                $database->rollBack();
                $this->renderEnrollments($user, $values, [
                    'classe_id' => 'Seleccione uma classe activa compatível com o nível da escola.'
                ]);
                return;
            }

            $existingEnrollment = $database->prepare(
                "SELECT m.id
                 FROM matriculas m
                 INNER JOIN aluno_escolas ae ON ae.id = m.aluno_escola_id
                 WHERE ae.aluno_id = :aluno_id
                   AND m.ano_lectivo_id = :ano_lectivo_id
                   AND m.estado = 'activa'
                 LIMIT 1
                 FOR UPDATE"
            );
            $existingEnrollment->execute([
                'aluno_id' => $studentId,
                'ano_lectivo_id' => $academicYearId
            ]);
            if ($existingEnrollment->fetchColumn() !== false) {
                $database->rollBack();
                $this->renderEnrollments($user, $values, [
                    'aluno_id' => 'Este aluno já tem uma matrícula activa neste ano lectivo.'
                ]);
                return;
            }

            $classroom = $this->findOrCreateEnrollmentClassroom(
                $database,
                (int) $student['escola_id'],
                $classId,
                $academicYearId
            );
            $classroomId = (int) $classroom['id'];

            $processNumber = $this->nextEnrollmentProcessNumber(
                $database,
                (int) $student['escola_id'],
                $academicYearId
            );
            $insert = $database->prepare(
                "INSERT INTO matriculas
                    (aluno_escola_id, escola_id, classe_id, turma_id, ano_lectivo_id,
                     numero_processo, data_matricula, estado)
                 VALUES
                    (:aluno_escola_id, :escola_id, :classe_id, :turma_id, :ano_lectivo_id,
                     :numero_processo, CURRENT_DATE, 'activa')"
            );
            $insert->execute([
                'aluno_escola_id' => $student['aluno_escola_id'],
                'escola_id' => $student['escola_id'],
                'classe_id' => $class['id'],
                'turma_id' => $classroomId,
                'ano_lectivo_id' => $academicYearId,
                'numero_processo' => $processNumber
            ]);
            $database->commit();
        } catch (\PDOException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }
            $this->renderEnrollments($user, $values, [
                'form' => 'Não foi possível criar a turma ou reservar um número de processo único. Tente novamente.'
            ]);
            return;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        $_SESSION['_enrollment_flash'] = [
            'type' => 'success',
            'message' => 'Matrícula criada para ' . $student['nome_completo']
                . ' na turma ' . $classroom['identificacao']
                . ', com o número de processo ' . $processNumber . '.'
        ];
        $this->redirect('/admin/matriculas');
    }

    public function updateEnrollmentStatus(): void
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
        if ($id === false) {
            $_SESSION['_enrollment_flash'] = [
                'type' => 'danger',
                'message' => 'Identificador de matrícula inválido.'
            ];
            $this->redirect('/admin/matriculas');
        }

        $database = Database::connect();
        $statement = $database->prepare(
            "UPDATE matriculas
             SET estado = 'cancelada'
             WHERE id = :id AND estado = 'activa'"
        );
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() === 0) {
            $exists = $database->prepare('SELECT estado FROM matriculas WHERE id = :id');
            $exists->execute(['id' => $id]);
            $status = $exists->fetchColumn();
            $_SESSION['_enrollment_flash'] = [
                'type' => $status === false ? 'danger' : 'warning',
                'message' => $status === false
                    ? 'A matrícula seleccionada não foi encontrada.'
                    : 'Esta matrícula já não está activa.'
            ];
        } else {
            $_SESSION['_enrollment_flash'] = [
                'type' => 'success',
                'message' => 'Matrícula cancelada; o registo foi mantido no histórico.'
            ];
        }

        $this->redirect('/admin/matriculas');
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

    private function availableUserEmail(
        \PDO $database,
        string $name,
        int $excludeId
    ): ?array {
        $asciiName = strtolower($this->asciiText(trim($name)));
        $nameParts = preg_split('/\s+/', $asciiName, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($nameParts) || count($nameParts) < 2) {
            return null;
        }

        $givenName = preg_replace('/[^a-z0-9]/', '', $nameParts[0]);
        $surname = preg_replace('/[^a-z0-9]/', '', $nameParts[count($nameParts) - 1]);
        if ($givenName === null || $surname === null || $givenName === '' || $surname === '') {
            return null;
        }

        $maximumPrefixLength = 150 - strlen('@' . self::SCHOOL_EMAIL_DOMAIN) - 4;
        $surname = substr($surname, 0, $maximumPrefixLength - 1);
        $prefix = substr($givenName, 0, 1) . $surname;
        $checkEmail = $database->prepare(
            'SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1'
        );
        $start = random_int(0, 9999);
        for ($offset = 0; $offset < 10000; $offset++) {
            $suffix = str_pad((string) (($start + $offset) % 10000), 4, '0', STR_PAD_LEFT);
            $email = $prefix . $suffix . '@' . self::SCHOOL_EMAIL_DOMAIN;
            $checkEmail->execute(['email' => $email, 'id' => $excludeId]);
            if ($checkEmail->fetchColumn() === false) {
                return ['email' => $email, 'suffix' => $suffix];
            }
        }

        return null;
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

    private function findOrCreateEnrollmentClassroom(
        \PDO $database,
        int $schoolId,
        int $classId,
        int $academicYearId
    ): array {
        $statement = $database->prepare(
            "SELECT t.id, t.identificacao, t.capacidade, t.estado,
                    COUNT(m.id) AS matriculas_activas
             FROM turmas t
             LEFT JOIN matriculas m
                ON m.turma_id = t.id
               AND m.estado = 'activa'
             WHERE t.escola_id = :escola_id
               AND t.classe_id = :classe_id
               AND t.ano_lectivo_id = :ano_lectivo_id
             GROUP BY t.id, t.identificacao, t.capacidade, t.estado
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
                'matriculas_activas' => (int) $section['matriculas_activas'],
                'label_is_canonical' => $identifier === $label
            ];
        }

        for ($number = 1; ; $number++) {
            $label = $this->alphabeticClassroomLabel($number);
            if (!isset($sections[$label])) {
                $create = $database->prepare(
                    "INSERT INTO turmas
                        (escola_id, classe_id, ano_lectivo_id,
                         identificacao, capacidade, estado)
                     VALUES
                        (:escola_id, :classe_id, :ano_lectivo_id,
                         :identificacao, :capacidade, 'activa')"
                );
                $create->execute([
                    'escola_id' => $schoolId,
                    'classe_id' => $classId,
                    'ano_lectivo_id' => $academicYearId,
                    'identificacao' => $label,
                    'capacidade' => self::MAX_STUDENTS_PER_TURMA
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
            if ($section['estado'] !== 'activa' || !$section['label_is_canonical']) {
                continue;
            }
            $configuredCapacity = $section['capacidade'] === null
                ? self::MAX_STUDENTS_PER_TURMA
                : (int) $section['capacidade'];
            $capacityLimit = min(
                self::MAX_STUDENTS_PER_TURMA,
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

    private function alphabeticClassroomLabel(int $number): string
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

    private function nextEnrollmentProcessNumber(
        \PDO $database,
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

    private function renderUsers(
        array $user,
        array $formValues = [],
        array $errors = []
    ): void {
        $database = Database::connect();
        $canManageRoot = ($user['nivel'] ?? '') === 'Root';
        $allRoles = $database->query(
            "SELECT id, nome FROM niveis_acesso ORDER BY CASE nome
                    WHEN 'Escola' THEN 1
                    WHEN 'Admin' THEN 2
                    WHEN 'Root' THEN 3
                    ELSE 4
                END, nome"
        )->fetchAll();
        $roleIds = [];
        $roleNamesById = [];
        foreach ($allRoles as $role) {
            $roleIds[$role['nome']] = (string) $role['id'];
            $roleNamesById[(string) $role['id']] = $role['nome'];
        }
        $roles = array_values(array_filter(
            $allRoles,
            static function (array $role) use ($canManageRoot): bool {
                return $canManageRoot || $role['nome'] !== 'Root';
            }
        ));
        $defaultRoleId = $roleIds['Admin'] ?? ($roles[0]['id'] ?? '');

        $schools = $database->query(
            'SELECT id, nome, distrito, estado FROM escolas ORDER BY distrito, nome'
        )->fetchAll();
        $districts = $database->query(
            "SELECT DISTINCT distrito FROM escolas
             WHERE distrito <> '' ORDER BY distrito"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $readFilter = static function (string $key): string {
            $value = $_GET[$key] ?? '';
            return is_string($value) ? trim($value) : '';
        };
        $search = substr($readFilter('q'), 0, 150);
        $districtFilter = $readFilter('distrito');
        $schoolFilter = $readFilter('escola_id');
        $schoolId = $schoolFilter === ''
            ? false
            : filter_var($schoolFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $schoolFilter = $schoolId === false ? '' : (string) $schoolId;
        $roleFilter = $readFilter('nivel_acesso_id');
        $roleId = $roleFilter === ''
            ? false
            : filter_var($roleFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $allowedRoleIds = array_map(
            static function (array $role): string {
                return (string) $role['id'];
            },
            $roles
        );
        $roleFilter = $roleId !== false && in_array((string) $roleId, $allowedRoleIds, true)
            ? (string) $roleId
            : '';
        $statusFilter = $readFilter('estado');
        if (!in_array($statusFilter, ['activo', 'inactivo'], true)) {
            $statusFilter = '';
        }

        $pageValue = $readFilter('pagina');
        $page = $pageValue === ''
            ? 1
            : filter_var($pageValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $page = $page === false ? 1 : $page;
        $pageSize = 25;

        $conditions = [];
        $parameters = [];
        if (!$canManageRoot) {
            $conditions[] = "na.nome <> 'Root'";
        }
        if ($search !== '') {
            $conditions[] = '(u.nome LIKE :search_name
                OR u.email LIKE :search_email
                OR COALESCE(u.telefone, \'\') LIKE :search_phone)';
            $pattern = '%' . $search . '%';
            $parameters['search_name'] = $pattern;
            $parameters['search_email'] = $pattern;
            $parameters['search_phone'] = $pattern;
        }
        if ($districtFilter !== '') {
            $conditions[] = 'e.distrito = :distrito';
            $parameters['distrito'] = $districtFilter;
        }
        if ($schoolFilter !== '') {
            $conditions[] = 'u.escola_id = :escola_id';
            $parameters['escola_id'] = (int) $schoolFilter;
        }
        if ($roleFilter !== '') {
            $conditions[] = 'u.nivel_acesso_id = :nivel_acesso_id';
            $parameters['nivel_acesso_id'] = (int) $roleFilter;
        }
        if ($statusFilter !== '') {
            $conditions[] = 'u.estado = :estado';
            $parameters['estado'] = $statusFilter;
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $from = '
            FROM usuarios u
            INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
            LEFT JOIN escolas e ON e.id = u.escola_id';

        $count = $database->prepare('SELECT COUNT(*)' . $from . $where);
        $count->execute($parameters);
        $totalUsers = (int) $count->fetchColumn();
        $totalPages = max(1, (int) ceil($totalUsers / $pageSize));
        $page = min($page, $totalPages);

        $statement = $database->prepare(
            'SELECT u.id, u.nome, u.email, u.telefone, u.estado, u.ultimo_acesso,
                    u.escola_id, u.nivel_acesso_id, na.nome AS nivel,
                    e.nome AS escola_nome, e.distrito AS escola_distrito,
                    e.estado AS escola_estado'
            . $from . $where . '
             ORDER BY u.nome, u.id
             LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(
                ':' . $name,
                $value,
                is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR
            );
        }
        $statement->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $pageSize, \PDO::PARAM_INT);
        $statement->execute();

        $summary = $database->query(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN u.estado = \'activo\' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN u.estado = \'inactivo\' THEN 1 ELSE 0 END) AS inactive,
                    SUM(CASE WHEN na.nome = \'Escola\' THEN 1 ELSE 0 END) AS school_accounts'
            . $from
            . ($canManageRoot ? '' : " WHERE na.nome <> 'Root'")
        )->fetch() ?: ['total' => 0, 'active' => 0, 'inactive' => 0, 'school_accounts' => 0];

        $formValues = array_merge([
            'id' => '',
            'nome' => '',
            'email' => '',
            'email_suffix' => '',
            'telefone' => '',
            'nivel_acesso_id' => $defaultRoleId,
            'distrito' => '',
            'escola_id' => ''
        ], $formValues);
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $flash = $_SESSION['_users_flash'] ?? null;
        unset($_SESSION['_users_flash']);

        $userPageData = [
            'adminPath' => $this->basePath() . '/admin',
            'publicPath' => $this->basePath() . '/public',
            'csrfToken' => $_SESSION['csrf_token'],
            'users' => $statement->fetchAll(),
            'currentUserId' => (int) $user['id'],
            'roles' => $roles,
            'roleIds' => $roleIds,
            'roleNamesById' => $roleNamesById,
            'schools' => $schools,
            'districts' => $districts,
            'summary' => $summary,
            'defaultRoleId' => $defaultRoleId,
            'totalUsers' => $totalUsers,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => [
                'q' => $search,
                'distrito' => $districtFilter,
                'escola_id' => $schoolFilter,
                'nivel_acesso_id' => $roleFilter,
                'estado' => $statusFilter
            ],
            'formValues' => $formValues,
            'errors' => $errors,
            'flash' => $flash,
            'canManageRoot' => $canManageRoot
        ];

        ob_start();
        $this->view('admin/users/index', $userPageData);
        $content = ob_get_clean();
        $this->renderAdminLayout($user, 'Utilizadores', 'users', $content);
    }

    private function renderAcademic(array $user): void
    {
        $database = Database::connect();
        $readFilter = static function (string $key): string {
            $value = $_GET[$key] ?? '';
            return is_string($value) ? trim($value) : '';
        };

        $districtFilter = $readFilter('distrito');
        $schoolFilter = $readFilter('escola_id');
        $schoolId = $schoolFilter === ''
            ? false
            : filter_var($schoolFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $schoolFilter = $schoolId === false ? '' : (string) $schoolId;

        $yearFilter = $readFilter('ano_id');
        $yearId = $yearFilter === ''
            ? false
            : filter_var($yearFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $yearFilter = $yearId === false ? '' : (string) $yearId;

        $levelFilter = $readFilter('nivel_id');
        $levelId = $levelFilter === ''
            ? false
            : filter_var($levelFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $levelFilter = $levelId === false ? '' : (string) $levelId;

        $classFilter = $readFilter('classe_id');
        $classId = $classFilter === ''
            ? false
            : filter_var($classFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $classFilter = $classId === false ? '' : (string) $classId;

        $statusFilter = $readFilter('estado');
        if (!in_array($statusFilter, ['activa', 'inactiva'], true)) {
            $statusFilter = '';
        }

        $pageValue = $readFilter('pagina');
        $page = $pageValue === ''
            ? 1
            : filter_var($pageValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $page = $page === false ? 1 : $page;
        $pageSize = 25;

        $levels = $database->query(
            'SELECT id, codigo, nome FROM niveis_ensino ORDER BY nome'
        )->fetchAll();
        $years = $database->query(
            'SELECT id, ano, data_inicio, data_fim, estado
             FROM anos_lectivos ORDER BY ano DESC'
        )->fetchAll();
        $schools = $database->query(
            'SELECT id, nome, distrito, estado FROM escolas ORDER BY distrito, nome'
        )->fetchAll();
        $districts = $database->query(
            "SELECT DISTINCT distrito FROM escolas
             WHERE distrito <> '' ORDER BY distrito"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $classes = $database->query(
            'SELECT c.id, c.nivel_ensino_id, c.codigo, c.nome, c.ordem, c.estado,
                    ne.codigo AS nivel_codigo, ne.nome AS nivel_nome,
                    GROUP_CONCAT(d.nome ORDER BY d.nome SEPARATOR \', \') AS disciplinas,
                    GROUP_CONCAT(cd.disciplina_id ORDER BY cd.disciplina_id SEPARATOR \',\')
                        AS disciplina_ids
             FROM classes c
             INNER JOIN niveis_ensino ne ON ne.id = c.nivel_ensino_id
             LEFT JOIN classes_disciplinas cd ON cd.classe_id = c.id
             LEFT JOIN disciplinas d ON d.id = cd.disciplina_id
             GROUP BY c.id, ne.id
             ORDER BY ne.nome, c.ordem, c.nome'
        )->fetchAll();
        $disciplines = $database->query(
            'SELECT d.id, d.nivel_ensino_id, d.codigo, d.nome, d.estado,
                    ne.nome AS nivel_nome, COUNT(cd.id) AS total_classes
             FROM disciplinas d
             INNER JOIN niveis_ensino ne ON ne.id = d.nivel_ensino_id
             LEFT JOIN classes_disciplinas cd ON cd.disciplina_id = d.id
             GROUP BY d.id, ne.id
             ORDER BY ne.nome, d.nome'
        )->fetchAll();

        $turmaConditions = [];
        $turmaParameters = [];
        if ($districtFilter !== '') {
            $turmaConditions[] = 'e.distrito = :distrito';
            $turmaParameters['distrito'] = $districtFilter;
        }
        if ($schoolFilter !== '') {
            $turmaConditions[] = 'e.id = :escola_id';
            $turmaParameters['escola_id'] = (int) $schoolFilter;
        }
        if ($yearFilter !== '') {
            $turmaConditions[] = 't.ano_lectivo_id = :ano_id';
            $turmaParameters['ano_id'] = (int) $yearFilter;
        }
        if ($levelFilter !== '') {
            $turmaConditions[] = 'c.nivel_ensino_id = :nivel_id';
            $turmaParameters['nivel_id'] = (int) $levelFilter;
        }
        if ($classFilter !== '') {
            $turmaConditions[] = 't.classe_id = :classe_id';
            $turmaParameters['classe_id'] = (int) $classFilter;
        }
        if ($statusFilter !== '') {
            $turmaConditions[] = 't.estado = :estado';
            $turmaParameters['estado'] = $statusFilter;
        }
        $turmaWhere = $turmaConditions === []
            ? ''
            : ' WHERE ' . implode(' AND ', $turmaConditions);
        $turmaFrom = '
            FROM turmas t
            INNER JOIN escolas e ON e.id = t.escola_id
            INNER JOIN anos_lectivos al ON al.id = t.ano_lectivo_id
            INNER JOIN classes c ON c.id = t.classe_id
            INNER JOIN niveis_ensino ne ON ne.id = c.nivel_ensino_id
            LEFT JOIN (
                SELECT turma_id, COUNT(*) AS matriculas_activas
                FROM matriculas
                WHERE estado = \'activa\'
                GROUP BY turma_id
            ) mt ON mt.turma_id = t.id';

        $countTurmas = $database->prepare('SELECT COUNT(*)' . $turmaFrom . $turmaWhere);
        $countTurmas->execute($turmaParameters);
        $totalTurmas = (int) $countTurmas->fetchColumn();
        $totalPages = max(1, (int) ceil($totalTurmas / $pageSize));
        $page = min($page, $totalPages);

        $turmaStatement = $database->prepare(
            'SELECT t.id, t.identificacao, t.turno, t.capacidade, t.estado,
                    e.nome AS escola_nome, e.distrito, al.ano,
                    c.nome AS classe_nome, ne.nome AS nivel_nome,
                    COALESCE(mt.matriculas_activas, 0) AS matriculas_activas'
            . $turmaFrom . $turmaWhere . '
             ORDER BY e.distrito, e.nome, al.ano DESC, c.ordem, t.identificacao
             LIMIT :limit OFFSET :offset'
        );
        foreach ($turmaParameters as $name => $value) {
            $turmaStatement->bindValue(
                ':' . $name,
                $value,
                is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR
            );
        }
        $turmaStatement->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
        $turmaStatement->bindValue(':offset', ($page - 1) * $pageSize, \PDO::PARAM_INT);
        $turmaStatement->execute();

        $summary = [
            'years' => (int) $database->query(
                'SELECT COUNT(*) FROM anos_lectivos WHERE estado = \'activo\''
            )->fetchColumn(),
            'classes' => (int) $database->query(
                'SELECT COUNT(*) FROM classes WHERE estado = \'activa\''
            )->fetchColumn(),
            'disciplines' => (int) $database->query(
                'SELECT COUNT(*) FROM disciplinas WHERE estado = \'activa\''
            )->fetchColumn(),
            'turmas' => $totalTurmas
        ];

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $flash = $_SESSION['_academic_flash'] ?? null;
        unset($_SESSION['_academic_flash']);

        $academicPageData = [
            'adminPath' => $this->basePath() . '/admin',
            'publicPath' => $this->basePath() . '/public',
            'csrfToken' => $_SESSION['csrf_token'],
            'levels' => $levels,
            'years' => $years,
            'schools' => $schools,
            'districts' => $districts,
            'classes' => $classes,
            'disciplines' => $disciplines,
            'turmas' => $turmaStatement->fetchAll(),
            'summary' => $summary,
            'totalTurmas' => $totalTurmas,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => [
                'distrito' => $districtFilter,
                'escola_id' => $schoolFilter,
                'ano_id' => $yearFilter,
                'nivel_id' => $levelFilter,
                'classe_id' => $classFilter,
                'estado' => $statusFilter
            ],
            'flash' => $flash
        ];

        ob_start();
        $this->view('admin/academic/index', $academicPageData);
        $content = ob_get_clean();
        $this->renderAdminLayout($user, 'Gestão académica', 'academic', $content);
    }

    private function isValidAcademicDate(string $date): bool
    {
        if ($date === '') {
            return true;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    private function renderEnrollments(
        array $user,
        array $formValues = [],
        array $errors = []
    ): void {
        $database = Database::connect();
        $searchValue = $_GET['q'] ?? '';
        $search = is_string($searchValue) ? trim($searchValue) : '';
        $statusValue = $_GET['estado'] ?? 'todas';
        $status = is_string($statusValue) ? $statusValue : 'todas';
        if (!in_array($status, ['todas', 'activa', 'cancelada'], true)) {
            $status = 'todas';
        }

        $yearFilterValue = $_GET['ano_id'] ?? '';
        $yearFilter = is_string($yearFilterValue)
            ? filter_var(
                $yearFilterValue,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
            : false;
        $yearFilter = $yearFilter === false ? '' : (string) $yearFilter;
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
            $conditions[] = '(CAST(m.numero_processo AS CHAR) LIKE :processo
                OR a.codigo_central LIKE :codigo
                OR a.nome_completo LIKE :nome
                OR e.nome LIKE :escola)';
            $pattern = '%' . $search . '%';
            $parameters['processo'] = $pattern;
            $parameters['codigo'] = $pattern;
            $parameters['nome'] = $pattern;
            $parameters['escola'] = $pattern;
        }
        if ($status !== 'todas') {
            $conditions[] = 'm.estado = :estado';
            $parameters['estado'] = $status;
        }
        if ($yearFilter !== '') {
            $conditions[] = 'm.ano_lectivo_id = :ano_id';
            $parameters['ano_id'] = (int) $yearFilter;
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $from = '
            FROM matriculas m
            INNER JOIN aluno_escolas ae ON ae.id = m.aluno_escola_id
            INNER JOIN alunos a ON a.id = ae.aluno_id
            INNER JOIN escolas e ON e.id = m.escola_id
            INNER JOIN anos_lectivos al ON al.id = m.ano_lectivo_id
            INNER JOIN turmas t ON t.id = m.turma_id
            INNER JOIN classes c ON c.id = m.classe_id';

        $count = $database->prepare('SELECT COUNT(*)' . $from . $where);
        $count->execute($parameters);
        $totalEnrollments = (int) $count->fetchColumn();
        $totalPages = max(1, (int) ceil($totalEnrollments / $pageSize));
        $page = min($page, $totalPages);

        $statement = $database->prepare(
            'SELECT m.id, m.numero_processo, m.data_matricula, m.estado,
                    a.nome_completo, a.codigo_central,
                    e.nome AS escola_nome, e.distrito,
                    al.ano, t.identificacao, t.turno, c.nome AS classe_nome'
            . $from . $where . '
             ORDER BY m.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(
                ':' . $name,
                $value,
                is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR
            );
        }
        $statement->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $pageSize, \PDO::PARAM_INT);
        $statement->execute();

        $students = $database->query(
            "SELECT a.id, a.nome_completo, a.codigo_central,
                    e.id AS escola_id,
                    COALESCE(am.matriculated_year_ids, '') AS matriculated_year_ids
             FROM alunos a
             INNER JOIN aluno_escolas ae ON ae.id = (
                 SELECT ae2.id
                 FROM aluno_escolas ae2
                 WHERE ae2.aluno_id = a.id
                   AND ae2.estado = 'activo'
                 ORDER BY ae2.data_entrada DESC, ae2.id DESC
                 LIMIT 1
             )
             INNER JOIN escolas e ON e.id = ae.escola_id
             LEFT JOIN (
                 SELECT ae2.aluno_id,
                        GROUP_CONCAT(
                            DISTINCT m.ano_lectivo_id
                            ORDER BY m.ano_lectivo_id
                            SEPARATOR ','
                        ) AS matriculated_year_ids
                 FROM matriculas m
                 INNER JOIN aluno_escolas ae2 ON ae2.id = m.aluno_escola_id
                 WHERE m.estado = 'activa'
                 GROUP BY ae2.aluno_id
             ) am ON am.aluno_id = a.id
             WHERE a.estado = 'activo'
               AND e.estado = 'activa'
             ORDER BY a.nome_completo, a.codigo_central"
        )->fetchAll();

        $schools = $database->query(
            "SELECT DISTINCT e.id, e.nome, e.distrito,
                    CASE e.tipo
                        WHEN 'Primaria' THEN 'PRI'
                        WHEN 'Secundaria' THEN 'SEC'
                        ELSE ''
                    END AS nivel_codigo
             FROM alunos a
             INNER JOIN aluno_escolas ae ON ae.id = (
                 SELECT ae2.id
                 FROM aluno_escolas ae2
                 WHERE ae2.aluno_id = a.id
                   AND ae2.estado = 'activo'
                 ORDER BY ae2.data_entrada DESC, ae2.id DESC
                 LIMIT 1
             )
             INNER JOIN escolas e ON e.id = ae.escola_id
             WHERE a.estado = 'activo'
               AND e.estado = 'activa'
             ORDER BY e.distrito, e.nome"
        )->fetchAll();
        $districts = [];
        foreach ($schools as $school) {
            $districts[$school['distrito']] = $school['distrito'];
        }

        $activeYears = $database->query(
            "SELECT id, ano FROM anos_lectivos
             WHERE estado = 'activo'
             ORDER BY ano DESC"
        )->fetchAll();
        $years = $database->query(
            'SELECT id, ano FROM anos_lectivos ORDER BY ano DESC'
        )->fetchAll();

        $classes = $database->query(
            "SELECT c.id, c.nome, ne.codigo AS nivel_codigo
             FROM classes c
             INNER JOIN niveis_ensino ne ON ne.id = c.nivel_ensino_id
             WHERE c.estado = 'activa'
             ORDER BY ne.codigo, c.ordem"
        )->fetchAll();

        $formValues = array_merge([
            'distrito' => '',
            'escola_id' => '',
            'aluno_id' => '',
            'ano_lectivo_id' => (string) ($activeYears[0]['id'] ?? ''),
            'classe_id' => ''
        ], $formValues);
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $flash = $_SESSION['_enrollment_flash'] ?? null;
        unset($_SESSION['_enrollment_flash']);

        $enrollmentPageData = [
            'adminPath' => $this->basePath() . '/admin',
            'enrollments' => $statement->fetchAll(),
            'students' => $students,
            'schools' => $schools,
            'districts' => $districts,
            'activeYears' => $activeYears,
            'years' => $years,
            'classes' => $classes,
            'maxStudentsPerClassroom' => self::MAX_STUDENTS_PER_TURMA,
            'totalEnrollments' => $totalEnrollments,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'statusFilter' => $status,
            'yearFilter' => $yearFilter,
            'csrfToken' => $_SESSION['csrf_token'],
            'formValues' => $formValues,
            'errors' => $errors,
            'flash' => $flash
        ];

        ob_start();
        $this->view('admin/enrollment/index', $enrollmentPageData);
        $content = ob_get_clean();

        $this->renderAdminLayout($user, 'Matrículas', 'enrollment', $content);
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
