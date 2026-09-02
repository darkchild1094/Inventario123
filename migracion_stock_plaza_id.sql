-- ============================================================
-- Migración: soporte multi-negocio para stock personal (tipo='usuario')
-- ============================================================
-- Problema que resuelve:
-- La tabla `stock` no distinguía negocio/plaza para el stock personal
-- de cada usuario (una sola fila por usuario_id, sin importar OXXO/BARA).
-- El sistema inferia el negocio de un activo "asignado a mí mismo" desde
-- usuario.plaza_id (fijo), por lo que SIEMPRE se mostraba bajo el negocio
-- de la plaza principal del usuario, sin importar qué negocio se eligió
-- al registrar el activo.
--
-- Esta migración agrega una columna plaza_id a `stock`, para que un mismo
-- usuario pueda tener un stock personal separado por cada plaza/negocio
-- en el que trabaja (ej. un stock para OXXO-Valles y otro para BARA-León).
-- ============================================================

START TRANSACTION;

-- 1. Agregar columna plaza_id (nullable por compatibilidad)
ALTER TABLE stock
  ADD COLUMN plaza_id INT(10) UNSIGNED DEFAULT NULL AFTER bodega_id;

-- 2. Backfill: las filas de stock 'usuario' existentes se asocian a la
--    plaza principal actual del usuario, para no romper nada de lo que
--    ya está en producción (todo lo existente sigue viéndose igual).
UPDATE stock s
JOIN usuario u ON s.usuario_id = u.id
SET s.plaza_id = u.plaza_id
WHERE s.tipo = 'usuario';

-- 3. Llave foránea + índice
ALTER TABLE stock
  ADD KEY fk_stock_plaza (plaza_id),
  ADD CONSTRAINT fk_stock_plaza FOREIGN KEY (plaza_id) REFERENCES plaza(id);

-- 4. Evitar duplicados: un usuario no debe tener dos stocks para la misma plaza
ALTER TABLE stock
  ADD UNIQUE KEY uq_stock_usuario_plaza (usuario_id, plaza_id, tipo);

COMMIT;

-- Verificación rápida después de correr esto:
-- SELECT s.id, s.tipo, s.usuario_id, s.plaza_id, p.nombre AS plaza, n.nombre AS negocio
-- FROM stock s
-- LEFT JOIN plaza p ON s.plaza_id = p.id
-- LEFT JOIN region r ON p.region_id = r.id
-- LEFT JOIN negocio n ON r.negocio_id = n.id
-- WHERE s.tipo = 'usuario';
