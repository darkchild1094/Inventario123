-- =====================================================================
--  Reversión de la Migración 008
-- =====================================================================

DELETE FROM `movimiento` WHERE `activo_id` BETWEEN 52852 AND 52871;
DELETE FROM `activo` WHERE `id` BETWEEN 52852 AND 52871;
DELETE FROM `modelo` WHERE `id` IN (1133, 1134);
