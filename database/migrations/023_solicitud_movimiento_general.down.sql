-- =====================================================================
--  Rollback Migración 023
-- =====================================================================
SET NAMES utf8mb4;

-- Las solicitudes que no eran 'en_bodega' no tienen sentido sin el esquema nuevo.
DELETE sta FROM `solicitud_traslado_activo` sta
  JOIN `solicitud_traslado` s ON s.id = sta.solicitud_id
  WHERE s.destino <> 'en_bodega';
DELETE FROM `solicitud_traslado` WHERE `destino` <> 'en_bodega';

ALTER TABLE `solicitud_traslado`
  DROP FOREIGN KEY `fk_sol_origen_tienda`,
  DROP FOREIGN KEY `fk_sol_destino_user`,
  DROP FOREIGN KEY `fk_sol_aprob2`,
  DROP KEY `fk_sol_origen_tienda`,
  DROP KEY `fk_sol_destino_user`,
  DROP KEY `fk_sol_aprob2`,
  DROP COLUMN `destino`,
  DROP COLUMN `origen_tienda_id`,
  DROP COLUMN `destino_usuario_id`,
  DROP COLUMN `aprobador2_id`,
  DROP COLUMN `firma_aprobador2`,
  DROP COLUMN `firmado_aprobador2_en`,
  MODIFY COLUMN `origen_usuario_id` int(10) unsigned NOT NULL,
  MODIFY COLUMN `destino_bodega_id` int(10) unsigned NOT NULL;
