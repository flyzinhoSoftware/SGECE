
-- =====================================================
-- SGECE
-- FASE 2 - ESTRUTURA INSTITUCIONAL E ACADEMICA
-- MySQL / MariaDB
-- =====================================================

CREATE DATABASE IF NOT EXISTS sgece_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE sgece_db;

-- =====================================================
-- 1. NIVEIS DE ACESSO
-- =====================================================

CREATE TABLE IF NOT EXISTS niveis_acesso (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    descricao VARCHAR(255) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO niveis_acesso (nome, descricao) VALUES
('Root', 'Administrador geral da plataforma'),
('Admin', 'Administrador institucional'),
('Escola', 'Utilizador associado a uma escola');

-- =====================================================
-- 2. ESCOLAS
-- =====================================================

CREATE TABLE IF NOT EXISTS escolas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(120) NOT NULL UNIQUE,
    nome VARCHAR(180) NOT NULL,
    nome_director VARCHAR(180) NULL,
    nome_chefe_secretaria VARCHAR(180) NULL,
    tipo VARCHAR(30) NOT NULL,
    provincia VARCHAR(100) NOT NULL,
    distrito VARCHAR(100) NOT NULL,
    localidade VARCHAR(120) NULL,
    endereco VARCHAR(255) NOT NULL,
    telefone VARCHAR(30) NOT NULL,
    email VARCHAR(150) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'activa',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_escolas_email UNIQUE (email),
    CONSTRAINT uq_escolas_telefone UNIQUE (telefone),
    CONSTRAINT uq_escolas_endereco UNIQUE (endereco)
) ENGINE=InnoDB;

-- =====================================================
-- 3. UTILIZADORES
-- =====================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    escola_id BIGINT UNSIGNED NULL,
    nivel_acesso_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telefone VARCHAR(30) NULL,
    palavra_passe VARCHAR(255) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'activo',
    ultimo_acesso DATETIME NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuario_escola
        FOREIGN KEY (escola_id)
        REFERENCES escolas(id),

    CONSTRAINT fk_usuario_nivel
        FOREIGN KEY (nivel_acesso_id)
        REFERENCES niveis_acesso(id),

    INDEX idx_usuario_escola (escola_id),
    INDEX idx_usuario_nivel (nivel_acesso_id)
) ENGINE=InnoDB;

-- =====================================================
-- 4. NIVEIS DE ENSINO
-- =====================================================

CREATE TABLE IF NOT EXISTS niveis_ensino (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao VARCHAR(255) NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO niveis_ensino (codigo, nome) VALUES
('PRI', 'Ensino Primario'),
('SEC', 'Ensino Secundario');

-- =====================================================
-- 5. CLASSES
-- =====================================================

CREATE TABLE IF NOT EXISTS classes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nivel_ensino_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(30) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    ordem INT UNSIGNED NOT NULL,
    tem_exame TINYINT(1) NOT NULL DEFAULT 0,
    estado VARCHAR(20) DEFAULT 'activa',

    CONSTRAINT fk_classe_nivel
        FOREIGN KEY (nivel_ensino_id)
        REFERENCES niveis_ensino(id),

    UNIQUE KEY uk_classe_codigo_nivel
        (nivel_ensino_id, codigo),

    INDEX idx_classe_nivel (nivel_ensino_id)
) ENGINE=InnoDB;

-- =====================================================
-- 6. ANOS LECTIVOS
-- =====================================================

CREATE TABLE IF NOT EXISTS anos_lectivos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ano INT UNSIGNED NOT NULL UNIQUE,
    data_inicio DATE NULL,
    data_fim DATE NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'planeado',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 7. TURMAS
-- =====================================================

