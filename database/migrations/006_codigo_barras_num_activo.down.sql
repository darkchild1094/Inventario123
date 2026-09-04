-- =====================================================================
--  Reversión de la Migración 006
--  Quita la columna activo.num_activo.
--  NOTA: la recarga de valores de codigo_barras (columna H del MAF) es
--  forward-only; este down NO restaura los codigo_barras previos.
-- =====================================================================

ALTER TABLE `activo` DROP COLUMN `num_activo`;
