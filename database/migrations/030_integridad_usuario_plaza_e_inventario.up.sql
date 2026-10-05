-- 030 — Invariantes que hoy sólo sostiene PHP, respaldados por el motor.
--
-- B-05: `usuario_plaza` es la única tabla del esquema con los índices de clave
--       foránea pero sin las restricciones. Es justo la tabla que decide el
--       alcance de cada rol, así que una fila huérfana da acceso a una plaza
--       inexistente o cuelga permisos de un usuario borrado.
--       Verificado antes de aplicar: 0 huérfanos en producción.
--
-- B-06: `inventario_bodega` sirve dos objetivos con columnas nullable
--       (bodega_id para el inventario de bodega, stock_usuario_id para el de
--       stock personal) y nada garantizaba que hubiera exactamente uno.
--       `stock` resuelve el mismo problema con chk_stock_owner; se copia el
--       patrón. Verificado: 0 filas con ambos NULL y 0 con ambos puestos.

-- ── B-05 ─────────────────────────────────────────────────────────────────────
ALTER TABLE `usuario_plaza`
  ADD CONSTRAINT `fk_usuario_plaza_usuario`
      FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_usuario_plaza_plaza`
      FOREIGN KEY (`plaza_id`)  REFERENCES `plaza`   (`id`);

-- ── B-06 ─────────────────────────────────────────────────────────────────────
ALTER TABLE `inventario_bodega`
  ADD CONSTRAINT `chk_invbod_objetivo` CHECK (
    (`bodega_id` IS NOT NULL AND `stock_usuario_id` IS NULL)
    OR
    (`bodega_id` IS NULL AND `stock_usuario_id` IS NOT NULL)
  );
