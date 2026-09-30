-- Revertir migración 029 · Inventario físico Stock PFS
-- OJO: falla si ya existen filas con stock_usuario_id lleno (bodega_id
-- no podría volver a NOT NULL con NULLs presentes) — borra esas filas
-- primero a mano si hace falta revertir después de usar la función.
SET NAMES utf8mb4;

ALTER TABLE `inventario_bodega`
  DROP FOREIGN KEY `fk_invbod_stockusuario`,
  DROP KEY `uq_invbod_usuario_periodo`,
  DROP COLUMN `stock_usuario_id`,
  MODIFY `bodega_id` int(10) unsigned NOT NULL;
