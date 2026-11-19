SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'escolas'
       AND COLUMN_NAME = 'nome_director') = 0,
    'ALTER TABLE escolas ADD COLUMN nome_director VARCHAR(180) NULL AFTER nome',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;

SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pedidos_documentos'
       AND COLUMN_NAME = 'matricula_id') = 0,
    'ALTER TABLE pedidos_documentos ADD COLUMN matricula_id BIGINT UNSIGNED NULL AFTER aluno_escola_id',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;

SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pedidos_documentos'
       AND INDEX_NAME = 'idx_pedido_documento_matricula') = 0,
    'CREATE INDEX idx_pedido_documento_matricula ON pedidos_documentos (matricula_id)',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;

SET @sgece_alter_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pedidos_documentos'
       AND CONSTRAINT_NAME = 'fk_pedido_documento_matricula') = 0,
    'ALTER TABLE pedidos_documentos ADD CONSTRAINT fk_pedido_documento_matricula FOREIGN KEY (matricula_id) REFERENCES matriculas(id)',
    'SELECT 1'
);
PREPARE sgece_alter_statement FROM @sgece_alter_sql;
EXECUTE sgece_alter_statement;
DEALLOCATE PREPARE sgece_alter_statement;
