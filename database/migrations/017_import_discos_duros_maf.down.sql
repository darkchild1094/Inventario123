-- =====================================================================
--  Reversión de la Migración 017
--  Borra todos los activos (y sus movimientos) del dispositivo
--  'DISCO DURO EXTERNO'. Nada más referencia ese dispositivo.
-- =====================================================================

SET @dde := (SELECT id FROM `dispositivo` WHERE `nombre` = 'DISCO DURO EXTERNO');

DELETE mv FROM `movimiento` mv
JOIN `activo` a ON a.id = mv.activo_id
JOIN `modelo` mo ON mo.id = a.modelo_id
WHERE mo.dispositivo_id = @dde;

DELETE a FROM `activo` a
JOIN `modelo` mo ON mo.id = a.modelo_id
WHERE mo.dispositivo_id = @dde;
