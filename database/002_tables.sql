-- OPS-SQL-001 · 002_tables.sql
-- Criação das 25 tabelas do OPS, na ordem de dependência.
-- Todas as tabelas seguem o padrão obrigatório (OPS-DB-002 secção 2):
--   id, uuid, created_at, updated_at, deleted_at, created_by, updated_by, deleted_by
-- Nenhuma tabela usa DELETE físico (soft delete via deleted_at).

USE ops;

-- 1. tb_company -------------------------------------------------------------
CREATE TABLE tb_company (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  code         VARCHAR(20)   NOT NULL,
  name         VARCHAR(200)  NOT NULL,
  nif          VARCHAR(30)   NULL,
  active       BOOLEAN       NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_company_uuid (uuid),
  UNIQUE KEY uq_company_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. tb_branch ----------------------------------------------------------------
CREATE TABLE tb_branch (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  company_id   BIGINT UNSIGNED NOT NULL,
  code         VARCHAR(20)   NOT NULL,
  name         VARCHAR(150)  NOT NULL,
  active       BOOLEAN       NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_branch_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. tb_department --------------------------------------------------------
CREATE TABLE tb_department (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  branch_id    BIGINT UNSIGNED NOT NULL,
  code         VARCHAR(20)   NOT NULL,
  name         VARCHAR(150)  NOT NULL,
  active       BOOLEAN       NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_department_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. tb_batch (lotes) -------------------------------------------------------
CREATE TABLE tb_batch (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid           CHAR(36)      NOT NULL,
  department_id  BIGINT UNSIGNED NOT NULL,
  code           VARCHAR(20)   NOT NULL,
  description    VARCHAR(150)  NULL,
  active         BOOLEAN       NOT NULL DEFAULT 1,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at     DATETIME      NULL,
  created_by     BIGINT UNSIGNED NULL,
  updated_by     BIGINT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_batch_uuid (uuid),
  UNIQUE KEY uq_batch_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. tb_role (perfis) ---------------------------------------------------------
CREATE TABLE tb_role (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  code         VARCHAR(40)   NOT NULL,
  name         VARCHAR(100)  NOT NULL,
  active       BOOLEAN       NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_role_uuid (uuid),
  UNIQUE KEY uq_role_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. tb_permission --------------------------------------------------------
CREATE TABLE tb_permission (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  code         VARCHAR(80)   NOT NULL,
  description  VARCHAR(200)  NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_permission_uuid (uuid),
  UNIQUE KEY uq_permission_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. tb_role_permission -----------------------------------------------------
CREATE TABLE tb_role_permission (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid           CHAR(36)      NOT NULL,
  role_id        BIGINT UNSIGNED NOT NULL,
  permission_id  BIGINT UNSIGNED NOT NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at     DATETIME      NULL,
  created_by     BIGINT UNSIGNED NULL,
  updated_by     BIGINT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_role_permission_uuid (uuid),
  UNIQUE KEY uq_role_permission (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. tb_user ------------------------------------------------------------------
CREATE TABLE tb_user (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid              CHAR(36)      NOT NULL,
  employee_number   VARCHAR(30)   NULL,
  username          VARCHAR(80)   NOT NULL,
  email             VARCHAR(150)  NOT NULL,
  password          VARCHAR(255)  NOT NULL,
  first_name        VARCHAR(100)  NOT NULL,
  last_name         VARCHAR(100)  NOT NULL,
  photo             VARCHAR(255)  NULL,
  role_id           BIGINT UNSIGNED NOT NULL,
  company_id        BIGINT UNSIGNED NOT NULL,
  branch_id         BIGINT UNSIGNED NOT NULL,
  department_id     BIGINT UNSIGNED NOT NULL,
  last_login_at     DATETIME      NULL,
  failed_attempts   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until      DATETIME      NULL,
  active            BOOLEAN       NOT NULL DEFAULT 1,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at        DATETIME      NULL,
  created_by        BIGINT UNSIGNED NULL,
  updated_by        BIGINT UNSIGNED NULL,
  deleted_by        BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_user_uuid (uuid),
  UNIQUE KEY uq_user_username (username),
  UNIQUE KEY uq_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. tb_user_batch (N:N utilizador <-> lote) ---------------------------------
CREATE TABLE tb_user_batch (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  user_id      BIGINT UNSIGNED NOT NULL,
  batch_id     BIGINT UNSIGNED NOT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_user_batch_uuid (uuid),
  UNIQUE KEY uq_user_batch (user_id, batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. tb_customer -------------------------------------------------------------
CREATE TABLE tb_customer (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid               CHAR(36)      NOT NULL,
  customer_code      VARCHAR(30)   NULL,
  name               VARCHAR(150)  NOT NULL,
  phone              VARCHAR(30)   NULL,
  email              VARCHAR(150)  NULL,
  nif                VARCHAR(30)   NULL,
  preferred_contact  ENUM('PHONE','EMAIL','SMS') NOT NULL DEFAULT 'PHONE',
  active             BOOLEAN       NOT NULL DEFAULT 1,
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME      NULL,
  created_by         BIGINT UNSIGNED NULL,
  updated_by         BIGINT UNSIGNED NULL,
  deleted_by         BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_customer_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. tb_vehicle ----------------------------------------------------------
CREATE TABLE tb_vehicle (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  customer_id  BIGINT UNSIGNED NOT NULL,
  plate        VARCHAR(20)   NOT NULL,
  brand        VARCHAR(100)  NULL,
  model        VARCHAR(100)  NULL,
  version      VARCHAR(100)  NULL,
  color        VARCHAR(40)   NULL,
  year         SMALLINT      NULL,
  vin          VARCHAR(50)   NULL,
  active       BOOLEAN       NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_vehicle_uuid (uuid),
  UNIQUE KEY uq_vehicle_plate (plate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. tb_status (estados parametrizáveis) ------------------------------------
CREATE TABLE tb_status (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  code         VARCHAR(30)   NOT NULL,
  name         VARCHAR(80)   NOT NULL,
  sort_order   SMALLINT      NOT NULL DEFAULT 0,
  active       BOOLEAN       NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_status_uuid (uuid),
  UNIQUE KEY uq_status_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. tb_priority -------------------------------------------------------------
CREATE TABLE tb_priority (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid           CHAR(36)      NOT NULL,
  code           VARCHAR(30)   NOT NULL,
  name           VARCHAR(80)   NOT NULL,
  color          VARCHAR(20)   NULL,
  sort_order     SMALLINT      NOT NULL DEFAULT 0,
  default_sla_minutes INT      NULL,
  active         BOOLEAN       NOT NULL DEFAULT 1,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at     DATETIME      NULL,
  created_by     BIGINT UNSIGNED NULL,
  updated_by     BIGINT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_priority_uuid (uuid),
  UNIQUE KEY uq_priority_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. tb_subject ----------------------------------------------------------
CREATE TABLE tb_subject (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  code         VARCHAR(30)   NOT NULL,
  name         VARCHAR(100)  NOT NULL,
  active       BOOLEAN       NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_subject_uuid (uuid),
  UNIQUE KEY uq_subject_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. tb_process (⭐ entidade central do OPS) ---------------------------------
CREATE TABLE tb_process (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid               CHAR(36)      NOT NULL,
  process_number     VARCHAR(20)   NOT NULL,
  company_id         BIGINT UNSIGNED NOT NULL,
  batch_id           BIGINT UNSIGNED NOT NULL,
  customer_id        BIGINT UNSIGNED NOT NULL,
  vehicle_id         BIGINT UNSIGNED NOT NULL,
  subject_id         BIGINT UNSIGNED NOT NULL,
  status_id          BIGINT UNSIGNED NOT NULL,
  priority_id        BIGINT UNSIGNED NOT NULL,
  created_by         BIGINT UNSIGNED NOT NULL,
  assigned_to        BIGINT UNSIGNED NULL,
  updated_by         BIGINT UNSIGNED NULL,
  closed_by          BIGINT UNSIGNED NULL,
  first_contact_at   DATETIME      NOT NULL,
  last_contact_at    DATETIME      NOT NULL,
  assumed_at         DATETIME      NULL,
  closed_at          DATETIME      NULL,
  contact_count      INT UNSIGNED  NOT NULL DEFAULT 1,
  reopen_count       INT UNSIGNED  NOT NULL DEFAULT 0,
  archived           BOOLEAN       NOT NULL DEFAULT 0,
  archived_at        DATETIME      NULL,
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME      NULL,
  deleted_by         BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_process_uuid (uuid),
  UNIQUE KEY uq_process_number (process_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. tb_interaction (contactos) ---------------------------------------------
CREATE TABLE tb_interaction (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid               CHAR(36)      NOT NULL,
  process_id         BIGINT UNSIGNED NOT NULL,
  interaction_type   VARCHAR(40)   NOT NULL,
  channel            VARCHAR(30)   NOT NULL,
  description         TEXT          NULL,
  operator_id        BIGINT UNSIGNED NOT NULL,
  received_at        DATETIME      NOT NULL,
  duration_seconds   INT UNSIGNED  NULL,
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME      NULL,
  created_by         BIGINT UNSIGNED NULL,
  updated_by         BIGINT UNSIGNED NULL,
  deleted_by         BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_interaction_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. tb_event (histórico técnico) ---------------------------------------
CREATE TABLE tb_event (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  process_id   BIGINT UNSIGNED NOT NULL,
  event_type   VARCHAR(60)   NOT NULL,
  title        VARCHAR(150)  NOT NULL,
  description  TEXT          NULL,
  user_id      BIGINT UNSIGNED NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_event_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. tb_timeline (leitura humana da Timeline Viva™) -------------------------
CREATE TABLE tb_timeline (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid                 CHAR(36)      NOT NULL,
  process_id           BIGINT UNSIGNED NOT NULL,
  event_id             BIGINT UNSIGNED NOT NULL,
  title                VARCHAR(150)  NOT NULL,
  description          TEXT          NULL,
  icon                 VARCHAR(40)   NULL,
  color                VARCHAR(20)   NULL,
  visible_to_operator  BOOLEAN       NOT NULL DEFAULT 1,
  created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at           DATETIME      NULL,
  created_by           BIGINT UNSIGNED NULL,
  updated_by           BIGINT UNSIGNED NULL,
  deleted_by           BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_timeline_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. tb_note (observações) --------------------------------------------------
CREATE TABLE tb_note (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  process_id   BIGINT UNSIGNED NOT NULL,
  note         TEXT          NOT NULL,
  author_id    BIGINT UNSIGNED NOT NULL,
  version      INT UNSIGNED  NOT NULL DEFAULT 1,
  edited_from  BIGINT UNSIGNED NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_note_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. tb_attachment (anexos) -------------------------------------------------
CREATE TABLE tb_attachment (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid              CHAR(36)      NOT NULL,
  process_id        BIGINT UNSIGNED NOT NULL,
  original_name     VARCHAR(255)  NOT NULL,
  stored_name       VARCHAR(255)  NOT NULL,
  mime_type         VARCHAR(100)  NOT NULL,
  extension         VARCHAR(10)   NOT NULL,
  file_size         BIGINT UNSIGNED NOT NULL,
  checksum_sha256   CHAR(64)      NOT NULL,
  uploaded_by       BIGINT UNSIGNED NOT NULL,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at        DATETIME      NULL,
  created_by        BIGINT UNSIGNED NULL,
  updated_by        BIGINT UNSIGNED NULL,
  deleted_by        BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_attachment_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. tb_notification (centro de notificações) -------------------------------
CREATE TABLE tb_notification (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  user_id      BIGINT UNSIGNED NOT NULL,
  title        VARCHAR(150)  NOT NULL,
  message      TEXT          NOT NULL,
  severity     ENUM('INFO','WARNING','CRITICAL') NOT NULL DEFAULT 'INFO',
  read_at      DATETIME      NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_notification_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. tb_login_log ------------------------------------------------------------
CREATE TABLE tb_login_log (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid               CHAR(36)      NOT NULL,
  user_id            BIGINT UNSIGNED NULL,
  login_at           DATETIME      NOT NULL,
  logout_at          DATETIME      NULL,
  ip_address         VARCHAR(45)   NOT NULL,
  browser            VARCHAR(150)  NULL,
  operating_system   VARCHAR(100)  NULL,
  success            BOOLEAN       NOT NULL,
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME      NULL,
  created_by         BIGINT UNSIGNED NULL,
  updated_by         BIGINT UNSIGNED NULL,
  deleted_by         BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_login_log_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. tb_audit (nunca editada, nunca apagada) --------------------------------
CREATE TABLE tb_audit (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid             CHAR(36)      NOT NULL,
  table_name       VARCHAR(60)   NOT NULL,
  record_id        BIGINT UNSIGNED NOT NULL,
  field_name       VARCHAR(60)   NULL,
  old_value        TEXT          NULL,
  new_value        TEXT          NULL,
  old_values       JSON          NULL,
  new_values       JSON          NULL,
  action           VARCHAR(40)   NOT NULL,
  session_id       VARCHAR(100)  NULL,
  ip_address       VARCHAR(45)   NULL,
  user_agent       VARCHAR(255)  NULL,
  request_method   VARCHAR(10)   NULL,
  request_url      VARCHAR(255)  NULL,
  user_id          BIGINT UNSIGNED NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_audit_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. tb_setting (parâmetros globais) ----------------------------------------
CREATE TABLE tb_setting (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36)      NOT NULL,
  `key`        VARCHAR(100)  NOT NULL,
  `value`      TEXT          NULL,
  description  VARCHAR(255)  NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME      NULL,
  created_by   BIGINT UNSIGNED NULL,
  updated_by   BIGINT UNSIGNED NULL,
  deleted_by   BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_setting_uuid (uuid),
  UNIQUE KEY uq_setting_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 25. tb_process_metrics (métricas consolidadas p/ dashboards) --------------
CREATE TABLE tb_process_metrics (
  id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid                     CHAR(36)      NOT NULL,
  process_id               BIGINT UNSIGNED NOT NULL,
  first_response_seconds  INT UNSIGNED  NULL,
  resolution_seconds      INT UNSIGNED  NULL,
  waiting_seconds         INT UNSIGNED  NULL,
  active_seconds          INT UNSIGNED  NULL,
  sla_met                 BOOLEAN       NULL,
  interactions_total      INT UNSIGNED  NOT NULL DEFAULT 0,
  reopen_total            INT UNSIGNED  NOT NULL DEFAULT 0,
  calculated_at           DATETIME      NOT NULL,
  created_at              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_process_metrics_uuid (uuid),
  UNIQUE KEY uq_process_metrics_process (process_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
