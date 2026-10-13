ALTER TABLE `tienda`
  DROP COLUMN `ultimo_inv_no_encontrados`,
  DROP COLUMN `ultimo_inv_encontrados`,
  DROP COLUMN `ultimo_inv_fecha`;
DROP TABLE IF EXISTS `inventario_tienda_detalle`;
DROP TABLE IF EXISTS `inventario_tienda`;
