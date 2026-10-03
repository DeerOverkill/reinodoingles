-- ============================================================
--  C.R.I. Idiomas — Script de criação do banco de leads
--  Execute este arquivo no seu servidor MySQL/MariaDB
--  Exemplo: mysql -u root -p < leads.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS reinodoingles
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE reinodoingles;

CREATE TABLE IF NOT EXISTS leads (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(150)    NOT NULL,
    email       VARCHAR(200)    NOT NULL,
    whatsapp    VARCHAR(20)     NOT NULL,
    origem      VARCHAR(60)     NOT NULL DEFAULT 'guia-gratis',
    ip_origem   VARCHAR(45)     DEFAULT NULL,
    criado_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_email     (email),
    INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuário dedicado (opcional, recomendado para produção)
-- Substitua 'senha_segura' por uma senha forte antes de executar
-- CREATE USER IF NOT EXISTS 'cri_app'@'localhost' IDENTIFIED BY 'senha_segura';
-- GRANT SELECT, INSERT ON reinodoingles.leads TO 'cri_app'@'localhost';
-- FLUSH PRIVILEGES;
