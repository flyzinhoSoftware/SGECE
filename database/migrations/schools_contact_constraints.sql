USE sgece_db;

ALTER TABLE escolas
    MODIFY COLUMN endereco VARCHAR(255) NOT NULL,
    MODIFY COLUMN telefone VARCHAR(30) NOT NULL,
    MODIFY COLUMN email VARCHAR(150) NOT NULL,
    ADD CONSTRAINT uq_escolas_email UNIQUE (email),
    ADD CONSTRAINT uq_escolas_telefone UNIQUE (telefone),
    ADD CONSTRAINT uq_escolas_endereco UNIQUE (endereco);
