-- =====================================================================
--  Migración 007 · Fotos del activo (equipo / serie / código de barras)
--  Fecha: 2026-09-04 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  El modelo Activo e ImageHelper ya sabían procesar foto_equipo,
--  foto_serie y foto_activo, pero la tabla nunca tuvo esas columnas y
--  los formularios nunca tuvieron los <input type="file">. Esta
--  migración agrega las columnas; el formulario se actualiza aparte.
-- =====================================================================

ALTER TABLE `activo`
  ADD COLUMN `foto_equipo` varchar(255) DEFAULT NULL AFTER `num_activo`,
  ADD COLUMN `foto_serie`  varchar(255) DEFAULT NULL AFTER `foto_equipo`,
  ADD COLUMN `foto_activo` varchar(255) DEFAULT NULL AFTER `foto_serie`;
