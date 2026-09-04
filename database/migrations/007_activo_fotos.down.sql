-- =====================================================================
--  Reversión de la Migración 007
-- =====================================================================

ALTER TABLE `activo`
  DROP COLUMN `foto_equipo`,
  DROP COLUMN `foto_serie`,
  DROP COLUMN `foto_activo`;
