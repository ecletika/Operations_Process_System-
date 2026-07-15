-- Assuntos permitidos por Departamento (#5): permite configurar que Assuntos
-- aparecem no Novo Processo consoante o Departamento/Filial escolhido.
-- Se um Departamento NÃO tiver nenhum Assunto configurado aqui, o sistema
-- mostra TODOS os assuntos ativos (retrocompatível — nada muda até configurar).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tb_department_subject (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NOT NULL,
    subject_id    BIGINT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by    BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_dept_subject (department_id, subject_id),
    KEY idx_ds_department (department_id),
    KEY idx_ds_subject (subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
