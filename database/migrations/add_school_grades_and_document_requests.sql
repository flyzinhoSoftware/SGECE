CREATE TABLE IF NOT EXISTS notas_trimestrais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricula_id BIGINT UNSIGNED NOT NULL,
    disciplina_id BIGINT UNSIGNED NOT NULL,
    trimestre TINYINT UNSIGNED NOT NULL,
    nota DECIMAL(4,2) NOT NULL,
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
    tipo_documento VARCHAR(20) NOT NULL,
    motivo VARCHAR(500) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'pendente',
    solicitado_por BIGINT UNSIGNED NOT NULL,
    analisado_por BIGINT UNSIGNED NULL,
    resposta_admin VARCHAR(500) NULL,
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
    CONSTRAINT fk_pedido_documento_solicitado_por
        FOREIGN KEY (solicitado_por)
        REFERENCES usuarios(id),
    CONSTRAINT fk_pedido_documento_analisado_por
        FOREIGN KEY (analisado_por)
        REFERENCES usuarios(id),

    INDEX idx_pedido_documento_escola_estado (escola_id, estado),
    INDEX idx_pedido_documento_tipo_estado (tipo_documento, estado)
) ENGINE=InnoDB;
