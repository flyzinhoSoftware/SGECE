<?php

namespace App\Services;

use PDO;

final class StudentIdentity
{
    public static function generateCode(): string
    {
        return 'SGECE-ALU-' . strtoupper(bin2hex(random_bytes(12)));
    }

    public static function hash(string $identityNumber): string
    {
        $normalized = preg_replace('/\s+/u', '', trim($identityNumber));
        if ($normalized === null || $normalized === '') {
            throw new \InvalidArgumentException('O BI do aluno é inválido.');
        }

        return hash_hmac(
            'sha256',
            strtoupper($normalized),
            self::identityKey('hash')
        );
    }

    public static function encrypt(string $identityNumber): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt(
            trim($identityNumber),
            'aes-256-gcm',
            self::identityKey('encryption'),
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

    public static function exists(PDO $database, string $identityHash, int $excludeId = 0): bool
    {
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

    public static function associateWithSchool(
        PDO $database,
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

    private static function identityKey(string $purpose): string
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
}
