-- =====================================================================
--  Reversión de la Migración 016
--  Requiere revertir antes la 017 (activos de disco duro externo).
-- =====================================================================

-- vuelve el catálogo QNAP a GRABADOR CCTV
UPDATE `modelo` mo
JOIN `marca` ma ON ma.id = mo.marca_id
SET mo.`dispositivo_id` = (SELECT id FROM `dispositivo` WHERE `nombre` = 'GRABADOR CCTV')
WHERE ma.`nombre` = 'QNAP'
  AND mo.`nombre` IN ('TS-131K', 'TS-131P', 'TS-128');

-- borra los modelos TS-130 / GENERICO del dispositivo nuevo si quedaron sin activos
DELETE mo FROM `modelo` mo
JOIN `marca` ma ON ma.id = mo.marca_id
JOIN `dispositivo` d ON d.id = mo.dispositivo_id
WHERE ma.`nombre` = 'QNAP' AND d.`nombre` = 'DISCO DURO EXTERNO'
  AND mo.`nombre` IN ('TS-130', 'GENERICO')
  AND NOT EXISTS (SELECT 1 FROM `activo` WHERE `modelo_id` = mo.id);

-- borra el dispositivo si quedó sin modelos
DELETE FROM `dispositivo`
WHERE `nombre` = 'DISCO DURO EXTERNO'
  AND NOT EXISTS (SELECT 1 FROM `modelo` WHERE `dispositivo_id` = `dispositivo`.`id`);
