-- =====================================================================
--  Reversión de la Migración 005
--  Vuelve activo.codigo_barras -> activo.placa
-- =====================================================================

ALTER TABLE `activo`
  CHANGE COLUMN `codigo_barras` `placa` varchar(100) DEFAULT NULL;
