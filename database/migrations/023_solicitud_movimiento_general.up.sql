-- =====================================================================
--  Migración 023 · Solicitud de traslado -> solicitud de movimiento general
--  Fecha: 2026-09-06 · Base: femsa_assets
-- ---------------------------------------------------------------------
--  Ahora TODO cambio de estatus/dueño de un activo (salvo el alta y la
--  instalación en tienda) pasa por una solicitud firmada. El destino y
--  el aprobador dependen del tipo:
--
--    destino     origen             aprueban / firman
--    ---------   ----------------   ---------------------------------
--    asignado    ingeniero A        el ingeniero B que recibe
--    en_bodega   ingeniero/tienda   un coordinador de la plaza
--    baja        ingeniero/tienda   un ATI de la plaza
--    garantia    ingeniero/tienda   un ATI  Y  un coordinador (doble)
--
--  Cambios de esquema (aditivos; la fila de prueba existente queda como
--  destino='en_bodega'):
--    + destino               (qué se vuelve el activo al aprobarse)
--    + origen_tienda_id       (cuando sale de una tienda, no de un ingeniero)
--    + destino_usuario_id     (ingeniero que recibe, para asignado->asignado)
--    + aprobador2_id / firma_aprobador2 / firmado_aprobador2_en (2º firmante)
--    destino_bodega_id y origen_usuario_id pasan a NULLABLE
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `solicitud_traslado`
  ADD COLUMN `destino` enum('en_bodega','asignado','baja','garantia') NOT NULL DEFAULT 'en_bodega' AFTER `estado`,
  MODIFY COLUMN `origen_usuario_id` int(10) unsigned DEFAULT NULL,
  MODIFY COLUMN `destino_bodega_id` int(10) unsigned DEFAULT NULL,
  ADD COLUMN `origen_tienda_id`   int(10) unsigned DEFAULT NULL AFTER `origen_usuario_id`,
  ADD COLUMN `destino_usuario_id` int(10) unsigned DEFAULT NULL AFTER `destino_bodega_id`,
  ADD COLUMN `aprobador2_id`      int(10) unsigned DEFAULT NULL AFTER `aprobador_id`,
  ADD COLUMN `firma_aprobador2`   varchar(255) DEFAULT NULL AFTER `firma_aprobador`,
  ADD COLUMN `firmado_aprobador2_en` timestamp NULL DEFAULT NULL AFTER `firmado_aprobador_en`,
  ADD KEY `fk_sol_origen_tienda` (`origen_tienda_id`),
  ADD KEY `fk_sol_destino_user`  (`destino_usuario_id`),
  ADD KEY `fk_sol_aprob2`        (`aprobador2_id`),
  ADD CONSTRAINT `fk_sol_origen_tienda` FOREIGN KEY (`origen_tienda_id`)   REFERENCES `tienda` (`id`)  ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sol_destino_user`  FOREIGN KEY (`destino_usuario_id`) REFERENCES `usuario` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sol_aprob2`        FOREIGN KEY (`aprobador2_id`)      REFERENCES `usuario` (`id`) ON DELETE SET NULL;
