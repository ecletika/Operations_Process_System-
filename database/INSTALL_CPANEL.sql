-- ============================================================================
-- OPS · INSTALL_CPANEL.sql
-- Script único de instalação para o cPanel (phpMyAdmin).
-- 1. Crie a base de dados e o utilizador MySQL no cPanel (MySQL® Databases).
-- 2. Em phpMyAdmin, selecione essa base de dados e importe este ficheiro.
-- 3. Atualize o .env no servidor com o nome da BD, utilizador e password.
-- Login inicial da aplicação -> utilizador: admin | password: IrmaosLeite@2026!
-- (Altere a password após o primeiro login.)
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- >>> 002_tables.sql
-- ----------------------------------------------------------------------------
-- OPS-SQL-001 · 002_tables.sql
-- Criação das 25 tabelas do OPS, na ordem de dependência.
-- Todas as tabelas seguem o padrão obrigatório (OPS-DB-002 secção 2):
--   id, uuid, created_at, updated_at, deleted_at, created_by, updated_by, deleted_by
-- Nenhuma tabela usa DELETE físico (soft delete via deleted_at).


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

-- ----------------------------------------------------------------------------
-- >>> 003_indexes.sql
-- ----------------------------------------------------------------------------
-- OPS-SQL-001 · 003_indexes.sql
-- Índices estratégicos (OPS-DB-002 secção 6 + OPS-PRD-001 capítulo 10.14)


CREATE INDEX idx_branch_company ON tb_branch (company_id);
CREATE INDEX idx_department_branch ON tb_department (branch_id);
CREATE INDEX idx_batch_department ON tb_batch (department_id);
CREATE INDEX idx_role_permission_role ON tb_role_permission (role_id);
CREATE INDEX idx_role_permission_permission ON tb_role_permission (permission_id);

CREATE INDEX idx_user_role ON tb_user (role_id);
CREATE INDEX idx_user_company ON tb_user (company_id);
CREATE INDEX idx_user_branch ON tb_user (branch_id);
CREATE INDEX idx_user_department ON tb_user (department_id);
CREATE INDEX idx_user_active ON tb_user (active);

CREATE INDEX idx_user_batch_user ON tb_user_batch (user_id);
CREATE INDEX idx_user_batch_batch ON tb_user_batch (batch_id);

CREATE INDEX idx_vehicle_customer ON tb_vehicle (customer_id);

CREATE INDEX idx_process_number ON tb_process (process_number);
CREATE INDEX idx_process_vehicle ON tb_process (vehicle_id);
CREATE INDEX idx_process_customer ON tb_process (customer_id);
CREATE INDEX idx_process_status ON tb_process (status_id);
CREATE INDEX idx_process_batch ON tb_process (batch_id);
CREATE INDEX idx_process_assigned ON tb_process (assigned_to);
CREATE INDEX idx_process_created_at ON tb_process (created_at);
CREATE INDEX idx_process_last_contact ON tb_process (last_contact_at);

CREATE INDEX idx_interaction_process ON tb_interaction (process_id);
CREATE INDEX idx_interaction_operator ON tb_interaction (operator_id);

CREATE INDEX idx_event_process ON tb_event (process_id);
CREATE INDEX idx_event_type ON tb_event (event_type);

CREATE INDEX idx_timeline_process ON tb_timeline (process_id);
CREATE INDEX idx_timeline_event ON tb_timeline (event_id);

CREATE INDEX idx_note_process ON tb_note (process_id);
CREATE INDEX idx_attachment_process ON tb_attachment (process_id);

CREATE INDEX idx_notification_user ON tb_notification (user_id);
CREATE INDEX idx_notification_read ON tb_notification (read_at);

CREATE INDEX idx_login_log_user ON tb_login_log (user_id);

CREATE INDEX idx_audit_table_record ON tb_audit (table_name, record_id);
CREATE INDEX idx_audit_user ON tb_audit (user_id);
CREATE INDEX idx_audit_created_at ON tb_audit (created_at);

-- ----------------------------------------------------------------------------
-- >>> 004_foreign_keys.sql
-- ----------------------------------------------------------------------------
-- OPS-SQL-001 · 004_foreign_keys.sql
-- Todas as FKs seguem ON UPDATE CASCADE / ON DELETE RESTRICT (OPS-PRD-001 10.15)


