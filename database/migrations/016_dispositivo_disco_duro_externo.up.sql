-- =====================================================================
--  Migración 016 · Dispositivo "DISCO DURO EXTERNO" (NAS)
--  Fecha: 2026-09-05 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Los NAS QNAP estaban mal categorizados bajo GRABADOR CCTV. El MAF
--  tiene su propia categoría "DISCO DURO EXTERNO" (181 filas que la
--  migración 015 no importó). Esta migración:
--    1. crea el dispositivo "DISCO DURO EXTERNO"
--    2. mueve el catálogo QNAP (TS-131K / TS-131P / TS-128) a ese
--       dispositivo
--    3. agrega el modelo QNAP TS-130 (aparece 27x en el MAF)
--  Idempotente.
-- =====================================================================

SET NAMES utf8mb4;

-- 1) dispositivo nuevo
INSERT INTO `dispositivo` (`nombre`)
SELECT 'DISCO DURO EXTERNO'
WHERE NOT EXISTS (SELECT 1 FROM `dispositivo` WHERE `nombre` = 'DISCO DURO EXTERNO');

-- 2) mover el catálogo QNAP existente a ese dispositivo
UPDATE `modelo` mo
JOIN `marca` ma ON ma.id = mo.marca_id
SET mo.`dispositivo_id` = (SELECT id FROM `dispositivo` WHERE `nombre` = 'DISCO DURO EXTERNO')
WHERE ma.`nombre` = 'QNAP'
  AND mo.`nombre` IN ('TS-131K', 'TS-131P', 'TS-128');

-- 3) modelos QNAP TS-130 y GENERICO bajo el dispositivo nuevo
INSERT INTO `modelo` (`nombre`, `dispositivo_id`, `marca_id`)
SELECT v.nombre,
       (SELECT id FROM `dispositivo` WHERE `nombre` = 'DISCO DURO EXTERNO'),
       (SELECT id FROM `marca` WHERE `nombre` = 'QNAP')
FROM (SELECT 'TS-130' AS nombre UNION ALL SELECT 'GENERICO') v
WHERE NOT EXISTS (
    SELECT 1 FROM `modelo` mo
    WHERE mo.`marca_id` = (SELECT id FROM `marca` WHERE `nombre` = 'QNAP')
      AND mo.`dispositivo_id` = (SELECT id FROM `dispositivo` WHERE `nombre` = 'DISCO DURO EXTERNO')
      AND mo.`nombre` = v.nombre
);
