-- OPS-SQL-001 · 004_foreign_keys.sql
-- Todas as FKs seguem ON UPDATE CASCADE / ON DELETE RESTRICT (OPS-PRD-001 10.15)

USE ops;

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
