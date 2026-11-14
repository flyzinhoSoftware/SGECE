USE sgece_db;

SET @sgece_drop_nuit_sql = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE escolas DROP COLUMN nuit',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'escolas'
      AND COLUMN_NAME = 'nuit'
);
PREPARE sgece_drop_nuit_statement FROM @sgece_drop_nuit_sql;
EXECUTE sgece_drop_nuit_statement;
DEALLOCATE PREPARE sgece_drop_nuit_statement;
