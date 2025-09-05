-- 00-schema.sql
-- MegaVote · schema principal
-- MySQL 8.0+ · utf8mb4

SET NAMES utf8mb4;
SET time_zone = "+00:00";

-- Ajuste global de charset (opcional por DB)
-- CREATE DATABASE IF NOT EXISTS poll_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE poll_app;

-- =========================
-- Tabela: users (admins)
-- =========================
CREATE TABLE IF NOT EXISTS users (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username    VARCHAR(64)     NOT NULL,
  email       VARCHAR(191)    DEFAULT NULL,
  password    VARCHAR(255)    NOT NULL,
  role        ENUM('master','admin','basic') NOT NULL DEFAULT 'admin',
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email    (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Tabela: assembleias (salas de enquete)
-- =========================================
CREATE TABLE IF NOT EXISTS assembleias (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo           VARCHAR(255)    NOT NULL,
  data_assembleia  DATE            DEFAULT NULL,
  criada_por       BIGINT UNSIGNED NOT NULL,
  status           ENUM('em_andamento','encerrada') NOT NULL DEFAULT 'em_andamento',
  invite_token     CHAR(32)        DEFAULT NULL,
  public_enabled   TINYINT(1)      NOT NULL DEFAULT 0,
  created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assembleias_token (invite_token),
  KEY idx_assembleias_criada_por (criada_por),
  CONSTRAINT fk_assembleias_user
    FOREIGN KEY (criada_por) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- Tabela: participants (convidados)
-- ===================================
CREATE TABLE IF NOT EXISTS participants (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assembleia_id   BIGINT UNSIGNED NOT NULL,
  full_name       VARCHAR(255)    NOT NULL,
  email           VARCHAR(191)    NOT NULL,
  condo_name      VARCHAR(255)    NOT NULL,
  bloco           VARCHAR(64)     NOT NULL,
  unidade         VARCHAR(64)     NOT NULL,
  ip_address      VARCHAR(45)     DEFAULT NULL,
  user_agent      VARCHAR(255)    DEFAULT NULL,
  is_annulled     TINYINT(1)      NOT NULL DEFAULT 0,
  annulled_by     VARCHAR(64)     DEFAULT NULL,
  annulled_at     DATETIME        DEFAULT NULL,
  created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_participants_asm (assembleia_id),
  KEY idx_participants_email (email),
  UNIQUE KEY uq_participants_email (assembleia_id, email),
  CONSTRAINT fk_participants_assembleia
    FOREIGN KEY (assembleia_id) REFERENCES assembleias(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Tabela: itens (pautas)
-- =========================
CREATE TABLE IF NOT EXISTS itens (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assembleia_id  BIGINT UNSIGNED NOT NULL,
  numero         INT             NOT NULL,
  descricao      TEXT            NOT NULL,
  created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_itens_asm (assembleia_id),
  CONSTRAINT uq_itens_numero UNIQUE (assembleia_id, numero),
  CONSTRAINT fk_itens_assembleia
    FOREIGN KEY (assembleia_id) REFERENCES assembleias(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================
-- Tabela: polls (enquetes)
-- ==============================
CREATE TABLE IF NOT EXISTS polls (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id       BIGINT UNSIGNED NOT NULL,
  question      TEXT            NOT NULL,
  max_choices   INT             NOT NULL DEFAULT 1,
  is_active     TINYINT(1)      NOT NULL DEFAULT 1,
  show_results  TINYINT(1)      NOT NULL DEFAULT 0,
  ordem         INT             NOT NULL DEFAULT 1,
  created_by    BIGINT UNSIGNED DEFAULT NULL,
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_polls_item (item_id, ordem),
  KEY idx_polls_active (item_id, is_active),
  KEY idx_polls_public (item_id, show_results),
  CONSTRAINT fk_polls_item
    FOREIGN KEY (item_id) REFERENCES itens(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_polls_user
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================
-- Tabela: options (opções)
-- ==============================
CREATE TABLE IF NOT EXISTS options (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  poll_id      BIGINT UNSIGNED NOT NULL,
  option_text  VARCHAR(255)    NOT NULL,
  created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_options_poll (poll_id),
  CONSTRAINT fk_options_poll
    FOREIGN KEY (poll_id) REFERENCES polls(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================
-- Tabela: votes (votos)
-- ==========================
CREATE TABLE IF NOT EXISTS votes (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  option_id      BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED DEFAULT NULL,       -- legado (admin/basic internos)
  participant_id BIGINT UNSIGNED DEFAULT NULL,       -- novos acessos via link público
  ip_address     VARCHAR(45)     DEFAULT NULL,
  user_agent     VARCHAR(255)    DEFAULT NULL,
  is_annulled    TINYINT(1)      NOT NULL DEFAULT 0,
  annulled_by    VARCHAR(64)     DEFAULT NULL,
  annulled_at    DATETIME        DEFAULT NULL,
  voted_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_votes_option (option_id),
  KEY idx_votes_user (user_id),
  KEY idx_votes_participant (participant_id),
  CONSTRAINT fk_votes_option
    FOREIGN KEY (option_id) REFERENCES options(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_votes_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_votes_participant
    FOREIGN KEY (participant_id) REFERENCES participants(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_vote_has_actor CHECK (user_id IS NOT NULL OR participant_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================
-- (Opcional) Seeds mínimos
-- ==========================
-- INSERT INTO users (username,email,password,role)
-- VALUES ('admin','admin@example.com','$2y$10$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx','admin');
-- (A senha acima deve ser um hash bcrypt gerado por password_hash)

-- ============================================
-- Tabela de logs de acesso (admin/basics/etc.)
-- Usada por auth/logout.php e pode ser usada em outros pontos
-- ============================================
CREATE TABLE IF NOT EXISTS access_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NULL,
  role        VARCHAR(30) NULL,
  action      VARCHAR(64) NOT NULL,
  ip_address  VARCHAR(64) NULL,
  user_agent  TEXT NULL,
  page        VARCHAR(255) NULL,
  meta        JSON NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_access_logs_created_at ON access_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_access_logs_user_role ON access_logs(user_id, role);