-- =====================================================================
--  Migración 029 · Inventario físico también para Stock PFS/Mi Stock
--  Fecha: 2026-09-30 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Generaliza inventario_bodega para auditar por escaneo el stock
--  PERSONAL de un ingeniero (PFS/ATI/coordinador/admin), no solo una
--  bodega física. `bodega_id` se vuelve opcional y se agrega
--  `stock_usuario_id` — exactamente uno de los dos debe ir lleno (lo
--  controla la app, no hay CHECK por compatibilidad con MySQL viejo).
--  No toca ninguna fila existente (ambas columnas nuevas/relajadas son
--  compatibles con lo que ya hay: la fila real en curso sigue con
--  bodega_id lleno y stock_usuario_id en NULL).
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `inventario_bodega`
  MODIFY `bodega_id` int(10) unsigned NULL,
  ADD COLUMN `stock_usuario_id` int(10) unsigned NULL AFTER `bodega_id`,
  ADD KEY `fk_invbod_stockusuario` (`stock_usuario_id`),
  ADD CONSTRAINT `fk_invbod_stockusuario` FOREIGN KEY (`stock_usuario_id`) REFERENCES `usuario` (`id`),
  ADD UNIQUE KEY `uq_invbod_usuario_periodo` (`stock_usuario_id`, `periodo`);
