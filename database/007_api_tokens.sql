-- OPS-SQL-001 · 007_api_tokens.sql
-- Fase 5 (API REST) - tokens de acesso pessoal (Bearer), independentes da sessão web.

USE ops;

CREATE TABLE tb_api_token (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid           CHAR(36)      NOT NULL,
  user_id        BIGINT UNSIGNED NOT NULL,
  name           VARCHAR(100)  NOT NULL,
  token_hash     CHAR(64)      NOT NULL,
  last_used_at   DATETIME      NULL,
  expires_at     DATETIME      NULL,
  revoked_at     DATETIME      NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at     DATETIME      NULL,
  created_by     BIGINT UNSIGNED NULL,
  updated_by     BIGINT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_api_token_uuid (uuid),
  UNIQUE KEY uq_api_token_hash (token_hash),
  KEY idx_api_token_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tb_api_token
  ADD CONSTRAINT fk_api_token_user FOREIGN KEY (user_id) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;
