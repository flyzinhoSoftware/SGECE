SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'escolas'
       AND COLUMN_NAME = 'nome_chefe_secretaria') = 0,
    'ALTER TABLE escolas ADD COLUMN nome_chefe_secretaria VARCHAR(180) NULL AFTER nome_director',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;
