-- 031 · Recalcular o Próximo Contacto dos Imobilizados abertos.
-- Regra: último contacto + 2 DIAS ÚTEIS, à mesma hora, saltando fim de semana/feriados.
-- (Substitui os valores antigos, que tinham sido calculados com a regra errada.)
-- Correr no phpMyAdmin (aba SQL). Só toca em Imobilizados (IMO+Baixa), que não têm data manual.

UPDATE tb_process SET next_contact_at='2026-08-21 17:37:23', updated_at=NOW() WHERE id=59; -- PR-2026-00000059
UPDATE tb_process SET next_contact_at='2026-08-21 14:08:55', updated_at=NOW() WHERE id=74; -- PR-2026-00000074
UPDATE tb_process SET next_contact_at='2026-08-21 08:27:19', updated_at=NOW() WHERE id=150; -- PR-2026-00000150
UPDATE tb_process SET next_contact_at='2026-08-21 09:34:21', updated_at=NOW() WHERE id=163; -- PR-2026-00000163
UPDATE tb_process SET next_contact_at='2026-08-24 14:43:17', updated_at=NOW() WHERE id=337; -- PR-2026-00000337
UPDATE tb_process SET next_contact_at='2026-08-21 09:34:48', updated_at=NOW() WHERE id=359; -- PR-2026-00000359
UPDATE tb_process SET next_contact_at='2026-08-21 09:35:19', updated_at=NOW() WHERE id=365; -- PR-2026-00000365
UPDATE tb_process SET next_contact_at='2026-08-21 09:18:51', updated_at=NOW() WHERE id=565; -- PR-2026-00000565
UPDATE tb_process SET next_contact_at='2026-08-21 09:37:35', updated_at=NOW() WHERE id=603; -- PR-2026-00000603
UPDATE tb_process SET next_contact_at='2026-08-21 09:39:14', updated_at=NOW() WHERE id=604; -- PR-2026-00000604
UPDATE tb_process SET next_contact_at='2026-08-24 14:02:25', updated_at=NOW() WHERE id=612; -- PR-2026-00000612
UPDATE tb_process SET next_contact_at='2026-08-21 09:38:17', updated_at=NOW() WHERE id=639; -- PR-2026-00000639
UPDATE tb_process SET next_contact_at='2026-08-13 15:00:11', updated_at=NOW() WHERE id=642; -- PR-2026-00000642
UPDATE tb_process SET next_contact_at='2026-08-04 17:12:32', updated_at=NOW() WHERE id=644; -- PR-2026-00000644
UPDATE tb_process SET next_contact_at='2026-08-21 14:04:38', updated_at=NOW() WHERE id=646; -- PR-2026-00000646
UPDATE tb_process SET next_contact_at='2026-08-13 14:59:36', updated_at=NOW() WHERE id=648; -- PR-2026-00000648
UPDATE tb_process SET next_contact_at='2026-08-04 17:48:10', updated_at=NOW() WHERE id=649; -- PR-2026-00000649
UPDATE tb_process SET next_contact_at='2026-08-13 14:52:42', updated_at=NOW() WHERE id=650; -- PR-2026-00000650
UPDATE tb_process SET next_contact_at='2026-08-04 17:52:50', updated_at=NOW() WHERE id=651; -- PR-2026-00000651
UPDATE tb_process SET next_contact_at='2026-08-10 09:24:54', updated_at=NOW() WHERE id=652; -- PR-2026-00000652
UPDATE tb_process SET next_contact_at='2026-08-21 17:35:15', updated_at=NOW() WHERE id=685; -- PR-2026-00000685
UPDATE tb_process SET next_contact_at='2026-08-21 17:39:19', updated_at=NOW() WHERE id=715; -- PR-2026-00000715
UPDATE tb_process SET next_contact_at='2026-08-21 11:04:30', updated_at=NOW() WHERE id=770; -- PR-2026-00000770
UPDATE tb_process SET next_contact_at='2026-08-21 17:34:07', updated_at=NOW() WHERE id=782; -- PR-2026-00000782
UPDATE tb_process SET next_contact_at='2026-08-21 17:31:33', updated_at=NOW() WHERE id=846; -- PR-2026-00000846
UPDATE tb_process SET next_contact_at='2026-08-21 10:53:49', updated_at=NOW() WHERE id=920; -- PR-2026-00000920
UPDATE tb_process SET next_contact_at='2026-08-20 15:38:08', updated_at=NOW() WHERE id=925; -- PR-2026-00000925
UPDATE tb_process SET next_contact_at='2026-08-21 10:26:46', updated_at=NOW() WHERE id=926; -- PR-2026-00000926
UPDATE tb_process SET next_contact_at='2026-08-21 08:58:13', updated_at=NOW() WHERE id=927; -- PR-2026-00000927
UPDATE tb_process SET next_contact_at='2026-08-21 08:17:46', updated_at=NOW() WHERE id=932; -- PR-2026-00000932
UPDATE tb_process SET next_contact_at='2026-08-20 10:59:49', updated_at=NOW() WHERE id=934; -- PR-2026-00000934
UPDATE tb_process SET next_contact_at='2026-08-21 08:19:43', updated_at=NOW() WHERE id=935; -- PR-2026-00000935
UPDATE tb_process SET next_contact_at='2026-08-24 14:02:12', updated_at=NOW() WHERE id=954; -- PR-2026-00000954
UPDATE tb_process SET next_contact_at='2026-08-18 14:09:18', updated_at=NOW() WHERE id=957; -- PR-2026-00000957
UPDATE tb_process SET next_contact_at='2026-08-21 17:30:47', updated_at=NOW() WHERE id=985; -- PR-2026-00000985
UPDATE tb_process SET next_contact_at='2026-08-21 10:09:29', updated_at=NOW() WHERE id=1045; -- PR-2026-00001045
UPDATE tb_process SET next_contact_at='2026-08-21 17:44:15', updated_at=NOW() WHERE id=1062; -- PR-2026-00001062
UPDATE tb_process SET next_contact_at='2026-08-24 16:06:57', updated_at=NOW() WHERE id=1080; -- PR-2026-00001080
UPDATE tb_process SET next_contact_at='2026-08-24 16:06:24', updated_at=NOW() WHERE id=1089; -- PR-2026-00001089

-- Conferência: Imobilizados abertos sem próximo (deve dar 0):
SELECT COUNT(*) AS sem_proximo FROM tb_process p JOIN tb_subject s ON s.id=p.subject_id JOIN tb_priority pr ON pr.id=p.priority_id JOIN tb_status st ON st.id=p.status_id WHERE s.code='IMO' AND pr.code='P4' AND p.deleted_at IS NULL AND p.last_contact_at IS NOT NULL AND p.next_contact_at IS NULL AND st.code NOT IN ('SOLVED','CLOSED');
