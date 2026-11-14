<?php

declare(strict_types=1);

use Config\Database;
use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

Dotenv::createImmutable($projectRoot)->load();

$name = trim((string) getenv('SGECE_ADMIN_NAME'));
$email = trim((string) getenv('SGECE_ADMIN_EMAIL'));
$password = (string) getenv('SGECE_ADMIN_PASSWORD');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Informe um nome e um endereço de e-mail válido.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "A palavra-passe deve ter pelo menos 12 caracteres.\n");
    exit(1);
}

$database = Database::connect();
$existingAdmin = $database->query(
    "SELECT u.id
     FROM usuarios u
     INNER JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
     WHERE u.estado = 'activo'
       AND na.nome IN ('Root', 'Admin')
     LIMIT 1"
)->fetchColumn();

if ($existingAdmin !== false) {
    fwrite(STDERR, "Já existe uma conta administrativa activa.\n");
    exit(1);
}

$levelStatement = $database->prepare(
    "SELECT id
     FROM niveis_acesso
     WHERE nome = 'Admin'
     LIMIT 1"
);
$levelStatement->execute();
$levelId = $levelStatement->fetchColumn();

if ($levelId === false) {
    throw new RuntimeException(
        'O nível Admin não existe. Execute primeiro a migração da base de dados.'
    );
}

$insert = $database->prepare(
    "INSERT INTO usuarios
        (nivel_acesso_id, nome, email, palavra_passe, estado)
     VALUES
        (:nivel_acesso_id, :nome, :email, :palavra_passe, 'activo')"
);
$insert->execute([
    'nivel_acesso_id' => $levelId,
    'nome' => $name,
    'email' => $email,
    'palavra_passe' => password_hash($password, PASSWORD_DEFAULT)
]);

fwrite(STDOUT, "Conta administrativa criada. Já pode iniciar sessão em /admin.\n");
