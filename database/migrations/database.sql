
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
    codigo VARCHAR(30) NOT NULL UNIQUE,
    nome VARCHAR(180) NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    provincia VARCHAR(100) NOT NULL,
    distrito VARCHAR(100) NOT NULL,
    localidade VARCHAR(120) NULL,
    endereco VARCHAR(255) NULL,
    nuit VARCHAR(30) NULL,
    telefone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'activa',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
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
    sexo VARCHAR(20) NOT NULL,

    -- BI cifrado pela aplicacao
    bi_cifrado VARBINARY(512) NOT NULL,

    -- HMAC-SHA256 do BI para deteccao de duplicados
    bi_hash CHAR(64) NOT NULL UNIQUE,

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

    CONSTRAINT fk_matricula_turma
        FOREIGN KEY (turma_id)
        REFERENCES turmas(id),

    CONSTRAINT fk_matricula_ano
        FOREIGN KEY (ano_lectivo_id)
        REFERENCES anos_lectivos(id),

    UNIQUE KEY uk_processo_escola_ano
        (escola_id, ano_lectivo_id, numero_processo),

    INDEX idx_matricula_aluno (aluno_escola_id),
    INDEX idx_matricula_turma (turma_id),
    INDEX idx_matricula_ano (ano_lectivo_id)
) ENGINE=InnoDB;

-- =====================================================
-- FIM DA PRIMEIRA ESTRUTURA
-- =====================================================
