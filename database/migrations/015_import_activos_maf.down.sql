-- =====================================================================
--  Reversión de la Migración 015
--  `activo` (y por lo tanto `movimiento`) estaban vacías antes de este
--  import, así que un vaciado completo es la reversión exacta.
-- =====================================================================

DELETE FROM `movimiento`;
DELETE FROM `activo`;
ALTER TABLE `activo` AUTO_INCREMENT = 1;
