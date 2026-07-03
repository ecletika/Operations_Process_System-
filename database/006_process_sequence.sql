-- OPS-SQL-001 · 006_process_sequence.sql
-- Contador atómico do número de processo (RN-0002: PR-AAAA-XXXXXXXX, nunca reutilizado).

USE ops;

CREATE TABLE tb_process_sequence (
  year        SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
  last_value  INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