ALTER TABLE tb_branch
  ADD CONSTRAINT fk_branch_company FOREIGN KEY (company_id) REFERENCES tb_company (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_department
  ADD CONSTRAINT fk_department_branch FOREIGN KEY (branch_id) REFERENCES tb_branch (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_batch
  ADD CONSTRAINT fk_batch_department FOREIGN KEY (department_id) REFERENCES tb_department (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_role_permission
  ADD CONSTRAINT fk_role_permission_role FOREIGN KEY (role_id) REFERENCES tb_role (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_role_permission_permission FOREIGN KEY (permission_id) REFERENCES tb_permission (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_user
  ADD CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES tb_role (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_user_company FOREIGN KEY (company_id) REFERENCES tb_company (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_user_branch FOREIGN KEY (branch_id) REFERENCES tb_branch (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_user_department FOREIGN KEY (department_id) REFERENCES tb_department (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_user_batch
  ADD CONSTRAINT fk_user_batch_user FOREIGN KEY (user_id) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_user_batch_batch FOREIGN KEY (batch_id) REFERENCES tb_batch (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_vehicle
  ADD CONSTRAINT fk_vehicle_customer FOREIGN KEY (customer_id) REFERENCES tb_customer (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_process
  ADD CONSTRAINT fk_process_company FOREIGN KEY (company_id) REFERENCES tb_company (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_process_batch FOREIGN KEY (batch_id) REFERENCES tb_batch (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_process_customer FOREIGN KEY (customer_id) REFERENCES tb_customer (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_process_vehicle FOREIGN KEY (vehicle_id) REFERENCES tb_vehicle (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_process_subject FOREIGN KEY (subject_id) REFERENCES tb_subject (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_process_status FOREIGN KEY (status_id) REFERENCES tb_status (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_process_priority FOREIGN KEY (priority_id) REFERENCES tb_priority (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_process_created_by FOREIGN KEY (created_by) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_process_assigned_to FOREIGN KEY (assigned_to) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_process_updated_by FOREIGN KEY (updated_by) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_process_closed_by FOREIGN KEY (closed_by) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_interaction
  ADD CONSTRAINT fk_interaction_process FOREIGN KEY (process_id) REFERENCES tb_process (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_interaction_operator FOREIGN KEY (operator_id) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_event
  ADD CONSTRAINT fk_event_process FOREIGN KEY (process_id) REFERENCES tb_process (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_event_user FOREIGN KEY (user_id) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_timeline
  ADD CONSTRAINT fk_timeline_process FOREIGN KEY (process_id) REFERENCES tb_process (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_timeline_event FOREIGN KEY (event_id) REFERENCES tb_event (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_note
  ADD CONSTRAINT fk_note_process FOREIGN KEY (process_id) REFERENCES tb_process (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_note_author FOREIGN KEY (author_id) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_note_edited_from FOREIGN KEY (edited_from) REFERENCES tb_note (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_attachment
  ADD CONSTRAINT fk_attachment_process FOREIGN KEY (process_id) REFERENCES tb_process (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_attachment_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_notification
  ADD CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_login_log
  ADD CONSTRAINT fk_login_log_user FOREIGN KEY (user_id) REFERENCES tb_user (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE tb_process_metrics
  ADD CONSTRAINT fk_process_metrics_process FOREIGN KEY (process_id) REFERENCES tb_process (id) ON UPDATE CASCADE ON DELETE RESTRICT;

-- ----------------------------------------------------------------------------
-- >>> 005_views.sql
-- ----------------------------------------------------------------------------
-- OPS-SQL-001 · 005_views.sql
-- Views de leitura para dashboards (OPS-PRD-001 capítulo 10.17)


CREATE OR REPLACE VIEW vw_process_summary AS
SELECT
  p.id,
  p.uuid,
  p.process_number,
  c.name         AS customer_name,
  v.plate        AS vehicle_plate,
  sub.name       AS subject_name,
  st.code        AS status_code,
  st.name        AS status_name,
  pr.code        AS priority_code,
  pr.name        AS priority_name,
  u.first_name   AS assigned_first_name,
  u.last_name    AS assigned_last_name,
  p.contact_count,
  p.reopen_count,
  p.first_contact_at,
  p.last_contact_at,
  p.assumed_at,
  p.closed_at,
  p.archived,
  p.created_at
FROM tb_process p
JOIN tb_customer c ON c.id = p.customer_id
JOIN tb_vehicle v ON v.id = p.vehicle_id
JOIN tb_subject sub ON sub.id = p.subject_id
JOIN tb_status st ON st.id = p.status_id
JOIN tb_priority pr ON pr.id = p.priority_id
LEFT JOIN tb_user u ON u.id = p.assigned_to
WHERE p.deleted_at IS NULL;

CREATE OR REPLACE VIEW vw_dashboard AS
SELECT
  st.code AS status_code,
  COUNT(*) AS total
FROM tb_process p
JOIN tb_status st ON st.id = p.status_id
WHERE p.deleted_at IS NULL
GROUP BY st.code;

CREATE OR REPLACE VIEW vw_operator AS
SELECT
  p.assigned_to AS user_id,
  COUNT(*) AS total_assigned,
  SUM(CASE WHEN p.closed_at IS NULL THEN 1 ELSE 0 END) AS total_open
FROM tb_process p
WHERE p.deleted_at IS NULL AND p.assigned_to IS NOT NULL
GROUP BY p.assigned_to;

-- ----------------------------------------------------------------------------
-- >>> 006_process_sequence.sql
-- ----------------------------------------------------------------------------
-- OPS-SQL-001 · 006_process_sequence.sql
-- Contador atómico do número de processo (RN-0002: PR-AAAA-XXXXXXXX, nunca reutilizado).


CREATE TABLE tb_process_sequence (
  year        SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
  last_value  INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- >>> 007_api_tokens.sql
-- ----------------------------------------------------------------------------
-- OPS-SQL-001 · 007_api_tokens.sql
-- Fase 5 (API REST) - tokens de acesso pessoal (Bearer), independentes da sessão web.


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

-- ----------------------------------------------------------------------------
-- >>> 009_seeders.sql
-- ----------------------------------------------------------------------------
-- OPS-SQL-001 · 009_seeders.sql
-- Dados básicos para o sistema arrancar (OPS-SQL-001 secção 12).
-- O utilizador administrador é criado à parte por database/seed_admin.php
-- (precisa de password_hash() em PHP, não deve viver como hash fixo em SQL).


-- O cliente `mysql` no Windows costuma assumir o code page da consola
-- (ex.: CP850) em vez de UTF-8 ao ler este ficheiro via stdin, corrompendo
-- os acentos ao inserir. SET NAMES força a sessão a tratar os literais
-- deste ficheiro como utf8mb4, independentemente do codepage do terminal.

-- Perfis --------------------------------------------------------------------
INSERT INTO tb_role (uuid, code, name) VALUES
  (UUID(), 'ROLE_ADMIN', 'Administrador'),
  (UUID(), 'ROLE_SUPERVISOR', 'Supervisor'),
  (UUID(), 'ROLE_OPERATOR', 'Operador'),
  (UUID(), 'ROLE_VIEWER', 'Consulta');

-- Permissões (OPS-PRD-001 3.5 Matriz de Permissões) --------------------------
INSERT INTO tb_permission (uuid, code, description) VALUES
  (UUID(), 'dashboard.view', 'Visualizar dashboard'),
  (UUID(), 'process.create', 'Criar processo'),
  (UUID(), 'process.assume', 'Assumir processo'),
  (UUID(), 'process.edit', 'Editar processo'),
  (UUID(), 'process.edit_own', 'Editar apenas os seus processos'),
  (UUID(), 'process.close', 'Concluir processo'),
  (UUID(), 'process.reopen', 'Reabrir processo'),
  (UUID(), 'process.view_all', 'Ver todos os processos (não só a fila/os seus)'),
  (UUID(), 'process.delete', 'Excluir processo (apenas Administrador)'),
  (UUID(), 'records.delete', 'Excluir registos (clientes, viaturas)'),
  (UUID(), 'users.manage', 'Gerir utilizadores'),
  (UUID(), 'companies.manage', 'Gerir empresas'),
  (UUID(), 'branches.manage', 'Gerir filiais'),
  (UUID(), 'batches.manage', 'Gerir lotes'),
  (UUID(), 'subjects.manage', 'Gerir assuntos'),
  (UUID(), 'audit.view', 'Visualizar auditoria'),
  (UUID(), 'logs.view', 'Visualizar logs'),
  (UUID(), 'settings.manage', 'Gerir configurações'),
  (UUID(), 'reports.export', 'Exportar relatórios');

-- Associação Perfil <-> Permissão --------------------------------------------
-- ROLE_ADMIN: todas as permissões
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r, tb_permission p
WHERE r.code = 'ROLE_ADMIN';

-- ROLE_SUPERVISOR
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r, tb_permission p
WHERE r.code = 'ROLE_SUPERVISOR'
  AND p.code IN ('dashboard.view','process.create','process.assume','process.edit',
                 'process.close','process.reopen','process.view_all','reports.export');

-- ROLE_OPERATOR
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r, tb_permission p
WHERE r.code = 'ROLE_OPERATOR'
  AND p.code IN ('dashboard.view','process.create','process.assume','process.edit_own','process.close');

-- ROLE_VIEWER
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r, tb_permission p
WHERE r.code = 'ROLE_VIEWER'
  AND p.code IN ('dashboard.view');

-- Estados (OPS-PRD-001 4.5) --------------------------------------------------
INSERT INTO tb_status (uuid, code, name, sort_order) VALUES
  (UUID(), 'NEW', 'Novo', 1),
  (UUID(), 'QUEUE', 'Em Fila', 2),
  (UUID(), 'ASSIGNED', 'Assumido', 3),
  (UUID(), 'IN_PROGRESS', 'Em Tratamento', 4),
  (UUID(), 'WAIT_CLIENT', 'Aguarda Cliente', 5),
  (UUID(), 'WAIT_PARTS', 'Aguarda Peças', 6),
  (UUID(), 'WAIT_WORKSHOP', 'Aguarda Oficina', 7),
  (UUID(), 'WAIT_EXTERNAL', 'Aguarda Terceiros', 8),
  (UUID(), 'SOLVED', 'Resolvido', 9),
  (UUID(), 'CLOSED', 'Encerrado', 10),
  (UUID(), 'REOPENED', 'Reaberto', 11);

-- Prioridades (OPS-PRD-001 4.13) ---------------------------------------------
INSERT INTO tb_priority (uuid, code, name, color, sort_order, default_sla_minutes) VALUES
  (UUID(), 'P1', 'Crítica', '#dc2626', 1, 60),
  (UUID(), 'P2', 'Alta', '#f97316', 2, 240),
  (UUID(), 'P3', 'Média', '#eab308', 3, 1440),
  (UUID(), 'P4', 'Baixa', '#22c55e', 4, 4320);

-- Assuntos (OPS-PRD-001 2.11 exemplo) ----------------------------------------
INSERT INTO tb_subject (uuid, code, name) VALUES
  (UUID(), 'ASSISTANCE', 'Assistência'),
  (UUID(), 'CLAIM', 'Sinistro'),
  (UUID(), 'TOW', 'Reboque'),
  (UUID(), 'WORKSHOP', 'Oficina'),
  (UUID(), 'INFO', 'Informação');

-- Configurações globais (OPS-PRD-001 4.7, 10.20) ------------------------------
INSERT INTO tb_setting (uuid, `key`, `value`, description) VALUES
  (UUID(), 'reopen_window_days', '30', 'Janela de reincidência em dias'),
  (UUID(), 'session_timeout_minutes', '60', 'Tempo de sessão'),
  (UUID(), 'dashboard_refresh_seconds', '30', 'Intervalo de atualização do dashboard'),
  (UUID(), 'login_max_attempts', '5', 'Tentativas de login antes de bloquear'),
  (UUID(), 'login_lock_minutes', '15', 'Duração do bloqueio após tentativas inválidas'),
  (UUID(), 'forgotten_process_hours', '48', 'RN-0056: horas sem atualização até o processo ser considerado esquecido'),
  (UUID(), 'operator_overload_limit', '30', 'RN-0057: nº máximo de processos ativos por operador'),
  (UUID(), 'frequent_customer_threshold', '5', 'RN-0059: nº de processos no período para marcar Cliente Frequente'),
  (UUID(), 'recurrent_vehicle_threshold', '3', 'RN-0060: nº de processos no período para marcar Viatura Recorrente'),
  (UUID(), 'recurrence_window_days', '90', 'Janela em dias usada para calcular Cliente Frequente / Viatura Recorrente'),
  (UUID(), 'archive_concluded_after_days', '30', 'Dias após conclusão até o processo ser arquivado automaticamente'),
  (UUID(), 'delete_archived_after_days', '180', 'Dias após arquivamento até o processo ser excluído automaticamente (vai para a Lixeira)'),
  (UUID(), 'audit_retention_days', '60', 'Dias de retenção da auditoria; registos mais antigos são apagados automaticamente');

-- Estrutura organizacional mínima --------------------------------------------
INSERT INTO tb_company (uuid, code, name) VALUES (UUID(), 'OPS', 'Operations Process System');
INSERT INTO tb_branch (uuid, company_id, code, name)
  SELECT UUID(), id, 'LIS', 'Lisboa' FROM tb_company WHERE code = 'OPS';
INSERT INTO tb_department (uuid, branch_id, code, name)
  SELECT UUID(), id, 'WORKSHOP', 'Oficina' FROM tb_branch WHERE code = 'LIS';
INSERT INTO tb_batch (uuid, department_id, code, description)
  SELECT UUID(), id, 'IL-132', 'Lote inicial' FROM tb_department WHERE code = 'WORKSHOP';

-- ----------------------------------------------------------------------------
-- >>> 010_user_default_batch.sql
-- ----------------------------------------------------------------------------
-- OPS-SQL-001 · 010_user_default_batch.sql
-- Um utilizador pode trabalhar em vários lotes (tb_user_batch, N:N),
-- mas precisa de um "Lote Padrão" para pré-preencher o Novo Processo
-- sem obrigar a escolher sempre que sessão é iniciada.


ALTER TABLE tb_user
  ADD COLUMN default_batch_id BIGINT UNSIGNED NULL AFTER department_id;

ALTER TABLE tb_user
  ADD CONSTRAINT fk_user_default_batch FOREIGN KEY (default_batch_id) REFERENCES tb_batch (id) ON UPDATE CASCADE ON DELETE RESTRICT;

CREATE INDEX idx_user_default_batch ON tb_user (default_batch_id);

-- ----------------------------------------------------------------------------
-- >>> 012_user_view_all_batches.sql
-- ----------------------------------------------------------------------------
-- Parâmetro por utilizador: visibilidade da Fila Inteligente™.
-- 0 = vê apenas os processos do seu próprio lote (comportamento normal do Operador)
-- 1 = vê os processos de todos os lotes (todas as filiais)

ALTER TABLE tb_user
    ADD COLUMN view_all_batches TINYINT(1) NOT NULL DEFAULT 0 AFTER default_batch_id;

-- ----------------------------------------------------------------------------
-- >>> Utilizador Administrador inicial
-- utilizador: admin | password: IrmaosLeite@2026!  (alterar após o 1.º login)
-- ----------------------------------------------------------------------------
INSERT INTO tb_user
    (uuid, username, email, password, first_name, last_name, role_id, company_id, branch_id, department_id, active, created_at)
SELECT UUID(), 'admin', 'admin@ops.local',
       '$2y$10$tiJhw/APfXQ34JQ0HKum.eIuBN8OJLanZPcNHWTiHtikOy2PJr1Yq',
       'Administrador', 'OPS', r.id, c.id, b.id, d.id, 1, NOW()
FROM tb_role r, tb_company c, tb_branch b, tb_department d
WHERE r.code = 'ROLE_ADMIN' AND c.code = 'OPS' AND b.code = 'LIS' AND d.code = 'WORKSHOP';

INSERT INTO tb_user_batch (uuid, user_id, batch_id, created_at)
SELECT UUID(), u.id, bt.id, NOW()
FROM tb_user u, tb_batch bt
WHERE u.username = 'admin' AND bt.code = 'IL-132';

UPDATE tb_user u
JOIN tb_batch bt ON bt.code = 'IL-132'
SET u.default_batch_id = bt.id
WHERE u.username = 'admin';

-- ----------------------------------------------------------------------------
-- >>> MFA (autenticação de dois fatores) — colunas, tabela e settings
-- ----------------------------------------------------------------------------
ALTER TABLE tb_user
    ADD COLUMN mfa_secret  VARCHAR(64)  NULL AFTER password,
    ADD COLUMN mfa_enabled TINYINT(1)   NOT NULL DEFAULT 0 AFTER mfa_secret;

CREATE TABLE IF NOT EXISTS tb_mfa_trusted_device (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid        CHAR(36)        NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  CHAR(64)        NOT NULL,
    expires_at  DATETIME        NOT NULL,
    ip_address  VARCHAR(45)     NULL,
    user_agent  VARCHAR(255)    NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mfa_device_uuid (uuid),
    KEY idx_mfa_device_user (user_id),
    KEY idx_mfa_device_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tb_setting (uuid, `key`, `value`, description) VALUES
  (UUID(), 'mfa_required', '0', 'Exigir MFA a todos os utilizadores no login (1=sim). Ative depois de testar com a sua conta.'),
  (UUID(), 'mfa_trust_hours', '24', 'Horas que um dispositivo fica confiável após passar o MFA (pedir 1x por dia)');
