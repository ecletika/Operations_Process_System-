-- OPS-SQL-001 · 003_indexes.sql
-- Índices estratégicos (OPS-DB-002 secção 6 + OPS-PRD-001 capítulo 10.14)

USE ops;

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
