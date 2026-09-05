-- =====================================================================
--  Reversión de la Migración 011
-- =====================================================================

DELETE mo FROM `modelo` mo
JOIN `marca` ma ON ma.id = mo.marca_id
WHERE (ma.nombre = 'BOSCH' AND mo.dispositivo_id = 1
       AND mo.nombre IN ('NDI-4502-A', 'NUC-51022-F2', 'NDN-50022-A3'))
   OR (ma.nombre = 'AXIS'  AND mo.dispositivo_id = 17 AND mo.nombre = 'S3008')
   OR (ma.nombre = 'QNAP'  AND mo.dispositivo_id = 17
       AND mo.nombre IN ('TS-131K', 'TS-131P', 'TS-128'));

DELETE FROM `marca`
WHERE `nombre` = 'QNAP'
  AND NOT EXISTS (SELECT 1 FROM `modelo` WHERE `marca_id` = `marca`.`id`);
