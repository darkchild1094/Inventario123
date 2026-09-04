-- =====================================================================
--  Migración 005 · Renombrar activo.placa -> activo.codigo_barras
--  Fecha: 2026-09-04 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  "Placa / Activo Fijo" pasa a llamarse "Código de barras" en toda la
--  aplicación; esta migración renombra la columna en la BD.
--  Reversión: 005_activo_placa_a_codigo_barras.down.sql
-- =====================================================================

ALTER TABLE `activo`
  CHANGE COLUMN `placa` `codigo_barras` varchar(100) DEFAULT NULL;
