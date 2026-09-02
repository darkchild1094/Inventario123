-- ============================================================
-- Reparación del activo ya registrado mal (id=25, serie MXKF833444bara)
-- Corre esto DESPUÉS de aplicar migracion_stock_plaza_id.sql
-- ============================================================

-- 1. Verifica primero que ya tienes (o crea) un stock personal de Raúl (usuario_id=1)
--    scopeado a la plaza de BARA (plaza_id=5, "León").
--    Si no existe, créalo:
INSERT INTO stock (tipo, usuario_id, bodega_id, plaza_id)
SELECT 'usuario', 1, NULL, 5
WHERE NOT EXISTS (
    SELECT 1 FROM stock WHERE tipo = 'usuario' AND usuario_id = 1 AND plaza_id = 5
);

-- 2. Obtén el id de ese stock BARA de Raúl
-- SELECT id FROM stock WHERE tipo='usuario' AND usuario_id=1 AND plaza_id=5;

-- 3. Reasigna el activo 25 a ese stock (reemplaza <ID_STOCK_BARA> por el id real del paso 2)
-- UPDATE activo SET stock_id = <ID_STOCK_BARA> WHERE id = 25;

-- 4. Verifica que ahora sí se ve como BARA:
-- SELECT a.id, a.serie, n.nombre AS negocio, p.nombre AS plaza
-- FROM activo a
-- LEFT JOIN stock s ON a.stock_id = s.id
-- LEFT JOIN plaza p ON s.plaza_id = p.id
-- LEFT JOIN region r ON p.region_id = r.id
-- LEFT JOIN negocio n ON r.negocio_id = n.id
-- WHERE a.id = 25;