CREATE TABLE IF NOT EXISTS turmas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    escola_id BIGINT UNSIGNED NOT NULL,
    classe_id BIGINT UNSIGNED NOT NULL,
    ano_lectivo_id BIGINT UNSIGNED NOT NULL,
    identificacao VARCHAR(50) NOT NULL,
    turno VARCHAR(30) NULL,
    capacidade INT UNSIGNED NULL,
    estado VARCHAR(20) DEFAULT 'activa',

    CONSTRAINT fk_turma_escola
        FOREIGN KEY (escola_id)
        REFERENCES escolas(id),

    CONSTRAINT fk_turma_classe
        FOREIGN KEY (classe_id)
        REFERENCES classes(id),

    CONSTRAINT fk_turma_ano
        FOREIGN KEY (ano_lectivo_id)
        REFERENCES anos_lectivos(id),

    UNIQUE KEY uk_turma_escola_ano
        (escola_id, ano_lectivo_id, classe_id, identificacao),

    INDEX idx_turma_escola (escola_id),
    INDEX idx_turma_ano (ano_lectivo_id)
) ENGINE=InnoDB;

-- =====================================================
-- 8. DISCIPLINAS
-- =====================================================

CREATE TABLE IF NOT EXISTS disciplinas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nivel_ensino_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(30) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    estado VARCHAR(20) DEFAULT 'activa',

    CONSTRAINT fk_disciplina_nivel
        FOREIGN KEY (nivel_ensino_id)
        REFERENCES niveis_ensino(id),

    UNIQUE KEY uk_disciplina_nivel_codigo
        (nivel_ensino_id, codigo)
) ENGINE=InnoDB;

-- =====================================================
-- 9. DISCIPLINAS POR CLASSE
-- =====================================================

CREATE TABLE IF NOT EXISTS classes_disciplinas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    classe_id BIGINT UNSIGNED NOT NULL,
    disciplina_id BIGINT UNSIGNED NOT NULL,

    CONSTRAINT fk_cd_classe
        FOREIGN KEY (classe_id)
        REFERENCES classes(id),

    CONSTRAINT fk_cd_disciplina
        FOREIGN KEY (disciplina_id)
        REFERENCES disciplinas(id),

    UNIQUE KEY uk_classe_disciplina
        (classe_id, disciplina_id)
) ENGINE=InnoDB;

-- =====================================================
-- 10. ALUNOS
-- =====================================================

CREATE TABLE IF NOT EXISTS alunos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_central VARCHAR(40) NOT NULL UNIQUE,
    nome_completo VARCHAR(180) NOT NULL,
    data_nascimento DATE NOT NULL,
    naturalidade VARCHAR(180) NULL,
    sexo VARCHAR(20) NOT NULL,

    -- BI cifrado pela aplicacao
    bi_cifrado VARBINARY(512) NOT NULL,

    -- HMAC-SHA256 do BI para deteccao de duplicados
    bi_hash CHAR(64) NOT NULL UNIQUE,
    data_emissao_bi DATE NULL,

    nome_pai VARCHAR(150) NULL,
    nome_mae VARCHAR(150) NULL,
    contacto VARCHAR(30) NULL,
    endereco VARCHAR(255) NULL,

    estado VARCHAR(20) DEFAULT 'activo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 11. ASSOCIACAO ALUNO - ESCOLA
-- =====================================================

CREATE TABLE IF NOT EXISTS aluno_escolas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id BIGINT UNSIGNED NOT NULL,
    escola_id BIGINT UNSIGNED NOT NULL,
    codigo_aluno_escola VARCHAR(50) NOT NULL UNIQUE,
    associacao_anterior_id BIGINT UNSIGNED NULL,
    data_entrada DATE NOT NULL,
    data_saida DATE NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'activo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ae_aluno
        FOREIGN KEY (aluno_id)
        REFERENCES alunos(id),

    CONSTRAINT fk_ae_escola
        FOREIGN KEY (escola_id)
        REFERENCES escolas(id),

    CONSTRAINT fk_ae_anterior
        FOREIGN KEY (associacao_anterior_id)
        REFERENCES aluno_escolas(id),

    INDEX idx_ae_aluno (aluno_id),
    INDEX idx_ae_escola (escola_id)
) ENGINE=InnoDB;

