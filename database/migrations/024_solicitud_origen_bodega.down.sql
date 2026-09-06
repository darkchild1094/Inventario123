SET NAMES utf8mb4;

DELETE sta FROM `solicitud_traslado_activo` sta
  JOIN `solicitud_traslado` s ON s.id = sta.solicitud_id
  WHERE s.origen_bodega_id IS NOT NULL;
DELETE FROM `solicitud_traslado` WHERE `origen_bodega_id` IS NOT NULL;

ALTER TABLE `solicitud_traslado`
  DROP FOREIGN KEY `fk_sol_origen_bodega`,
  DROP KEY `fk_sol_origen_bodega`,
  DROP COLUMN `origen_bodega_id`;
