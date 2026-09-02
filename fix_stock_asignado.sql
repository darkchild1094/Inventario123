-- ============================================================
-- FIX: Activos "asignado" deben guardarse en stock personal 
--      del usuario por plaza/negocio, NO en bodega
-- ============================================================
-- Problema: Los triggers no pasaban plaza_id al crear stock
-- de usuarios, y la lógica de HomeController confundía
-- la búsqueda de bodega incluso para activos asignados.
-- ============================================================

START TRANSACTION;

-- 1. Actualizar trigger de usuario para pasar plaza_id
DELIMITER $$
DROP TRIGGER IF EXISTS `trg_crear_stock_usuario`;
$$
CREATE TRIGGER `trg_crear_stock_usuario` AFTER INSERT ON `usuario` FOR EACH ROW
BEGIN
    -- Crear stock personal del usuario scopeado a su plaza_id
    INSERT INTO `stock` (`tipo`, `usuario_id`, `plaza_id`, `bodega_id`)
    VALUES ('usuario', NEW.id, NEW.plaza_id, NULL);
END
$$
DELIMITER ;

-- 2. Backfill: actualizar stocks de usuario existentes para asignar plaza_id
--    Buscar la plaza del usuario desde usuario_plaza o usuario.plaza_id
UPDATE stock s
JOIN usuario u ON s.usuario_id = u.id
SET s.plaza_id = u.plaza_id
WHERE s.tipo = 'usuario' AND s.plaza_id IS NULL;

-- 3. Trigger de bodega: ya está correcto (no pasa plaza_id porque es bodega global)
--    pero confirmar que existe
DELIMITER $$
DROP TRIGGER IF EXISTS `trg_crear_stock_bodega`;
$$
CREATE TRIGGER `trg_crear_stock_bodega` AFTER INSERT ON `bodega` FOR EACH ROW
BEGIN
    INSERT INTO `stock` (`tipo`, `usuario_id`, `bodega_id`, `plaza_id`)
    VALUES ('bodega', NULL, NEW.id, NULL);
END
$$
DELIMITER ;

-- 4. Agregar constraint UNIQUE para evitar duplicados en stock de usuario
--    (un usuario no debe tener dos stocks para la misma plaza)
ALTER TABLE stock
ADD UNIQUE KEY uq_stock_usuario_plaza (usuario_id, plaza_id, tipo);

COMMIT;

-- ============================================================
-- Verificación post-fix
-- ============================================================
-- SELECT s.id, s.tipo, s.usuario_id, u.nombre AS usuario, 
--        s.plaza_id, p.nombre AS plaza, r.negocio_id, n.nombre AS negocio
-- FROM stock s
-- LEFT JOIN usuario u ON s.usuario_id = u.id
-- LEFT JOIN plaza p ON s.plaza_id = p.id
-- LEFT JOIN region r ON p.region_id = r.id
-- LEFT JOIN negocio n ON r.negocio_id = n.id
-- WHERE s.tipo = 'usuario'
-- ORDER BY s.usuario_id, s.plaza_id;
