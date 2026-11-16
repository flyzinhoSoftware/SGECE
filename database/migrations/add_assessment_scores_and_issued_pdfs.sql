ALTER TABLE notas_trimestrais
    MODIFY COLUMN nota DECIMAL(4,2) NULL;

SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'notas_trimestrais'
       AND COLUMN_NAME = 'acs1') = 0,
    'ALTER TABLE notas_trimestrais ADD COLUMN acs1 DECIMAL(4,2) NULL AFTER trimestre',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;

SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'notas_trimestrais'
       AND COLUMN_NAME = 'acs2') = 0,
    'ALTER TABLE notas_trimestrais ADD COLUMN acs2 DECIMAL(4,2) NULL AFTER acs1',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;

SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'notas_trimestrais'
       AND COLUMN_NAME = 'avaliacao_provincial') = 0,
    'ALTER TABLE notas_trimestrais ADD COLUMN avaliacao_provincial DECIMAL(4,2) NULL AFTER acs2',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;

SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pedidos_documentos'
       AND COLUMN_NAME = 'conteudo_documento') = 0,
    'ALTER TABLE pedidos_documentos ADD COLUMN conteudo_documento TEXT NULL AFTER resposta_admin',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;

SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pedidos_documentos'
       AND COLUMN_NAME = 'codigo_validacao') = 0,
    'ALTER TABLE pedidos_documentos ADD COLUMN codigo_validacao VARCHAR(48) NULL AFTER conteudo_documento',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;

SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pedidos_documentos'
       AND COLUMN_NAME = 'documento_pdf') = 0,
    'ALTER TABLE pedidos_documentos ADD COLUMN documento_pdf MEDIUMBLOB NULL AFTER codigo_validacao',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;

SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pedidos_documentos'
       AND COLUMN_NAME = 'emitido_em') = 0,
    'ALTER TABLE pedidos_documentos ADD COLUMN emitido_em DATETIME NULL AFTER documento_pdf',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;

SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pedidos_documentos'
       AND INDEX_NAME = 'uq_pedido_documento_codigo_validacao') = 0,
    'CREATE UNIQUE INDEX uq_pedido_documento_codigo_validacao ON pedidos_documentos (codigo_validacao)',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;
