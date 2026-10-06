-- 032 — Un proyecto RENTEC pertenece a una plaza.
--
-- Hasta ahora `proyecto_rentec` no guardaba plaza y la visibilidad se deducía de
-- los movimientos que el proyecto ya había tocado:
--
--   WHERE pr.usuario_id = :yo
--      OR EXISTS (movimiento del proyecto con plaza_id IN (mis plazas))
--
-- El efecto es que un proyecto recién creado, que todavía no tiene ningún
-- movimiento, SÓLO lo ve quien lo creó. El resto del equipo de la plaza no puede
-- entrar a instalar hasta que alguien más haya movido algo, que es justo al
-- revés de como se trabaja: se abre el folio y entre todos lo van llenando.
--
-- Con plaza_id propia la visibilidad es directa y vale desde el minuto cero.
-- Se backfillea con la plaza de los movimientos existentes cuando se puede, y
-- si no, con la plaza principal de quien lo creó.

ALTER TABLE `proyecto_rentec`
  ADD COLUMN `plaza_id` int(10) unsigned DEFAULT NULL AFTER `estado`,
  ADD KEY `fk_rentec_plaza` (`plaza_id`),
  ADD CONSTRAINT `fk_rentec_plaza` FOREIGN KEY (`plaza_id`) REFERENCES `plaza` (`id`);

-- 1) la plaza que ya tocó el proyecto (la de su movimiento más antiguo)
UPDATE `proyecto_rentec` pr
SET pr.plaza_id = (
    SELECT m.plaza_id FROM `movimiento` m
    WHERE m.proyecto_rentec_id = pr.id AND m.plaza_id IS NOT NULL
    ORDER BY m.creado_en LIMIT 1
)
WHERE pr.plaza_id IS NULL;

-- 2) los que no tocaron nada: la plaza principal de quien lo creó
UPDATE `proyecto_rentec` pr
JOIN `usuario` u ON u.id = pr.usuario_id
SET pr.plaza_id = u.plaza_id
WHERE pr.plaza_id IS NULL;
