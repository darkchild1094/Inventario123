-- =====================================================================
--  Rollback Migración 020 · Solicitud de traslado a bodega con doble firma
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `movimiento`
  DROP FOREIGN KEY `fk_mov_solicitud`,
  DROP KEY `idx_mov_solicitud`,
  DROP COLUMN `solicitud_traslado_id`;

DROP TABLE IF EXISTS `solicitud_traslado_activo`;
DROP TABLE IF EXISTS `solicitud_traslado`;
