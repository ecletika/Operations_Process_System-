-- 031 · Backfill do Próximo Contacto dos Imobilizados que ficaram SEM próximo.
-- Valores já calculados aqui (último contacto + 960 min úteis; horário 08:30–18:00, 2ª–6ª).
-- Correr no phpMyAdmin (aba SQL). Só atualiza linhas ainda a NULL — pode repetir sem risco.

UPDATE tb_process SET next_contact_at='2026-08-18 11:10:00', updated_at=NOW() WHERE id=957 AND next_contact_at IS NULL; -- PR-2026-00000957

-- Conferência (deve dar 0 depois de correr):
SELECT COUNT(*) AS imobilizados_sem_proximo FROM tb_process p JOIN tb_subject s ON s.id=p.subject_id JOIN tb_priority pr ON pr.id=p.priority_id JOIN tb_status st ON st.id=p.status_id WHERE s.code='IMO' AND pr.code='P4' AND p.deleted_at IS NULL AND p.last_contact_at IS NOT NULL AND p.next_contact_at IS NULL AND st.code NOT IN ('SOLVED','CLOSED');
