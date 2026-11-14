START TRANSACTION;

INSERT INTO classes
    (nivel_ensino_id, codigo, nome, ordem, estado)
SELECT
    ne.id, curriculum.codigo, curriculum.nome, curriculum.ordem, 'activa'
FROM niveis_ensino ne
CROSS JOIN (
    SELECT 'SEC-07' AS codigo, '7.ª Classe' AS nome, 7 AS ordem
    UNION ALL SELECT 'SEC-08', '8.ª Classe', 8
    UNION ALL SELECT 'SEC-09', '9.ª Classe', 9
    UNION ALL SELECT 'SEC-10', '10.ª Classe', 10
    UNION ALL SELECT 'SEC-11', '11.ª Classe', 11
    UNION ALL SELECT 'SEC-12', '12.ª Classe', 12
) curriculum
WHERE ne.codigo = 'SEC'
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    ordem = VALUES(ordem);

INSERT INTO disciplinas
    (nivel_ensino_id, codigo, nome, estado)
SELECT
    ne.id, curriculum.codigo, curriculum.nome, 'activa'
FROM niveis_ensino ne
CROSS JOIN (
    SELECT 'POR-SEC' AS codigo, 'Português' AS nome
    UNION ALL SELECT 'ING-SEC', 'Inglês'
    UNION ALL SELECT 'FRA-SEC', 'Francês'
    UNION ALL SELECT 'HIS-SEC', 'História'
    UNION ALL SELECT 'GEO-SEC', 'Geografia'
    UNION ALL SELECT 'MAT-SEC', 'Matemática'
    UNION ALL SELECT 'BIO-SEC', 'Biologia'
    UNION ALL SELECT 'QUI-SEC', 'Química'
    UNION ALL SELECT 'FIS-SEC', 'Física'
    UNION ALL SELECT 'AGR-SEC', 'Agro-pecuária'
    UNION ALL SELECT 'TIC-SEC', 'Tecnologias de Informação e Comunicação (TIC)'
    UNION ALL SELECT 'EV-SEC', 'Educação Visual'
    UNION ALL SELECT 'EF-SEC', 'Educação Física'
    UNION ALL SELECT 'FIL-SEC', 'Filosofia'
    UNION ALL SELECT 'GD-SEC', 'Geometria Descritiva'
) curriculum
WHERE ne.codigo = 'SEC'
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome);

INSERT IGNORE INTO classes_disciplinas (classe_id, disciplina_id)
SELECT c.id, d.id
FROM classes c
INNER JOIN disciplinas d
    ON d.nivel_ensino_id = c.nivel_ensino_id
INNER JOIN niveis_ensino ne
    ON ne.id = c.nivel_ensino_id
WHERE ne.codigo = 'SEC'
  AND (
      (
          c.codigo = 'SEC-07'
          AND d.codigo IN (
              'POR-SEC', 'ING-SEC', 'HIS-SEC', 'GEO-SEC', 'MAT-SEC',
              'BIO-SEC', 'AGR-SEC', 'TIC-SEC', 'EV-SEC', 'EF-SEC'
          )
      )
      OR (
          c.codigo IN ('SEC-08', 'SEC-09')
          AND d.codigo IN (
              'POR-SEC', 'ING-SEC', 'FRA-SEC', 'HIS-SEC', 'GEO-SEC',
              'MAT-SEC', 'BIO-SEC', 'QUI-SEC', 'FIS-SEC', 'AGR-SEC',
              'TIC-SEC', 'EV-SEC', 'EF-SEC'
          )
      )
      OR (
          c.codigo IN ('SEC-10', 'SEC-11', 'SEC-12')
          AND d.codigo IN (
              'POR-SEC', 'ING-SEC', 'MAT-SEC', 'EF-SEC', 'TIC-SEC',
              'GEO-SEC', 'HIS-SEC', 'FRA-SEC', 'FIL-SEC', 'BIO-SEC',
              'QUI-SEC', 'FIS-SEC', 'EV-SEC', 'GD-SEC'
          )
      )
  );

COMMIT;
