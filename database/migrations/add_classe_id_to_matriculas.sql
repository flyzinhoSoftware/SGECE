USE sgece_db;

ALTER TABLE matriculas
    ADD COLUMN classe_id BIGINT UNSIGNED NULL AFTER escola_id;

UPDATE matriculas m
INNER JOIN turmas t ON t.id = m.turma_id
SET m.classe_id = t.classe_id
WHERE m.classe_id IS NULL
  AND m.id > 0;

ALTER TABLE matriculas
    MODIFY COLUMN classe_id BIGINT UNSIGNED NOT NULL,
    ADD INDEX idx_matricula_classe (classe_id);

ALTER TABLE matriculas
    ADD CONSTRAINT fk_matricula_classe
        FOREIGN KEY (classe_id)
        REFERENCES classes(id);
