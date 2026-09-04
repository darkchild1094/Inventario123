-- =====================================================================
--  Reversión de la Migración 003
--  Base: femsa_assets
-- ---------------------------------------------------------------------
--  Quita los ATI asignados por 003, borra las 57 tiendas nuevas (id >= 949)
--  y los 4 usuarios nuevos, junto con su stock y filas en usuario_plaza.
--  Se saltan (protección FK) los registros que ya tengan activos asociados.
-- =====================================================================

SET NAMES utf8mb4;

SET @rosa_id    := 4;
SET @enrique_id := (SELECT id FROM `usuario` WHERE email = 'enrique.gil@oxxo.com' LIMIT 1);

-- 1) Quitar ATI responsable puesto por 003 (antes todo estaba en NULL)
UPDATE `tienda` SET `ati_usuario_id` = NULL
 WHERE `ati_usuario_id` IN (@rosa_id, @enrique_id);

-- 2) Borrar stock + tiendas nuevas (id >= 949) que no tengan activos
DELETE s FROM `stock` s
 JOIN `tienda` t     ON t.id = s.tienda_id
 LEFT JOIN `activo` a ON a.stock_id = s.id
 WHERE t.id >= 949 AND a.id IS NULL;

DELETE t FROM `tienda` t
 LEFT JOIN `activo` a1 ON a1.tienda_uso_id = t.id
 LEFT JOIN `activo` a2 ON a2.procedencia_tienda_id = t.id
 WHERE t.id >= 949 AND a1.id IS NULL AND a2.id IS NULL;

-- 3) Borrar usuarios nuevos + su stock + usuario_plaza
DELETE s FROM `stock` s
 JOIN `usuario` u     ON u.id = s.usuario_id
 LEFT JOIN `activo` a ON a.stock_id = s.id
 WHERE u.email IN ('enrique.gil@oxxo.com','jose.arcos@getic.com.mx','jose.flores@getic.com.mx','oscar.duenez@getic.com.mx')
   AND a.id IS NULL;

DELETE FROM `usuario_plaza`
 WHERE usuario_id IN (SELECT id FROM `usuario`
       WHERE email IN ('enrique.gil@oxxo.com','jose.arcos@getic.com.mx','jose.flores@getic.com.mx','oscar.duenez@getic.com.mx'));

DELETE FROM `usuario`
 WHERE email IN ('enrique.gil@oxxo.com','jose.arcos@getic.com.mx','jose.flores@getic.com.mx','oscar.duenez@getic.com.mx');