-- =====================================================
-- 12. SEQUENCIAS DE PROCESSOS DOCUMENTAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS sequencias_processos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    escola_id BIGINT UNSIGNED NOT NULL,
    ano_lectivo_id BIGINT UNSIGNED NOT NULL,
    ultimo_numero INT UNSIGNED NOT NULL DEFAULT 0,

    CONSTRAINT fk_seq_escola
        FOREIGN KEY (escola_id)
        REFERENCES escolas(id),

    CONSTRAINT fk_seq_ano
        FOREIGN KEY (ano_lectivo_id)
        REFERENCES anos_lectivos(id),

    UNIQUE KEY uk_seq_escola_ano
        (escola_id, ano_lectivo_id)
) ENGINE=InnoDB;

-- =====================================================
-- 13. MATRICULAS
-- =====================================================

CREATE TABLE IF NOT EXISTS matriculas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_escola_id BIGINT UNSIGNED NOT NULL,
    escola_id BIGINT UNSIGNED NOT NULL,
    classe_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NOT NULL,
    ano_lectivo_id BIGINT UNSIGNED NOT NULL,
    numero_processo INT UNSIGNED NOT NULL,
    data_matricula DATE NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'activa',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_matricula_aluno_escola
        FOREIGN KEY (aluno_escola_id)
        REFERENCES aluno_escolas(id),

    CONSTRAINT fk_matricula_escola
        FOREIGN KEY (escola_id)
        REFERENCES escolas(id),

    CONSTRAINT fk_matricula_classe
        FOREIGN KEY (classe_id)
        REFERENCES classes(id),

    CONSTRAINT fk_matricula_turma
        FOREIGN KEY (turma_id)
        REFERENCES turmas(id),

    CONSTRAINT fk_matricula_ano
        FOREIGN KEY (ano_lectivo_id)
        REFERENCES anos_lectivos(id),

    UNIQUE KEY uk_processo_escola_ano
        (escola_id, ano_lectivo_id, numero_processo),

    INDEX idx_matricula_aluno (aluno_escola_id),
    INDEX idx_matricula_classe (classe_id),
    INDEX idx_matricula_turma (turma_id),
    INDEX idx_matricula_ano (ano_lectivo_id)
) ENGINE=InnoDB;

-- =====================================================
-- 14. NOTAS TRIMESTRAIS E PEDIDOS DE DOCUMENTOS
-- =====================================================

