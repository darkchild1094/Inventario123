-- Revertir migración 027 · Inventario físico de bodega
SET NAMES utf8mb4;

DROP TABLE IF EXISTS `inventario_bodega_detalle`;
DROP TABLE IF EXISTS `inventario_bodega`;
