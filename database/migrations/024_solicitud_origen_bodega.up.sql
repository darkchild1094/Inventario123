-- =====================================================================
--  Migración 024 · Solicitud de movimiento: origen = bodega
--  Fecha: 2026-09-07 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Sacar equipo de bodega para asignarlo a un usuario (o a baja/garantía)
--  también requiere solicitud firmada. Se agrega origen_bodega_id como
--  tercera opción de origen (además de origen_usuario_id / origen_tienda_id).
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `solicitud_traslado`
  ADD COLUMN `origen_bodega_id` int(10) unsigned DEFAULT NULL AFTER `origen_tienda_id`,
  ADD KEY `fk_sol_origen_bodega` (`origen_bodega_id`),
  ADD CONSTRAINT `fk_sol_origen_bodega` FOREIGN KEY (`origen_bodega_id`)
      REFERENCES `bodega` (`id`) ON DELETE SET NULL;
