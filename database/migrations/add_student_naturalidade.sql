SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'alunos'
       AND COLUMN_NAME = 'naturalidade') = 0,
    'ALTER TABLE alunos ADD COLUMN naturalidade VARCHAR(180) NULL AFTER data_nascimento',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;
