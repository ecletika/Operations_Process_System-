-- OPS-SQL-001 · 005_views.sql
-- Views de leitura para dashboards (OPS-PRD-001 capítulo 10.17)

USE ops;

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
