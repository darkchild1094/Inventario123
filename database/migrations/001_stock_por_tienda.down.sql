-- =====================================================================
--  Reversión de la Migración 001 · Stock por tienda      (2026-09-03)
--  Base: femsa_assets  ·  Motor: MariaDB 10.4 / InnoDB
-- ---------------------------------------------------------------------
--  Deja `stock` exactamente como estaba antes de 001.
--
--  IMPORTANTE: si algún `activo` sigue apuntando a un stock de tipo
--  'tienda', el paso 3 (borrado) lo respeta y el paso 5 (reducir el
--  enum) FALLARÁ a propósito con error de truncado. Reasigna esos
--  activos a otro stock antes de revertir.
-- =====================================================================

-- 1) Quitar el trigger -------------------------------------------------
DROP TRIGGER IF EXISTS `trg_crear_stock_tienda`;

-- 2) Quitar CHECK, FK e índices nuevos; restaurar el UNIQUE original -
ALTER TABLE `stock`
  DROP CONSTRAINT `chk_stock_owner`,
  DROP FOREIGN KEY `fk_stock_tienda`,
  DROP INDEX `uq_stock_tienda`,
  DROP INDEX `uq_stock_bodega`,
  DROP INDEX `uq_stock_usuario_plaza`,
  ADD  UNIQUE KEY `uq_stock_usuario_plaza` (`usuario_id`,`plaza_id`,`tipo`),
  ADD  KEY `fk_stock_bodega` (`bodega_id`);

-- 3) Eliminar los stocks de tienda que no tengan activos -----------
DELETE s FROM `stock` s
LEFT  JOIN `activo` a ON a.`stock_id` = s.`id`
WHERE s.`tipo` = 'tienda' AND a.`id` IS NULL;

-- 4) Quitar la columna tienda_id ----------------------------------
ALTER TABLE `stock`
  DROP COLUMN `tienda_id`;

-- 5) Reducir el enum a su forma original ---------------------------
--    Falla si aún quedan filas con tipo='tienda' (ver cabecera).
ALTER TABLE `stock`
  MODIFY `tipo` enum('usuario','bodega') NOT NULL;