CREATE TABLE IF NOT EXISTS notas_trimestrais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricula_id BIGINT UNSIGNED NOT NULL,
    disciplina_id BIGINT UNSIGNED NOT NULL,
    trimestre TINYINT UNSIGNED NOT NULL,
    acs1 DECIMAL(4,2) NULL,
    acs2 DECIMAL(4,2) NULL,
    avaliacao_provincial DECIMAL(4,2) NULL,
    nota DECIMAL(4,2) NULL,
    criado_por BIGINT UNSIGNED NOT NULL,
    actualizado_por BIGINT UNSIGNED NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_nota_matricula
        FOREIGN KEY (matricula_id)
        REFERENCES matriculas(id),
    CONSTRAINT fk_nota_disciplina
        FOREIGN KEY (disciplina_id)
        REFERENCES disciplinas(id),
    CONSTRAINT fk_nota_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES usuarios(id),
    CONSTRAINT fk_nota_actualizado_por
        FOREIGN KEY (actualizado_por)
        REFERENCES usuarios(id),

    UNIQUE KEY uk_nota_matricula_disciplina_trimestre
        (matricula_id, disciplina_id, trimestre),
    INDEX idx_nota_disciplina_trimestre (disciplina_id, trimestre)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos_documentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(32) NOT NULL UNIQUE,
    escola_id BIGINT UNSIGNED NOT NULL,
    aluno_escola_id BIGINT UNSIGNED NOT NULL,
    matricula_id BIGINT UNSIGNED NULL,
    tipo_documento VARCHAR(20) NOT NULL,
    motivo VARCHAR(500) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'pendente',
    solicitado_por BIGINT UNSIGNED NOT NULL,
    analisado_por BIGINT UNSIGNED NULL,
    resposta_admin VARCHAR(500) NULL,
    conteudo_documento TEXT NULL,
    codigo_validacao VARCHAR(48) NULL,
    documento_pdf MEDIUMBLOB NULL,
    emitido_em DATETIME NULL,
    solicitado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    concluido_em DATETIME NULL,

    CONSTRAINT fk_pedido_documento_escola
        FOREIGN KEY (escola_id)
        REFERENCES escolas(id),
    CONSTRAINT fk_pedido_documento_aluno_escola
        FOREIGN KEY (aluno_escola_id)
        REFERENCES aluno_escolas(id),
    CONSTRAINT fk_pedido_documento_matricula
        FOREIGN KEY (matricula_id)
        REFERENCES matriculas(id),
    CONSTRAINT fk_pedido_documento_solicitado_por
        FOREIGN KEY (solicitado_por)
        REFERENCES usuarios(id),
    CONSTRAINT fk_pedido_documento_analisado_por
        FOREIGN KEY (analisado_por)
        REFERENCES usuarios(id),

    INDEX idx_pedido_documento_escola_estado (escola_id, estado),
    INDEX idx_pedido_documento_tipo_estado (tipo_documento, estado),
    INDEX idx_pedido_documento_matricula (matricula_id),
    UNIQUE KEY uq_pedido_documento_codigo_validacao (codigo_validacao)
) ENGINE=InnoDB;

-- =====================================================
-- FIM DA PRIMEIRA ESTRUTURA
-- =====================================================

-- =====================================================
-- DADOS GERAIS DE TESTE
-- Os niveis de acesso e de ensino acima sao dados de referencia existentes.
-- A tabela usuarios e apenas actualizada; nenhum utilizador e criado aqui.
-- =====================================================

INSERT IGNORE INTO escolas
    (codigo, nome, tipo, provincia, distrito, localidade, endereco,
     telefone, email, estado)
VALUES
    ('SGECE-ESC-TESTE-01', 'Escola de Teste SGECE', 'Secundaria',
     'Maputo', 'Maputo', 'Localidade de Teste',
     'Endereço de Teste 01, Maputo', '841000001',
     'edts-maputo@sgece.gov.mz', 'activa');

UPDATE usuarios u
INNER JOIN escolas e
    ON e.codigo = 'SGECE-ESC-TESTE-01'
INNER JOIN niveis_acesso na
    ON na.nome = 'Admin'
SET
    u.escola_id = e.id,
    u.nivel_acesso_id = na.id,
    u.telefone = '841000002',
    u.estado = 'activo'
WHERE u.email = 'utilizador.teste@sgece.local';

INSERT IGNORE INTO anos_lectivos
    (ano, data_inicio, data_fim, estado)
VALUES
    (2026, '2026-01-01', '2026-12-31', 'activo');

INSERT INTO classes
    (nivel_ensino_id, codigo, nome, ordem, estado)
SELECT
    ne.id, 'TESTE-8A', '8a Classe', 8, 'activa'
FROM niveis_ensino ne
WHERE ne.codigo = 'SEC'
LIMIT 1;

INSERT IGNORE INTO disciplinas
    (nivel_ensino_id, codigo, nome, estado)
SELECT
    ne.id, 'MAT-TESTE', 'Matematica', 'activa'
FROM niveis_ensino ne
WHERE ne.codigo = 'SEC'
LIMIT 1;

INSERT IGNORE INTO classes_disciplinas
    (classe_id, disciplina_id)
SELECT
    c.id, d.id
FROM classes c
INNER JOIN disciplinas d
    ON d.codigo = 'MAT-TESTE'
INNER JOIN niveis_ensino ne
    ON ne.id = d.nivel_ensino_id
