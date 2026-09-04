-- =====================================================================
--  Reversión de la Migración 002 · Historial + ATI por tienda
--  Base: femsa_assets  ·  Motor: MariaDB 10.4 / InnoDB
-- ---------------------------------------------------------------------
--  Deja `tienda` y el esquema como estaban antes de 002.
--  ATENCIÓN: elimina TODA la bitácora de movimientos.
-- =====================================================================

-- 1) Bitácora ---------------------------------------------------------
DROP TABLE IF EXISTS `movimiento`;

-- 2) ATI responsable por tienda ------------------------------------
ALTER TABLE `tienda`
  DROP FOREIGN KEY `fk_tienda_ati`,
  DROP INDEX `fk_tienda_ati`,
  DROP COLUMN `ati_usuario_id`;
