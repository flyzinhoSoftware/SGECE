SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'alunos'
       AND COLUMN_NAME = 'data_emissao_bi') = 0,
    'ALTER TABLE alunos ADD COLUMN data_emissao_bi DATE NULL AFTER bi_hash',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;