WHERE c.codigo = 'TESTE-8A'
  AND c.nivel_ensino_id = ne.id
LIMIT 1;

INSERT IGNORE INTO classes
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

INSERT INTO classes
    (nivel_ensino_id, codigo, nome, ordem, tem_exame, estado)
SELECT
    ne.id, curriculum.codigo, curriculum.nome, curriculum.ordem,
    IF(curriculum.codigo IN ('SEC-09', 'SEC-10', 'SEC-12'), 1, 0), 'activa'
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
    ordem = VALUES(ordem),
    tem_exame = VALUES(tem_exame);

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

INSERT IGNORE INTO alunos
    (codigo_central, nome_completo, data_nascimento, naturalidade, sexo,
     bi_cifrado, bi_hash, nome_pai, nome_mae, contacto, endereco, estado)
VALUES
    ('SGECE-ALUNO-TESTE-001', 'Aluno de Teste SGECE', '2012-05-10',
     'Maputo, Província de Maputo',
     'Feminino',
     UNHEX('7385e09d6a814e121e3acd35de6c2fa7fa44ecdf3687e6cfc19d1f689ec3a64185898fc8ce532e166f9a8b3f6e8aa086'),
     '323768fb6725179f3311f8c1af7e9dec1f6582649c374ad67e2d020d2a627f59',
     'Pai de Teste', 'Mae de Teste', '840000001',
     'Endereco ficticio para testes', 'activo');

UPDATE alunos
SET naturalidade = 'Maputo, Província de Maputo'
WHERE codigo_central = 'SGECE-ALUNO-TESTE-001'
  AND naturalidade IS NULL;

INSERT IGNORE INTO aluno_escolas
    (aluno_id, escola_id, codigo_aluno_escola, data_entrada, estado)
SELECT
    a.id, e.id, 'SGECE-ALUNO-TESTE-001', '2026-02-02', 'activo'
FROM alunos a
INNER JOIN escolas e
    ON e.codigo = 'SGECE-ESC-TESTE-01'
WHERE a.codigo_central = 'SGECE-ALUNO-TESTE-001'
LIMIT 1;

INSERT IGNORE INTO turmas
    (escola_id, classe_id, ano_lectivo_id, identificacao,
     turno, capacidade, estado)
SELECT
    e.id, c.id, al.id, '8A-Teste', 'Manha', 40, 'activa'
FROM escolas e
INNER JOIN classes c
    ON c.codigo = 'TESTE-8A'
INNER JOIN anos_lectivos al
    ON al.ano = 2026
WHERE e.codigo = 'SGECE-ESC-TESTE-01'
LIMIT 1;

INSERT IGNORE INTO sequencias_processos
    (escola_id, ano_lectivo_id, ultimo_numero)
SELECT
    e.id, al.id, 1
FROM escolas e
INNER JOIN anos_lectivos al
    ON al.ano = 2026
WHERE e.codigo = 'SGECE-ESC-TESTE-01'
LIMIT 1;

INSERT IGNORE INTO matriculas
    (aluno_escola_id, escola_id, classe_id, turma_id, ano_lectivo_id,
     numero_processo, data_matricula, estado)
SELECT
    ae.id, e.id, c.id, t.id, al.id, 1, '2026-02-02', 'activa'
FROM aluno_escolas ae
INNER JOIN alunos a
    ON a.id = ae.aluno_id
INNER JOIN escolas e
    ON e.id = ae.escola_id
INNER JOIN turmas t
    ON t.escola_id = e.id
   AND t.identificacao = '8A-Teste'
INNER JOIN classes c
    ON c.id = t.classe_id
INNER JOIN anos_lectivos al
    ON al.id = t.ano_lectivo_id
WHERE a.codigo_central = 'SGECE-ALUNO-TESTE-001'
  AND e.codigo = 'SGECE-ESC-TESTE-01'
  AND al.ano = 2026
LIMIT 1;
