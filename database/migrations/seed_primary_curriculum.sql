START TRANSACTION;

INSERT INTO classes
    (nivel_ensino_id, codigo, nome, ordem, tem_exame, estado)
SELECT
    ne.id, curriculum.codigo, curriculum.nome, curriculum.ordem,
    IF(curriculum.codigo = 'PRI-06', 1, 0), 'activa'
FROM niveis_ensino ne
CROSS JOIN (
    SELECT 'PRI-01' AS codigo, '1.ª Classe' AS nome, 1 AS ordem
    UNION ALL SELECT 'PRI-02', '2.ª Classe', 2
    UNION ALL SELECT 'PRI-03', '3.ª Classe', 3
    UNION ALL SELECT 'PRI-04', '4.ª Classe', 4
    UNION ALL SELECT 'PRI-05', '5.ª Classe', 5
    UNION ALL SELECT 'PRI-06', '6.ª Classe', 6
) curriculum
WHERE ne.codigo = 'PRI'
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    ordem = VALUES(ordem),
    tem_exame = VALUES(tem_exame);

INSERT INTO disciplinas
    (nivel_ensino_id, codigo, nome, estado)
SELECT
    ne.id, curriculum.codigo, curriculum.nome, 'activa'
FROM niveis_ensino ne
CROSS JOIN (
    SELECT 'LP-PRI' AS codigo, 'Língua Portuguesa' AS nome
    UNION ALL SELECT 'LM-PRI', 'Língua Moçambicana'
    UNION ALL SELECT 'MAT-PRI', 'Matemática'
    UNION ALL SELECT 'EF-PRI', 'Educação Física'
    UNION ALL SELECT 'CN-PRI', 'Ciências Naturais'
    UNION ALL SELECT 'CS-PRI', 'Ciências Sociais'
    UNION ALL SELECT 'EVO-PRI', 'Educação Visual e Ofícios'
) curriculum
WHERE ne.codigo = 'PRI'
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome);

INSERT IGNORE INTO classes_disciplinas (classe_id, disciplina_id)
SELECT c.id, d.id
FROM classes c
INNER JOIN disciplinas d
    ON d.nivel_ensino_id = c.nivel_ensino_id
INNER JOIN niveis_ensino ne
    ON ne.id = c.nivel_ensino_id
WHERE ne.codigo = 'PRI'
  AND (
      (
          c.codigo IN ('PRI-01', 'PRI-02', 'PRI-03', 'PRI-04', 'PRI-05', 'PRI-06')
          AND d.codigo IN ('LP-PRI', 'LM-PRI', 'MAT-PRI', 'EF-PRI', 'EVO-PRI')
      )
      OR (
          c.codigo IN ('PRI-04', 'PRI-05', 'PRI-06')
          AND d.codigo IN ('CN-PRI', 'CS-PRI')
      )
  );

COMMIT;
