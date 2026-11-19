SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'classes'
       AND COLUMN_NAME = 'tem_exame') = 0,
    'ALTER TABLE classes ADD COLUMN tem_exame TINYINT(1) NOT NULL DEFAULT 0 AFTER ordem',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;
